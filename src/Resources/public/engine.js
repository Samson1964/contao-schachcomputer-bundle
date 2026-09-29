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
 * Stockfish im Web Worker. Es läuft höchstens eine Suche zugleich.
 */
export class Engine {

    /**
     * Merkt sich die Adresse; der Worker startet erst beim ersten Zug.
     *
     * @param {string} url Adresse von stockfish-19-lite-single.js
     */
    constructor(url) {
        this.url = url
        this.worker = null
        this.bereit = null
        this.wartende = []
        this.kette = Promise.resolve()
    }

    /**
     * Startet den Worker und wartet auf uciok und readyok.
     *
     * @returns {Promise<void>} Erfüllt, sobald die Engine Befehle annimmt
     */
    starten() {
        if (!this.bereit) {
            this.worker = new Worker(this.url)
            this.worker.onmessage = ereignis => String(ereignis.data).split("\n").forEach(zeile => this.empfangen(zeile.trim()))
            this.bereit = (async () => {
                const uciok = this.warten(zeile => zeile === "uciok")
                this.senden("uci")
                await uciok
                await this.synchronisieren()
            })()
        }
        return this.bereit
    }

    /**
     * Lässt die Engine einen Zug suchen.
     *
     * Suchen werden nacheinander ausgeführt: Wird eine neue angefordert,
     * während die vorige noch läuft (etwa nach dem Zurücknehmen in der
     * Übungspartie), wartet sie, statt der Engine ein zweites „go" zu schicken.
     *
     * @param {string[]} zuege Bisherige Züge in UCI-Schreibweise ab Grundstellung
     * @param {Object} einstellungen Einstellungen der Stufe
     * @param {number} zeit Rechenzeit in ms (bei festen Tiefen ungenutzt)
     * @returns {Promise<string>} Der Zug der Engine in UCI-Schreibweise
     */
    zug(zuege, einstellungen, zeit) {
        const suche = this.kette.then(() => this.suchen(zuege, einstellungen, zeit))
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
     * Beendet den Worker, etwa beim Verlassen der Seite.
     */
    beenden() {
        if (this.worker) {
            this.worker.terminate()
        }
        this.worker = null
        this.bereit = null
        this.wartende = []
        this.kette = Promise.resolve()
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
     */
    senden(befehl) {
        this.worker.postMessage(befehl)
    }

    /**
     * Liefert ein Versprechen, das sich mit der ersten passenden Zeile erfüllt.
     *
     * @param {function(string): boolean} bedingung Prüft eine Ausgabezeile
     * @returns {Promise<string>} Die passende Zeile
     */
    warten(bedingung) {
        return new Promise(erfuellen => this.wartende.push({bedingung, erfuellen}))
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
