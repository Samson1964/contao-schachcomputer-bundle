/*
 * Anbindung an Stockfish für das Schachcomputer-Bundle.
 *
 * Stockfish 19 läuft als WebAssembly in einem Web Worker (stockfish.js,
 * Fassung „lite single-threaded"). Der Worker sucht seine .wasm-Datei neben
 * sich, unter demselben Namen mit .wasm statt .js.
 *
 * Die Stärke kommt aus Engine\Stufen (PHP): ab 1400 UCI_Elo, darunter Skill
 * Level 0 mit begrenzter Tiefe und einem Anteil zufälliger Züge, den dieses
 * Modul auswürfelt.
 *
 * @license LGPL-3.0-or-later
 */

/**
 * Liefert die UCI-Befehle, die eine Stufe einstellen.
 *
 * @param {Object} einstellungen Einstellungen aus Engine\Stufen::einstellungen()
 * @returns {string[]} setoption-Befehle in der richtigen Reihenfolge
 */
export function optionsBefehle(einstellungen) {
    if (einstellungen.uciElo) {
        return [
            "setoption name UCI_LimitStrength value true",
            `setoption name UCI_Elo value ${einstellungen.uciElo}`
        ]
    }
    return [
        "setoption name UCI_LimitStrength value false",
        `setoption name Skill Level value ${einstellungen.skill ?? 0}`
    ]
}

/**
 * Liefert den go-Befehl: feste Tiefe bei nachgebauten Stufen, sonst feste
 * Rechenzeit.
 *
 * @param {Object} einstellungen Einstellungen der Stufe
 * @param {number} rechenzeit Rechenzeit in ms
 * @returns {string} Der go-Befehl
 */
export function goBefehl(einstellungen, rechenzeit) {
    return einstellungen.tiefe ? `go depth ${einstellungen.tiefe}` : `go movetime ${rechenzeit}`
}

/**
 * Würfelt die Rechenzeit eines Zuges zwischen zeitMin und zeitMax aus.
 *
 * @param {Object} einstellungen Einstellungen der Stufe
 * @param {function(): number} [zufall] Zufallsquelle 0 ≤ x < 1, für Tests ersetzbar
 * @returns {number} Rechenzeit in ms
 */
export function rechenzeit(einstellungen, zufall = Math.random) {
    return einstellungen.zeitMin + Math.floor(zufall() * (einstellungen.zeitMax - einstellungen.zeitMin + 1))
}

/**
 * Entscheidet, ob die Engine statt ihres Zuges einen Zufallszug spielt.
 *
 * @param {Object} einstellungen Einstellungen der Stufe (zufall = Anteil 0…1)
 * @param {string[]} legaleZuege Alle erlaubten Züge in UCI-Schreibweise
 * @param {function(): number} [zufall] Zufallsquelle, für Tests ersetzbar
 * @returns {string|null} Ein zufälliger erlaubter Zug, oder null für „Engine fragen"
 */
export function zufallsZug(einstellungen, legaleZuege, zufall = Math.random) {
    if (!einstellungen.zufall || legaleZuege.length === 0 || zufall() >= einstellungen.zufall) {
        return null
    }
    return legaleZuege[Math.floor(zufall() * legaleZuege.length)]
}

/**
 * Liest den Zug aus einer bestmove-Zeile.
 *
 * @param {string} zeile Eine Ausgabezeile der Engine
 * @returns {string|null} Der Zug in UCI-Schreibweise, oder null
 */
export function bestmove(zeile) {
    const treffer = /^bestmove ([a-h][1-8][a-h][1-8][qrbn]?)/.exec(zeile)
    return treffer ? treffer[1] : null
}

/**
 * Begrenzt die Wartezeit auf ein Versprechen.
 *
 * Das ursprüngliche Versprechen läuft weiter; nur das zurückgegebene weist
 * nach Ablauf ab. Wer danach aufräumen muss (etwa die Engine beenden), tut
 * das selbst.
 *
 * @param {Promise<*>} versprechen Das Versprechen, auf das gewartet wird
 * @param {number} zeitlimitMs Höchstwartezeit in ms
 * @param {string} meldung Text des Fehlers bei Zeitablauf
 * @returns {Promise<*>} Erfüllt mit dem Ergebnis, oder weist bei Fehler
 *                       bzw. nach Ablauf des Zeitlimits ab
 */
export function mitZeitlimit(versprechen, zeitlimitMs, meldung) {
    let timer
    const ablauf = new Promise((erfuellen, abweisen) => {
        timer = setTimeout(() => abweisen(new Error(meldung)), zeitlimitMs)
    })
    return Promise.race([versprechen, ablauf]).finally(() => clearTimeout(timer))
}

/**
 * Stockfish im Web Worker. Es läuft höchstens eine Suche zugleich.
 *
 * Fällt der Worker aus (Skript oder .wasm nicht ladbar, kein WebAssembly,
 * Content-Security-Policy ohne 'wasm-unsafe-eval'), weist die Engine alle
 * wartenden Suchen ab und setzt sich zurück; der nächste Aufruf startet einen
 * neuen Worker. So wartet niemand ewig auf einen Zug, der nie kommt.
 */
export class Engine {

    /**
     * Merkt sich die Adresse; der Worker startet erst mit starten() oder
     * beim ersten Zug.
     *
     * @param {string} url Adresse von stockfish-19-lite-single.js
     */
    constructor(url) {
        this.url = url
        this.worker = null
        this.bereit = null
        this.wartende = []
        this.kette = Promise.resolve()
        // Zählt jedes Beenden und jeden Ausfall. Suchen, die davor angefordert
        // wurden, laufen danach nicht mehr los (siehe zug()).
        this.epoche = 0
    }

    /**
     * Startet den Worker, falls er nicht schon läuft, und wartet auf uciok
     * und readyok. Taugt zum Vorwärmen beim Laden der Seite.
     *
     * Das Zeitlimit begrenzt nur das Warten des Aufrufers: Der Worker lädt
     * weiter, ein späterer Aufruf kann also noch Erfolg haben. Wer einen
     * frischen Start will, ruft vorher beenden().
     *
     * @param {number} [zeitlimitMs] Höchstwartezeit in ms; ohne Angabe unbegrenzt
     * @returns {Promise<void>} Erfüllt, sobald die Engine Befehle annimmt;
     *                          weist ab, wenn der Worker nicht startet oder
     *                          ausfällt, oder nach Ablauf des Zeitlimits
     */
    starten(zeitlimitMs) {
        if (!this.bereit) {
            let worker
            try {
                worker = new Worker(this.url)
            } catch (fehler) {
                // Etwa eine CSP, die Worker verbietet: sofort abweisen, beim
                // nächsten Aufruf neu versuchen
                return Promise.reject(fehler)
            }
            this.worker = worker
            // Meldungen eines schon ersetzten Workers zählen nicht mehr
            worker.onmessage = ereignis => {
                if (this.worker === worker) {
                    String(ereignis.data).split("\n").forEach(zeile => this.empfangen(zeile.trim()))
                }
            }
            worker.onerror = ereignis => {
                if (this.worker === worker) {
                    this.zuruecksetzen(new Error(`Stockfish ist ausgefallen: ${ereignis?.message ?? "unbekannter Fehler"}`))
                }
            }
            worker.onmessageerror = () => {
                if (this.worker === worker) {
                    this.zuruecksetzen(new Error("Stockfish hat eine unlesbare Nachricht geschickt."))
                }
            }
            this.bereit = (async () => {
                const uciok = this.warten(zeile => zeile === "uciok")
                this.senden("uci")
                await uciok
                await this.synchronisieren()
            })()
            // Ein Fehlstart, auf den gerade niemand wartet (Vorwärmen mit
            // abgelaufenem Zeitlimit), soll nicht als unbehandelt gemeldet werden
            this.bereit.catch(() => undefined)
        }
        if (zeitlimitMs === undefined) {
            return this.bereit
        }
        return mitZeitlimit(this.bereit, zeitlimitMs, "Stockfish ist nicht rechtzeitig bereit.")
    }

    /**
     * Lässt die Engine einen Zug suchen.
     *
     * Suchen werden nacheinander ausgeführt: Wird eine neue angefordert,
     * während die vorige noch läuft (etwa nach dem Zurücknehmen in der
     * Übungspartie), wartet sie, statt der Engine ein zweites „go" zu schicken.
     * Wird die Engine beendet oder fällt sie aus, bevor eine wartende Suche an
     * der Reihe ist, weist diese ab, statt einen neuen Worker zu starten.
     *
     * @param {string[]} zuege Bisherige Züge in UCI-Schreibweise ab Grundstellung
     * @param {Object} einstellungen Einstellungen der Stufe
     * @param {number} zeit Rechenzeit in ms (bei festen Tiefen ungenutzt)
     * @returns {Promise<string>} Der Zug der Engine in UCI-Schreibweise; weist
     *                            ab bei Ausfall oder Beenden der Engine
     */
    zug(zuege, einstellungen, zeit) {
        const epoche = this.epoche
        const suche = this.kette.then(() => {
            if (epoche !== this.epoche) {
                throw new Error("Stockfish wurde beendet, bevor die Suche beginnen konnte.")
            }
            return this.suchen(zuege, einstellungen, zeit)
        })
        this.kette = suche.catch(() => undefined)
        return suche
    }

    /**
     * Führt eine einzelne Suche aus.
     *
     * @param {string[]} zuege Bisherige Züge in UCI-Schreibweise
     * @param {Object} einstellungen Einstellungen der Stufe
     * @param {number} zeit Rechenzeit in ms
     * @returns {Promise<string>} Der Zug der Engine
     */
    async suchen(zuege, einstellungen, zeit) {
        await this.starten()
        optionsBefehle(einstellungen).forEach(befehl => this.senden(befehl))
        await this.synchronisieren()
        this.senden(zuege.length ? `position startpos moves ${zuege.join(" ")}` : "position startpos")
        const antwort = this.warten(zeile => zeile.startsWith("bestmove"))
        this.senden(goBefehl(einstellungen, zeit))
        const zug = bestmove(await antwort)
        if (!zug) {
            throw new Error("Stockfish hat keinen Zug geliefert.")
        }
        return zug
    }

    /**
     * Beendet den Worker, etwa beim Verlassen der Seite oder vor einem
     * zweiten Versuch mit frischer Engine.
     *
     * Offene und angeforderte Suchen weisen mit einem Fehler ab, statt still
     * liegen zu bleiben. Ein späterer Aufruf von starten() oder zug() startet
     * einen neuen Worker.
     */
    beenden() {
        this.zuruecksetzen(new Error("Stockfish wurde beendet."))
    }

    /**
     * Beendet den Worker, weist alle Wartenden mit dem Fehler ab und setzt
     * Worker und Bereitschaft zurück.
     *
     * Gemeinsamer Weg von beenden() und den Fehlerereignissen des Workers.
     * Die Liste der Wartenden wird vor dem Abweisen geleert, damit ein
     * Wartender, der beim Abweisen gleich neu startet, nicht mit abgewiesen wird.
     *
     * @param {Error} fehler Der Grund, mit dem die Wartenden abweisen
     */
    zuruecksetzen(fehler) {
        if (this.worker) {
            this.worker.terminate()
        }
        this.worker = null
        this.bereit = null
        this.epoche++
        const wartende = this.wartende
        this.wartende = []
        wartende.forEach(wartend => wartend.abweisen(fehler))
    }

    /**
     * Wartet, bis die Engine alle bisherigen Befehle verarbeitet hat.
     *
     * @returns {Promise<string>} Erfüllt mit „readyok"
     */
    synchronisieren() {
        const readyok = this.warten(zeile => zeile === "readyok")
        this.senden("isready")
        return readyok
    }

    /**
     * Schickt einen Befehl an den Worker.
     *
     * @param {string} befehl Ein UCI-Befehl
     * @throws {Error} Wenn der Worker inzwischen beendet wurde
     */
    senden(befehl) {
        if (!this.worker) {
            throw new Error("Stockfish läuft nicht.")
        }
        this.worker.postMessage(befehl)
    }

    /**
     * Liefert ein Versprechen, das sich mit der ersten passenden Zeile erfüllt.
     *
     * @param {function(string): boolean} bedingung Prüft eine Ausgabezeile
     * @returns {Promise<string>} Die passende Zeile; weist ab, wenn die
     *                            Engine vorher beendet wird oder ausfällt
     */
    warten(bedingung) {
        return new Promise((erfuellen, abweisen) => this.wartende.push({bedingung, erfuellen, abweisen}))
    }

    /**
     * Verteilt eine Ausgabezeile an den ersten Wartenden, dessen Bedingung passt.
     *
     * @param {string} zeile Eine Ausgabezeile der Engine
     */
    empfangen(zeile) {
        const index = this.wartende.findIndex(wartend => wartend.bedingung(zeile))
        if (index >= 0) {
            const [wartend] = this.wartende.splice(index, 1)
            wartend.erfuellen(zeile)
        }
    }
}
