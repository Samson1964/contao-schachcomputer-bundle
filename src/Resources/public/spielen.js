/*
 * Schachcomputer-Bundle: Partien gegen Stockfish im Browser.
 *
 * Gewertete Partie: Der Server führt die Partie. Jeder Zug – auch der der
 * Engine – geht an /_schachcomputer/zug; maßgeblich sind Stand und Uhren des
 * Servers (Spieler und, seit 1.1.0, Computer; die Uhren hier zeigen nur an).
 * chess.js dient nur der sofortigen Zugprüfung und dem Aufbau der
 * Züge für Stockfish. Ob die Partie zu Ende ist, entscheidet allein der
 * Server (seine Wiederholungsprüfung weicht in einem Randfall von chess.js ab).
 * Über ein Remisangebot entscheidet Stockfish hier im Browser; der Server
 * prüft nur die Rahmenregeln (/_schachcomputer/remis).
 *
 * Übungspartie: läuft ganz im Browser, ohne Uhr, mit Zurücknehmen. Am Ende
 * geht die Zugliste an /_schachcomputer/uebung (nur Mitglieder).
 *
 * @license LGPL-3.0-or-later
 */

import {Chessboard, COLOR, INPUT_EVENT_TYPE, BORDER_TYPE} from "./vendor/cm-chessboard/src/Chessboard.js"
import {Markers, MARKER_TYPE} from "./vendor/cm-chessboard/src/extensions/markers/Markers.js"
import {PromotionDialog, PROMOTION_DIALOG_RESULT_TYPE} from "./vendor/cm-chessboard/src/extensions/promotion-dialog/PromotionDialog.js"
import {Chess} from "./vendor/chess.js/chess.js"

// Eigene Module mit derselben Versionsangabe laden, mit der dieses Skript
// eingebunden wurde („?v=…"); sonst nähme der Browser nach einem Update eine
// alte Fassung aus seinem Zwischenspeicher. Ein statischer Import kann die
// Angabe nicht übernehmen, deshalb der dynamische Import.
const VERSION = new URL(import.meta.url).search
const {Engine, rechenzeit, zeitBudget, zufallsZug, mitZeitlimit, nimmtRemisAn, VOLLE_STAERKE} = await import("./engine.js" + VERSION)
const {Uhr, fristSekunden, warngrenze} = await import("./uhr.js" + VERSION)

/** Markierung des letzten Zuges. */
const MARKER_ZUG = MARKER_TYPE.square

/** Klasse der gerade laufenden Uhr. */
const UHR_AKTIV = "schachcomputer-uhr--aktiv"

/** Klasse einer Uhr, deren Zeit knapp wird (rote Ziffern, siehe warngrenze()). */
const UHR_KNAPP = "schachcomputer-uhr--knapp"

/** Zeit, die nach Ablauf der Frist für den ersten Zug gewartet wird, bevor der Stand geholt wird (ms). */
const FRIST_PUFFER = 1500

/**
 * Abstand, in dem der Countdown der Frist für den ersten Zug nachsieht, ob
 * sich die Sekundenzahl geändert hat (ms). Kürzer als eine Sekunde, damit
 * die Zahl höchstens einen Takt nach dem Sekundenwechsel springt.
 */
const FRIST_TAKT = 250

/** So lange wird auf eine startende Engine gewartet, etwa vor einer gewerteten Partie (ms). */
const ENGINE_START_MS = 20000

/** Höchstdauer einer Suche in gewerteten Partien; danach ein zweiter Versuch mit frischer Engine (ms). */
const ENGINE_SUCHE_MS = 15000

/**
 * Ausgleich des Servers bei abgelaufener Uhr (Uhr::AUSGLEICH_MS) plus Puffer:
 * So lange meldet der Server nach Restzeit 0 noch „läuft" (ms).
 */
const UHR_AUSGLEICH_MS = 1000 + 300

/** Rechenzeit, mit der Stockfish ein Remisangebot prüft, solange die Uhr des Spielers nicht knapp ist (ms). */
const REMIS_PRUEFZEIT_MS = 1000

/** Höchstdauer der Prüfung eines Remisangebots samt Start der Engine; die Uhr des Spielers läuft dabei (ms). */
const REMIS_ZEITLIMIT_MS = 5000

const warten = ms => new Promise(erfuellen => setTimeout(erfuellen, ms))

/**
 * Wandelt einen UCI-Zug in das Zugformat von chess.js.
 *
 * @param {string} uci Etwa „e2e4" oder „b7a8q"
 * @returns {{from: string, to: string, promotion: (string|undefined)}}
 */
function zugObjekt(uci) {
    return {from: uci.slice(0, 2), to: uci.slice(2, 4), promotion: uci.length > 4 ? uci[4] : undefined}
}

/**
 * Bildet das PGN-Ergebnis aus den Punkten des Spielers.
 *
 * @param {number|null} punkte 1, 0,5, 0 oder null (offen)
 * @param {string} farbe Farbe des Spielers, „w" oder „b"
 * @returns {string} 1-0, 0-1, 1/2-1/2 oder *
 */
function pgnErgebnis(punkte, farbe) {
    if (punkte === null) {
        return "*"
    }
    if (punkte === 0.5) {
        return "1/2-1/2"
    }
    return (farbe === "w") === (punkte === 1) ? "1-0" : "0-1"
}

class Schachcomputer {

    /**
     * Baut Brett und Bedienelemente auf und lädt den Stand des Spielers.
     *
     * @param {HTMLElement} element Der Container mit data-konfiguration
     */
    constructor(element) {
        this.element = element
        this.konfiguration = JSON.parse(element.dataset.konfiguration)
        this.texte = this.konfiguration.texte
        const feld = selektor => element.querySelector(selektor)
        this.feld = {
            wertungen: feld("[data-wertungen]"),
            start: feld("[data-start]"),
            bedenkzeit: feld("[data-bedenkzeit]"),
            stufe: feld("[data-stufe]"),
            gewertet: feld('[data-aktion="gewertet"]'),
            uebung: feld('[data-aktion="uebung"]'),
            keineBedenkzeit: feld("[data-keine-bedenkzeit]"),
            partie: feld("[data-partie]"),
            gegner: feld("[data-gegner]"),
            uhren: feld("[data-uhren]"),
            uhr: feld("[data-uhr]"),
            uhrEngine: feld("[data-uhr-engine]"),
            rahmenSpieler: feld('[data-uhr-rahmen="spieler"]'),
            rahmenEngine: feld('[data-uhr-rahmen="engine"]'),
            status: feld("[data-status]"),
            gast: feld("[data-gast]"),
            knoepfe: {
                abbrechen: feld('[data-aktion="abbrechen"]'),
                remis: feld('[data-aktion="remis"]'),
                aufgeben: feld('[data-aktion="aufgeben"]'),
                zuruecknehmen: feld('[data-aktion="zuruecknehmen"]'),
                beenden: feld('[data-aktion="beenden"]'),
                pgn: feld('[data-aktion="pgn"]'),
                neu: feld('[data-aktion="neu"]')
            }
        }

        this.brett = new Chessboard(feld(".schachcomputer-brett"), {
            assetsUrl: this.konfiguration.assetsUrl,
            style: {borderType: BORDER_TYPE.frame, pieces: {file: "pieces/standard.svg"}, animationDuration: 250},
            extensions: [{class: Markers, props: {autoMarkers: null}}, {class: PromotionDialog}]
        })
        this.engine = new Engine(this.konfiguration.engineUrl)
        this.uhr = new Uhr(this.feld.uhr, () => this.partieNeuLaden())
        // Läuft die Uhr des Computers hier ab, geschieht nichts: Ob sein Zug
        // zu spät kam, entscheidet der Server, wenn der Zug eintrifft
        this.uhrEngine = new Uhr(this.feld.uhrEngine, () => {})
        this.eingabe = this.eingabe.bind(this)

        // Erhöht sich bei jedem Neuanfang, Neuladen und Aufgeben. Antworten und
        // Engine-Züge aus einer älteren Generation werden verworfen.
        this.generation = 0
        this.modus = null
        this.partie = null
        this.chess = new Chess()
        this.wertungen = null
        this.fristTimer = null
        this.fristTakt = null
        this.uebungsPunkte = null

        this.feld.start.addEventListener("submit", ereignis => {
            ereignis.preventDefault()
            this.gewertetStarten()
        })
        this.feld.uebung.addEventListener("click", () => this.uebungStarten())
        this.feld.bedenkzeit.addEventListener("change", () => this.stufeVorschlagen())
        this.feld.knoepfe.abbrechen.addEventListener("click", () => this.abbrechen())
        this.feld.knoepfe.remis.addEventListener("click", () => this.remisAnbieten())
        this.feld.knoepfe.aufgeben.addEventListener("click", () => this.aufgeben())
        this.feld.knoepfe.zuruecknehmen.addEventListener("click", () => this.zuruecknehmen())
        this.feld.knoepfe.beenden.addEventListener("click", () => this.uebungBeenden(false))
        this.feld.knoepfe.pgn.addEventListener("click", () => this.pgnKopieren())
        this.feld.knoepfe.neu.addEventListener("click", () => this.startZeigen())
        window.addEventListener("pagehide", () => this.seiteVerlassen())
        window.addEventListener("pageshow", ereignis => this.seiteZurueck(ereignis))

        this.auswahlFuellen()
        this.standLaden(true)
        this.engineVorwaermen()
    }

    // ------------------------------------------------------------------
    // Engine

    /**
     * Lädt Stockfish schon beim Aufruf der Seite, ohne darauf zu warten.
     *
     * Die .wasm-Datei ist 1,8 MB groß; über Mobilfunk dauert das. Schlägt das
     * Vorwärmen fehl, bleibt nur eine Warnung in der Konsole: Vor einer
     * gewerteten Partie wird ohnehin noch einmal auf die Engine gewartet.
     */
    engineVorwaermen() {
        this.engine.starten(ENGINE_START_MS).catch(fehler => console.warn("Schachcomputer: Stockfish ist noch nicht bereit.", fehler))
    }

    /**
     * Räumt auf, wenn die Seite verlassen wird oder in den bfcache wandert.
     *
     * Die Generation steigt, damit eine abgewiesene Suche oder eine späte
     * Antwort des Servers nichts mehr anrichtet; Uhren und Frist ruhen, bis
     * pageshow den Stand neu lädt. Die Zugeingabe wird abgeschaltet, sonst
     * meldet cm-chessboard beim Zurückkommen „moveInput already enabled",
     * weil enableMoveInput() kein zweites Mal ohne disableMoveInput() dazwischen
     * aufgerufen werden darf; disableMoveInput() ist auch ohne laufende
     * Eingabe gefahrlos aufrufbar.
     */
    seiteVerlassen() {
        this.generation++
        this.fristBeenden()
        this.uhrenAnhalten()
        this.brett.disableMoveInput()
        this.engine.beenden()
    }

    /**
     * Macht weiter, wenn die Seite aus dem bfcache zurückkommt.
     *
     * Worker, Uhr und Frist hat seiteVerlassen() angehalten; die Engine wird
     * hier neu vorgewärmt, weil pagehide ihren Worker beendet hat. Bei einer
     * gewerteten Partie wird der Stand dieser Partie neu geholt (auch wenn sie
     * inzwischen beendet wurde, etwa durch abgelaufene Uhr oder Zeitüberschreitung
     * – standLaden() liefert nur noch laufende Partien und zeigte dann fälschlich
     * das Startformular). Eine Übungspartie gibt es nur im Browser; sie wird
     * fortgesetzt statt verworfen (war die Engine am Zug, sucht sie neu). Ohne
     * laufende Partie bleibt es beim gewohnten Neuladen des Stands.
     *
     * @param {PageTransitionEvent} ereignis Das pageshow-Ereignis
     */
    seiteZurueck(ereignis) {
        if (!ereignis.persisted) {
            return
        }
        this.engineVorwaermen()
        if (this.modus === "uebung") {
            this.weiter()
            return
        }
        if (this.modus === "gewertet") {
            this.partieNeuLaden()
            return
        }
        this.standLaden()
    }

    /**
     * Fragt Stockfish nach einem Zug.
     *
     * In gewerteten Partien mit Zeitlimit: Wird die Engine nicht bereit oder
     * zieht sie nicht rechtzeitig, wird sie beendet und ein zweiter Versuch
     * mit frischer Engine gemacht. Ohne Zug beim Server gälte die Partie nach
     * 60 s als verlassen und verloren; ein hängender Worker darf das nicht
     * verursachen. Übungspartien haben keine Frist und warten unbegrenzt.
     *
     * @param {string[]} zuege Bisherige Züge in UCI-Schreibweise
     * @param {Object} einstellungen Einstellungen der Stufe
     * @param {number} zeit Rechenzeit in ms
     * @param {number} generation Generation beim Beginn des Engine-Zuges
     * @returns {Promise<string>} Der Zug in UCI-Schreibweise; weist ab, wenn
     *                            auch der zweite Versuch scheitert oder die
     *                            Generation inzwischen gewechselt hat
     */
    async engineSuche(zuege, einstellungen, zeit, generation) {
        if (this.modus !== "gewertet") {
            return this.engine.zug(zuege, einstellungen, zeit)
        }
        try {
            return await this.engineVersuch(zuege, einstellungen, zeit)
        } catch (fehler) {
            this.engine.beenden()
            // Seite verlassen oder Partie neu geladen: kein zweiter Versuch
            if (generation !== this.generation) {
                throw fehler
            }
            console.warn("Schachcomputer: Stockfish antwortet nicht, zweiter Versuch mit frischer Engine.", fehler)
        }
        try {
            return await this.engineVersuch(zuege, einstellungen, zeit)
        } catch (fehler) {
            this.engine.beenden()
            throw fehler
        }
    }

    /**
     * Ein einzelner Versuch: Engine starten (höchstens ENGINE_START_MS) und
     * suchen lassen (höchstens ENGINE_SUCHE_MS).
     *
     * @param {string[]} zuege Bisherige Züge in UCI-Schreibweise
     * @param {Object} einstellungen Einstellungen der Stufe
     * @param {number} zeit Rechenzeit in ms
     * @returns {Promise<string>} Der Zug; weist bei Ausfall oder Zeitablauf ab
     */
    async engineVersuch(zuege, einstellungen, zeit) {
        await this.engine.starten(ENGINE_START_MS)
        return mitZeitlimit(this.engine.zug(zuege, einstellungen, zeit), ENGINE_SUCHE_MS, "Stockfish hat nicht rechtzeitig gezogen.")
    }

    // ------------------------------------------------------------------
    // Server

    /**
     * Schickt eine Anfrage an die JSON-Schnittstelle.
     *
     * @param {string} url Adresse
     * @param {Object} [daten] Bei POST der JSON-Inhalt; ohne Daten GET
     * @returns {Promise<{status: number, ok: boolean, daten: Object}>}
     */
    async anfrage(url, daten) {
        const optionen = {
            credentials: "same-origin",
            cache: "no-store",
            headers: {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest"}
        }
        if (daten !== undefined) {
            optionen.method = "POST"
            optionen.headers["Content-Type"] = "application/json"
            optionen.body = JSON.stringify(daten)
        }
        const antwort = await fetch(url, optionen)
        let inhalt = {}
        try {
            inhalt = await antwort.json()
        } catch (fehler) {
            inhalt = {}
        }
        return {status: antwort.status, ok: antwort.ok, daten: inhalt}
    }

    /**
     * Holt Wertungen und eine laufende Partie; setzt sie fort oder zeigt den Start.
     *
     * @param {boolean} [aufruf] true nur beim ersten Laden der Seite: Dann
     *                           zählt der Server einen Aufruf für die Statistik
     */
    async standLaden(aufruf = false) {
        const generation = ++this.generation
        this.status(this.texte.laden)
        let antwort
        try {
            antwort = await this.anfrage(this.konfiguration.standUrl + (aufruf ? "?aufruf=1" : ""))
        } catch (fehler) {
            this.fehler(fehler)
            return
        }
        if (generation !== this.generation) {
            return
        }
        if (!antwort.ok) {
            this.fehler(new Error("HTTP " + antwort.status))
            return
        }
        this.wertungenUebernehmen(antwort.daten)
        if (antwort.daten.partie) {
            await this.fortsetzen(antwort.daten.partie)
        } else {
            this.startZeigen()
        }
    }

    /**
     * Holt den Stand der laufenden gewerteten Partie, auch wenn sie inzwischen
     * beendet ist (Uhr abgelaufen, Zug abgewiesen, Frist verstrichen).
     */
    async partieNeuLaden() {
        if (!this.partie || this.modus !== "gewertet") {
            return
        }
        const generation = ++this.generation
        this.uhrenAnhalten()
        this.brett.disableMoveInput()
        let antwort
        try {
            antwort = await this.anfrage(this.konfiguration.standUrl + "?partie=" + this.partie.id)
        } catch (fehler) {
            this.fehler(fehler)
            return
        }
        if (generation !== this.generation) {
            return
        }
        if (!antwort.ok || !antwort.daten.partie) {
            await this.standLaden()
            return
        }
        this.wertungenUebernehmen(antwort.daten)
        await this.fortsetzen(antwort.daten.partie)
    }

    /**
     * Übernimmt Wertungen und Gastkennzeichen aus einer Stand-Antwort.
     *
     * @param {Object} daten Antwort von /_schachcomputer/stand
     */
    wertungenUebernehmen(daten) {
        this.wertungen = daten.wertungen
        this.feld.gast.hidden = !daten.gast
        this.wertungenZeigen()
        this.stufeVorschlagen()
    }

    /**
     * Frischt nur die Wertungsanzeige auf, etwa nach dem Ende einer Partie.
     */
    async wertungenAuffrischen() {
        try {
            const antwort = await this.anfrage(this.konfiguration.standUrl)
            if (antwort.ok) {
                this.wertungen = antwort.daten.wertungen
                this.wertungenZeigen()
            }
        } catch (fehler) {
            console.error("Schachcomputer:", fehler)
        }
    }

    // ------------------------------------------------------------------
    // Startformular

    /**
     * Füllt die Auswahl der Bedenkzeiten (nach Klassen gruppiert) und Stufen.
     */
    auswahlFuellen() {
        const gruppen = {}
        for (const bedenkzeit of this.konfiguration.bedenkzeiten) {
            if (!gruppen[bedenkzeit.klasse]) {
                gruppen[bedenkzeit.klasse] = document.createElement("optgroup")
                gruppen[bedenkzeit.klasse].label = this.texte.klassen[bedenkzeit.klasse] ?? bedenkzeit.klasse
                this.feld.bedenkzeit.append(gruppen[bedenkzeit.klasse])
            }
            const option = new Option(bedenkzeit.name, String(bedenkzeit.id))
            option.dataset.klasse = bedenkzeit.klasse
            gruppen[bedenkzeit.klasse].append(option)
        }
        for (const stufe of this.konfiguration.stufen) {
            this.feld.stufe.append(new Option(String(stufe), String(stufe)))
        }
        this.feld.stufe.value = "1500"

        const keine = this.konfiguration.bedenkzeiten.length === 0
        this.feld.bedenkzeit.disabled = keine
        this.feld.gewertet.disabled = keine
        this.feld.keineBedenkzeit.hidden = !keine
    }

    /**
     * Wählt die Stufe vor, die zur Wertung in der Klasse der Bedenkzeit passt.
     */
    stufeVorschlagen() {
        const option = this.feld.bedenkzeit.selectedOptions[0]
        const klasse = option ? option.dataset.klasse : null
        if (this.wertungen && klasse && this.wertungen[klasse]) {
            this.feld.stufe.value = String(this.wertungen[klasse].vorschlag)
        }
    }

    /**
     * Zeigt die eigenen Wertungen, vorläufige mit „?".
     */
    wertungenZeigen() {
        const teile = Object.entries(this.wertungen ?? {}).map(([klasse, wertung]) =>
            `${this.texte.klassen[klasse] ?? klasse} ${wertung.wertung}${wertung.vorlaeufig ? "?" : ""}`)
        this.feld.wertungen.textContent = teile.length ? `${this.texte.deineWertung}: ${teile.join(" · ")}` : ""
    }

    /**
     * @returns {string} „w", „b" oder „zufall"
     */
    gewaehlteFarbe() {
        const wahl = this.feld.start.querySelector('input[name="farbe"]:checked')
        return wahl ? wahl.value : "zufall"
    }

    /**
     * Zeigt das Startformular und die Grundstellung.
     *
     * Die Uhren stehen neben dem Brett, nicht im Partiebereich; sie müssen
     * deshalb eigens ausgeblendet werden.
     */
    startZeigen() {
        this.generation++
        this.fristBeenden()
        this.uhrenAnhalten()
        this.brett.disableMoveInput()
        this.brett.removeMarkers()
        this.brett.setOrientation(COLOR.white)
        this.brett.setPosition(new Chess().fen(), false)
        this.modus = null
        this.partie = null
        this.feld.partie.hidden = true
        this.feld.uhren.hidden = true
        this.feld.start.hidden = false
        this.stufeVorschlagen()
        this.status("")
    }

    /**
     * Startet eine gewertete Partie; läuft schon eine, wird sie fortgesetzt.
     *
     * Vorher wird auf die bereite Engine gewartet (höchstens ENGINE_START_MS).
     * Ist sie nicht bereit, beginnt keine Partie: Sonst verlöre der Spieler
     * nach seinem ersten Zug, weil der Engine-Zug nie beim Server ankäme.
     */
    async gewertetStarten() {
        const generation = ++this.generation
        this.feld.gewertet.disabled = true
        this.status(this.texte.laden)
        let antwort
        try {
            try {
                await this.engine.starten(ENGINE_START_MS)
            } catch (fehler) {
                console.error("Schachcomputer:", fehler)
                // Ohne Neustart wartete der nächste Klick auf dasselbe hängende
                // „bereit" – die Meldung rät aber zu „versuche es noch einmal"
                this.engine.beenden()
                if (generation === this.generation) {
                    this.status(this.texte.engineNichtBereit, "fehler")
                }
                return
            }
            if (generation !== this.generation) {
                return
            }
            antwort = await this.anfrage(this.konfiguration.startUrl, {
                bedenkzeit: Number(this.feld.bedenkzeit.value),
                stufe: Number(this.feld.stufe.value),
                farbe: this.gewaehlteFarbe()
            })
        } catch (fehler) {
            this.fehler(fehler)
            return
        } finally {
            this.feld.gewertet.disabled = this.konfiguration.bedenkzeiten.length === 0
        }
        if (generation !== this.generation) {
            return
        }
        if (antwort.status === 409) {
            await this.standLaden()
            return
        }
        if (!antwort.ok) {
            this.fehler(new Error("HTTP " + antwort.status))
            return
        }
        await this.fortsetzen(antwort.daten.partie)
    }

    /**
     * Startet eine Übungspartie im Browser.
     */
    async uebungStarten() {
        const generation = ++this.generation
        const stufe = Number(this.feld.stufe.value)
        let farbe = this.gewaehlteFarbe()
        if (farbe === "zufall") {
            farbe = Math.random() < 0.5 ? "w" : "b"
        }
        this.modus = "uebung"
        this.partie = {farbe, stufe, status: "laeuft", einstellungen: this.konfiguration.einstellungen[stufe]}
        this.uebungsPunkte = null
        this.chess = new Chess()
        this.partieBereichZeigen(`${this.texte.gegner.replace("%s", stufe)} · ${this.texte.uebung}`)
        this.farbe = farbe === "w" ? COLOR.white : COLOR.black
        this.brett.removeMarkers()
        await this.brett.setOrientation(this.farbe)
        await this.brett.setPosition(this.chess.fen(), false)
        if (generation === this.generation) {
            this.weiter()
        }
    }

    // ------------------------------------------------------------------
    // Partie

    /**
     * Übernimmt eine gewertete Partie vom Server und macht mit ihr weiter.
     *
     * @param {Object} partie Partie aus der Antwort des Servers
     */
    async fortsetzen(partie) {
        const generation = ++this.generation
        this.fristBeenden()
        this.modus = "gewertet"
        this.partie = partie
        this.chess = new Chess()
        partie.zuege.forEach(uci => this.chess.move(zugObjekt(uci)))
        const klasse = this.texte.klassen[partie.klasse] ?? partie.klasse
        this.partieBereichZeigen(`${this.texte.gegner.replace("%s", partie.stufe)} · ${klasse} ${partie.minuten}+${partie.inkrement}`)
        this.farbe = partie.farbe === "w" ? COLOR.white : COLOR.black
        await this.brett.setOrientation(this.farbe)
        await this.brett.setPosition(this.chess.fen(), false)
        this.letztenZugMarkieren()
        if (generation === this.generation) {
            this.weiter()
        }
    }

    /**
     * Blendet das Startformular aus und den Partiebereich ein.
     *
     * Die Uhren gibt es nur in gewerteten Partien; die des Computers fehlt
     * bei Partien, die vor Fassung 1.1.0 begonnen wurden (restzeitEngine -1).
     * In gewerteten Partien bekommen beide Uhren hier ihre Warngrenze für die
     * roten Ziffern, je nach Grundzeit der Partie (warngrenze()). Die Uhr des
     * Computers bekommt keine, wenn es sie nicht gibt: Ein Platzhalter ohne
     * Wert soll nicht rot werden. Übungspartien brauchen keine, ihre Uhren
     * sind ausgeblendet.
     *
     * @param {string} gegner Beschreibung des Gegners
     */
    partieBereichZeigen(gegner) {
        const gewertet = this.modus === "gewertet"
        const ohneEngineUhr = !gewertet || this.partie.restzeitEngine < 0
        this.feld.start.hidden = true
        this.feld.partie.hidden = false
        this.feld.gegner.textContent = gegner
        this.feld.uhren.hidden = !gewertet
        this.feld.rahmenEngine.hidden = ohneEngineUhr
        if (gewertet) {
            const grenze = warngrenze(this.partie.minuten)
            this.uhr.warnungSetzen(grenze, this.feld.rahmenSpieler, UHR_KNAPP)
            this.uhrEngine.warnungSetzen(ohneEngineUhr ? null : grenze, this.feld.rahmenEngine, UHR_KNAPP)
        }
    }

    /**
     * Entscheidet nach jeder Änderung, wie es weitergeht.
     */
    weiter() {
        this.knoepfeSetzen()
        if (this.modus === "uebung") {
            if (this.partie.status !== "laeuft") {
                return
            }
            if (this.chess.isGameOver()) {
                this.uebungBeenden(false)
            } else if (this.chess.turn() === this.partie.farbe) {
                this.spielerIstAmZug()
            } else {
                this.engineZieht()
            }
            return
        }
        if (this.partie.status !== "laeuft") {
            this.ende()
        } else if (this.partie.spielerAmZug) {
            this.spielerIstAmZug()
        } else {
            this.uhr.zeigen(this.partie.restzeit)
            this.engineUhrStellen()
            this.uhrHervorheben(this.partie.engineUhrLaeuft ? "engine" : null)
            this.engineZieht()
        }
    }

    /**
     * Gibt die Zugeingabe frei und startet bei gewerteten Partien die Uhr.
     *
     * Meldet der Server „läuft, Restzeit 0", ist die Zeit abgelaufen, aber
     * der Ausgleich (Uhr::AUSGLEICH_MS) noch nicht verstrichen. Die Uhr bei 0
     * zu starten hieße, nach 100 ms erneut zu fragen – mehrmals, mit jedes Mal
     * neu aufgebautem Brett. Stattdessen wird einmal nach dem Ausgleich
     * nachgefragt; ein Zug in dieser Zeit hebt die Nachfrage auf.
     *
     * Die Uhr des Computers steht und zeigt seine Restzeit nach dem letzten
     * Zug. Hervorgehoben wird die Uhr des Spielers nur, wenn sie läuft – für
     * den ersten Zug gibt es stattdessen die Frist, deren Countdown
     * fristStarten() im Status anzeigt. Das gilt auch, wenn Stockfish Weiß
     * hat: Die Frist beginnt dann nach seinem ersten Zug, und der Server
     * meldet ersterZugFrist entsprechend.
     */
    spielerIstAmZug() {
        this.zugBeginn = performance.now()
        let ersteFrist = null
        if (this.modus === "gewertet") {
            const partie = this.partie
            if (partie.uhrLaeuft && partie.restzeit <= 0) {
                this.uhr.zeigen(0)
                this.fristBeenden()
                this.fristTimer = setTimeout(() => this.partieNeuLaden(), UHR_AUSGLEICH_MS)
            } else if (partie.uhrLaeuft) {
                this.uhr.starten(partie.restzeit)
            } else {
                this.uhr.zeigen(partie.restzeit)
            }
            this.engineUhrStellen()
            this.uhrHervorheben(partie.uhrLaeuft ? "spieler" : null)
            ersteFrist = partie.ersterZugFrist
        }
        if (ersteFrist === null) {
            this.status(this.texte.amZug)
        } else {
            this.fristStarten(ersteFrist)
        }
        this.brett.enableMoveInput(this.eingabe, this.farbe)
    }

    /**
     * Startet die Frist für den ersten Zug: Der Status zählt die Sekunden
     * herunter, und nach Fristende wird der Stand vom Server neu geholt.
     *
     * Der Server bricht die Partie ungewertet ab, wenn der erste Zug nicht
     * binnen 60 Sekunden eintrifft; der Spieler soll das sehen, bevor es
     * geschieht. Die Sekunden werden aus einem festen Endzeitpunkt berechnet
     * (fristSekunden()), nicht aufsummiert; der Takt (FRIST_TAKT) schaut nur
     * nach, ob sich die Zahl geändert hat, und schreibt den Status dann
     * höchstens einmal je Sekunde. Bei 0 hört der Takt auf; das Neuladen folgt
     * nach FRIST_PUFFER, damit der Server die Frist sicher als verstrichen
     * kennt. Eine laufende Frist wird vorher beendet, es läuft nie eine zweite.
     *
     * @param {number} frist Verbleibende Frist laut Server in ms (ersterZugFrist)
     */
    fristStarten(frist) {
        this.fristBeenden()
        const ende = performance.now() + frist
        let angezeigt = null
        const anzeigen = () => {
            const sekunden = fristSekunden(ende, performance.now())
            if (sekunden !== angezeigt) {
                angezeigt = sekunden
                this.status(this.texte.ersterZug.replace("%s", sekunden))
            }
            if (sekunden === 0) {
                clearInterval(this.fristTakt)
                this.fristTakt = null
            }
        }
        this.fristTakt = setInterval(anzeigen, FRIST_TAKT)
        anzeigen()
        this.fristTimer = setTimeout(() => this.partieNeuLaden(), frist + FRIST_PUFFER)
    }

    /**
     * Räumt alles ab, was mit der Frist zusammenhängt: das geplante Neuladen
     * (nach der Frist für den ersten Zug oder nach dem Ausgleich einer
     * abgelaufenen Uhr) und den Takt des Countdowns.
     *
     * Wird an jeder Stelle gerufen, die die Partie verlässt oder weiterführt
     * (Zug gemacht, Abbruch, Aufgabe, Neustart, Neuladen, Ende, Seite
     * verlassen), damit nirgends ein Takt weiterläuft und den Status
     * überschreibt. Ohne laufende Frist geschieht nichts.
     */
    fristBeenden() {
        clearTimeout(this.fristTimer)
        clearInterval(this.fristTakt)
        this.fristTimer = null
        this.fristTakt = null
    }

    /**
     * Ereignisbehandlung der Zugeingabe von cm-chessboard (wie im
     * Schachaufgaben-Bundle, samt Umwandlungsdialog).
     *
     * @param {Object} ereignis Das Ereignis des Bretts
     * @returns {boolean|undefined} Ob die Eingabe fortgesetzt werden darf
     */
    eingabe(ereignis) {
        switch (ereignis.type) {
            case INPUT_EVENT_TYPE.moveInputStarted: {
                this.brett.removeLegalMovesMarkers()
                const zuege = this.chess.moves({square: ereignis.squareFrom, verbose: true})
                this.brett.addLegalMovesMarkers(zuege)
                return zuege.length > 0
            }
            case INPUT_EVENT_TYPE.validateMoveInput: {
                this.brett.removeLegalMovesMarkers()
                const passend = this.chess.moves({square: ereignis.squareFrom, verbose: true})
                    .filter(zug => zug.to === ereignis.squareTo)
                if (passend.length === 0) {
                    return false
                }
                if (passend[0].promotion) {
                    this.brett.showPromotionDialog(ereignis.squareTo, this.farbe, ergebnis => {
                        if (ergebnis.type === PROMOTION_DIALOG_RESULT_TYPE.pieceSelected) {
                            this.spielerZug(ereignis.squareFrom, ereignis.squareTo, ergebnis.piece.charAt(1))
                        } else {
                            this.brett.setPosition(this.chess.fen(), true)
                            this.brett.enableMoveInput(this.eingabe, this.farbe)
                        }
                    })
                    return true
                }
                // Erst ziehen, wenn das Brett seine eigene Zugdarstellung abgeschlossen hat
                ereignis.chessboard.state.moveInputProcess.then(() => {
                    this.spielerZug(ereignis.squareFrom, ereignis.squareTo)
                })
                return true
            }
            case INPUT_EVENT_TYPE.moveInputCanceled:
                this.brett.removeLegalMovesMarkers()
                return
            case INPUT_EVENT_TYPE.moveInputFinished:
                if (ereignis.legalMove) {
                    this.brett.disableMoveInput()
                }
                return
        }
    }

    /**
     * Führt einen Zug des Spielers aus und meldet ihn bei gewerteten Partien.
     *
     * @param {string} von Ausgangsfeld
     * @param {string} nach Zielfeld
     * @param {string} [umwandlung] Figur bei Bauernumwandlung (q, r, b, n)
     */
    async spielerZug(von, nach, umwandlung) {
        this.fristBeenden()
        let zug
        try {
            zug = this.chess.move({from: von, to: nach, promotion: umwandlung})
        } catch (fehler) {
            await this.brett.setPosition(this.chess.fen(), true)
            this.brett.enableMoveInput(this.eingabe, this.farbe)
            return
        }
        this.brett.disableMoveInput()
        await this.brett.setPosition(this.chess.fen(), true)
        this.zugMarkieren(zug)
        if (this.modus === "uebung") {
            this.weiter()
            return
        }
        this.uhrenAnhalten()
        // Der Zug ist unterwegs: Anbieten geht erst wieder, wenn der Spieler erneut am Zug ist
        this.feld.knoepfe.remis.hidden = true
        await this.zugMelden(zug.lan, Math.round(performance.now() - this.zugBeginn))
    }

    /**
     * Lässt Stockfish ziehen – oder würfelt bei schwachen Stufen einen
     * Zufallszug aus. Der Zug kommt nie schneller als nach der Rechenzeit.
     *
     * In gewerteten Partien mit Uhr des Computers begrenzt zeitBudget() die
     * Rechenzeit, damit der Computer nicht auf Zeit verliert; weil dieselbe
     * Zeit auch das Warten auf die Mindestzeit bestimmt, zieht er bei knapper
     * Uhr auch auf schwachen Stufen schneller. Übungspartien und Partien ohne
     * Uhr des Computers (vor 1.1.0) rechnen wie bisher.
     *
     * Scheitert Stockfish in einer gewerteten Partie auch im zweiten Versuch
     * (siehe engineSuche()), erscheint eine eigene Meldung, und beide Uhren
     * werden angehalten, damit die hervorgehobene Uhr des Computers nicht
     * weiter bis 0 läuft; der Server wertet die Partie dann nach 60 s ohne
     * Engine-Zug als verlassen.
     */
    async engineZieht() {
        const generation = this.generation
        const halbzuege = this.chess.history().length
        const einstellungen = this.partie.einstellungen
        let zeit = rechenzeit(einstellungen)
        if (this.modus === "gewertet" && this.partie.restzeitEngine >= 0) {
            zeit = Math.min(zeit, zeitBudget(this.partie.restzeitEngine, this.partie.inkrement * 1000))
        }
        const beginn = performance.now()
        this.status(this.texte.engineDenkt)
        let uci
        try {
            const legale = this.chess.moves({verbose: true}).map(zug => zug.lan)
            uci = zufallsZug(einstellungen, legale)
                ?? await this.engineSuche(this.chess.history({verbose: true}).map(zug => zug.lan), einstellungen, zeit, generation)
        } catch (fehler) {
            // Inzwischen Seite verlassen, neu geladen oder zurückgenommen: kein Fehler für den Spieler
            if (generation !== this.generation) {
                return
            }
            if (this.modus === "gewertet") {
                console.error("Schachcomputer:", fehler)
                // Sonst liefe die hervorgehobene Uhr des Computers bis 0 weiter,
                // obwohl er gar nicht mehr rechnet
                this.uhrenAnhalten()
                this.status(this.texte.engineAusgefallen, "fehler")
            } else {
                this.fehler(fehler)
            }
            return
        }
        const rest = zeit - (performance.now() - beginn)
        if (rest > 0) {
            await warten(rest)
        }
        // Inzwischen neu begonnen, aufgegeben oder zurückgenommen: Zug verwerfen
        if (generation !== this.generation || halbzuege !== this.chess.history().length) {
            return
        }
        const zug = this.chess.move(zugObjekt(uci))
        // Nicht auf die Animation warten: In einem Hintergrund-Tab ruht
        // requestAnimationFrame, der Zug muss aber binnen der Engine-Frist beim
        // Server sein. Das Brett holt die Darstellung nach, sobald der Tab
        // wieder sichtbar ist.
        this.brett.setPosition(this.chess.fen(), true)
        this.zugMarkieren(zug)
        if (this.modus === "uebung") {
            this.weiter()
            return
        }
        // Der Zug ist unterwegs: Die Uhr des Computers steht, bis die Antwort
        // des Servers seine neue Restzeit bringt
        this.uhrenAnhalten()
        await this.zugMelden(uci, null)
    }

    /**
     * Meldet einen Zug an den Server und übernimmt dessen Stand.
     *
     * Bei 409 (veraltet, beendet) und 422 (regelwidrig) gilt der Stand des
     * Servers: Die Partie wird neu geladen.
     *
     * @param {string} uci Der Zug
     * @param {number|null} denkzeit Gemessene Denkzeit des Spielers, null bei der Engine
     */
    async zugMelden(uci, denkzeit) {
        const generation = this.generation
        let antwort
        try {
            antwort = await this.anfrage(this.konfiguration.zugUrl, {
                partie: this.partie.id,
                zugnummer: this.partie.zugnummer,
                zug: uci,
                denkzeit
            })
        } catch (fehler) {
            this.fehler(fehler)
            return
        }
        if (generation !== this.generation) {
            return
        }
        if (!antwort.ok) {
            await this.partieNeuLaden()
            return
        }
        this.partie = antwort.daten.partie
        // Kam der Zug zu spät, hat der Server ihn nicht ausgeführt
        if (this.partie.zugnummer !== this.chess.history().length) {
            await this.brettAngleichen()
        }
        this.weiter()
    }

    /**
     * Baut die Stellung aus den Zügen des Servers neu auf.
     */
    async brettAngleichen() {
        this.chess = new Chess()
        this.partie.zuege.forEach(uci => this.chess.move(zugObjekt(uci)))
        await this.brett.setPosition(this.chess.fen(), true)
        this.letztenZugMarkieren()
    }

    /**
     * Gibt auf – bei gewerteten Partien über den Server.
     */
    async aufgeben() {
        if (!window.confirm(this.texte.aufgebenFrage)) {
            return
        }
        if (this.modus === "uebung") {
            this.uebungBeenden(true)
            return
        }
        await this.endeMelden(this.konfiguration.aufgebenUrl)
    }

    /**
     * Bricht eine gewertete Partie vor dem ersten eigenen Zug ab.
     */
    async abbrechen() {
        if (await this.endeMelden(this.konfiguration.abbrechenUrl)) {
            this.startZeigen()
            this.status(this.texte.abgebrochen)
        }
    }

    /**
     * Bietet Stockfish in einer gewerteten Partie Remis an.
     *
     * Stockfish bewertet die Stellung in voller Stärke (VOLLE_STAERKE) rund
     * eine Sekunde lang – bei knapper Uhr kürzer, mindestens 0,2 s – und nimmt
     * an, wenn er nicht besser steht (nimmtRemisAn()). Die Prüfung kostet den
     * Spieler Bedenkzeit: Seine Uhr läuft weiter, die Zugeingabe ist gesperrt.
     * Die Entscheidung geht an den Server, der Uhr und Rahmenregeln prüft;
     * seine Antwort gilt. Bei Annahme endet die Partie wie gewohnt, bei
     * Ablehnung geht es mit derselben Uhr weiter, und die Denkzeit des Zuges
     * zählt weiter ab seinem Beginn, nicht ab der Ablehnung.
     *
     * Liefert Stockfish kein Ergebnis, geht nichts an den Server; die Engine
     * wird beendet (der nächste Engine-Zug startet sie neu), und der Spieler
     * zieht weiter. Scheitert die Anfrage an den Server (Netzfehler), wird der
     * Stand neu geladen (partieNeuLaden()), damit Brett und Knopf nicht
     * gesperrt bleiben, während die Uhr läuft. Wechselt inzwischen die
     * Generation (Uhr abgelaufen, Seite verlassen, aufgegeben), wird nichts
     * mehr verarbeitet.
     */
    async remisAnbieten() {
        const partie = this.partie
        // Ist schon ein eigener Zug unterwegs, gilt remisErlaubt nicht mehr
        if (this.modus !== "gewertet" || partie.status !== "laeuft" || !partie.spielerAmZug || !partie.remisErlaubt
            || this.chess.history().length !== partie.zugnummer) {
            return
        }
        const generation = this.generation
        const zugBeginn = this.zugBeginn
        this.feld.knoepfe.remis.disabled = true
        this.brett.disableMoveInput()
        this.status(this.texte.remisPruefen)
        // Ohne Gutschrift gerechnet: Ein Angebot ist kein Zug und bringt keine
        const zeit = Math.min(REMIS_PRUEFZEIT_MS, zeitBudget(this.uhr.verbleibend(), 0))
        let bewertung
        try {
            const zuege = this.chess.history({verbose: true}).map(zug => zug.lan)
            bewertung = await mitZeitlimit(this.engine.bewerten(zuege, VOLLE_STAERKE, zeit), REMIS_ZEITLIMIT_MS, "Stockfish hat das Remisangebot nicht rechtzeitig geprüft.")
        } catch (fehler) {
            // Eine hängende Suche hielte sonst auch den nächsten Engine-Zug auf
            this.engine.beenden()
            if (generation !== this.generation) {
                return
            }
            console.error("Schachcomputer:", fehler)
            this.knoepfeSetzen()
            this.status(this.texte.remisFehler, "fehler")
            this.brett.enableMoveInput(this.eingabe, this.farbe)
            return
        }
        if (generation !== this.generation) {
            return
        }
        let antwort
        try {
            antwort = await this.anfrage(this.konfiguration.remisUrl, {
                partie: partie.id,
                zugnummer: partie.zugnummer,
                angenommen: nimmtRemisAn(bewertung)
            })
        } catch (fehler) {
            // Brett und Knopf sind gesperrt, die Uhr des Spielers läuft auf dem
            // Server weiter: Der Stand wird neu geladen, damit die Partie nicht
            // festsitzt. Ob das Angebot angekommen ist, weiß nur der Server.
            if (generation !== this.generation) {
                return
            }
            this.fehler(fehler)
            await this.partieNeuLaden()
            return
        }
        if (generation !== this.generation) {
            return
        }
        if (!antwort.ok) {
            await this.partieNeuLaden()
            return
        }
        this.partie = antwort.daten.partie
        this.weiter()
        if (this.partie.status === "laeuft" && this.partie.spielerAmZug) {
            this.zugBeginn = zugBeginn
            this.status(this.texte.remisAbgelehnt)
        }
    }

    /**
     * Schickt Aufgabe oder Abbruch an den Server.
     *
     * @param {string} url aufgebenUrl oder abbrechenUrl
     * @returns {Promise<boolean>} true, wenn der Server zugestimmt hat
     */
    async endeMelden(url) {
        const generation = ++this.generation
        this.fristBeenden()
        this.uhrenAnhalten()
        this.brett.disableMoveInput()
        let antwort
        try {
            antwort = await this.anfrage(url, {partie: this.partie.id})
        } catch (fehler) {
            this.fehler(fehler)
            return false
        }
        if (generation !== this.generation) {
            return false
        }
        if (!antwort.ok) {
            await this.partieNeuLaden()
            return false
        }
        this.partie = antwort.daten.partie
        this.weiter()
        return true
    }

    /**
     * Zeigt das Ende einer gewerteten Partie mit Ergebnis und Wertungsänderung.
     */
    ende() {
        this.fristBeenden()
        this.uhr.zeigen(this.partie.restzeit)
        this.engineUhrStellen()
        this.uhrHervorheben(null)
        this.brett.disableMoveInput()
        let text = this.ergebnisText(this.partie.status, this.partie.punkte, this.partie.grund)
        if (this.partie.verrechnet) {
            // Das Ergebnis („Verloren – Aufgabe") endet ohne Punkt, der Satz zur Wertung folgt als eigener Satz
            text += ". " + this.texte.wertungAenderung.replace("%s", this.partie.wertungVorher).replace("%s", this.partie.wertungNachher)
        }
        this.status(text, this.partie.punkte === 1 ? "erfolg" : this.partie.punkte === 0 ? "fehler" : "")
        this.wertungenAuffrischen()
    }

    /**
     * Beschreibt ein Ergebnis in Worten.
     *
     * @param {string} status laeuft, beendet oder abgebrochen
     * @param {number|null} punkte Punkte des Spielers
     * @param {string} grund Grund des Endes
     * @returns {string} Etwa „Gewonnen – Matt"
     */
    ergebnisText(status, punkte, grund) {
        if (status === "abgebrochen") {
            return this.texte.abgebrochen
        }
        if (punkte === null) {
            return this.texte.unbeendet
        }
        const ergebnis = punkte === 1 ? this.texte.gewonnen : punkte === 0 ? this.texte.verloren : this.texte.remis
        const begruendung = this.texte.gruende[grund]
        return begruendung ? `${ergebnis} – ${begruendung}` : ergebnis
    }

    // ------------------------------------------------------------------
    // Übungspartie

    /**
     * Nimmt in der Übungspartie den eigenen Zug samt Antwort zurück.
     */
    async zuruecknehmen() {
        if (this.modus !== "uebung" || this.partie.status !== "laeuft" || this.eigeneZuege() === 0 || this.chess.turn() !== this.partie.farbe) {
            return
        }
        this.generation++
        this.brett.disableMoveInput()
        this.chess.undo()
        this.chess.undo()
        await this.brett.setPosition(this.chess.fen(), true)
        this.letztenZugMarkieren()
        this.weiter()
    }

    /**
     * Beendet die Übungspartie, zeigt das Ergebnis und speichert sie für Mitglieder.
     *
     * @param {boolean} aufgegeben Ob der Spieler aufgegeben hat
     */
    async uebungBeenden(aufgegeben) {
        this.generation++
        this.brett.disableMoveInput()
        this.partie.status = "beendet"
        let punkte = null
        let grund = "unbeendet"
        if (this.chess.isCheckmate()) {
            grund = "matt"
            punkte = this.chess.turn() === this.partie.farbe ? 0 : 1
        } else if (this.chess.isDraw()) {
            punkte = 0.5
            grund = this.chess.isStalemate() ? "patt"
                : this.chess.isInsufficientMaterial() ? "material"
                    : this.chess.isThreefoldRepetition() ? "wiederholung" : "fuenfzig"
        } else if (aufgegeben) {
            grund = "aufgabe"
            punkte = 0
        }
        this.uebungsPunkte = punkte
        this.knoepfeSetzen()
        const text = this.ergebnisText("beendet", punkte, grund)
        this.status(text)

        if (!this.konfiguration.mitglied || this.chess.history().length === 0) {
            return
        }
        try {
            const antwort = await this.anfrage(this.konfiguration.uebungUrl, {
                stufe: this.partie.stufe,
                farbe: this.partie.farbe,
                zuege: this.chess.history({verbose: true}).map(zug => zug.lan),
                aufgegeben
            })
            if (antwort.ok) {
                this.status(`${text} ${this.texte.uebungGespeichert}`)
            }
        } catch (fehler) {
            console.error("Schachcomputer:", fehler)
        }
    }

    /**
     * Legt die PGN der Partie in die Zwischenablage.
     *
     * navigator.clipboard gibt es nur über eine sichere Verbindung (HTTPS
     * oder localhost). Fehlt es oder verweigert der Browser das Schreiben,
     * erscheint ein eigener Hinweis statt der allgemeinen Fehlermeldung, die
     * zum Neuladen rät – das hülfe hier nicht.
     */
    async pgnKopieren() {
        const heute = new Date()
        const datum = `${heute.getFullYear()}.${String(heute.getMonth() + 1).padStart(2, "0")}.${String(heute.getDate()).padStart(2, "0")}`
        const engine = this.texte.gegner.replace("%s", this.partie.stufe)
        const weiss = this.partie.farbe === "w"
        const ergebnis = this.modus === "uebung" ? pgnErgebnis(this.uebungsPunkte, this.partie.farbe) : (this.partie.ergebnis || "*")
        this.chess.setHeader("Event", this.modus === "uebung" ? this.texte.uebung : this.texte.pgnEvent)
        this.chess.setHeader("Site", window.location.host)
        this.chess.setHeader("Date", datum)
        this.chess.setHeader("White", weiss ? this.texte.spieler : engine)
        this.chess.setHeader("Black", weiss ? engine : this.texte.spieler)
        this.chess.setHeader("Result", ergebnis)
        if (!navigator.clipboard) {
            this.status(this.texte.pgnNichtKopiert, "fehler")
            return
        }
        try {
            await navigator.clipboard.writeText(this.chess.pgn())
            this.status(this.texte.pgnKopiert)
        } catch (fehler) {
            console.error("Schachcomputer:", fehler)
            this.status(this.texte.pgnNichtKopiert, "fehler")
        }
    }

    // ------------------------------------------------------------------
    // Anzeige

    /**
     * Stellt die Uhr des Computers nach dem Stand des Servers (this.partie).
     *
     * Rechnet der Computer gerade (engineUhrLaeuft), läuft sie ab der
     * gemeldeten Restzeit, sonst steht sie. Partien ohne Uhr des Computers
     * (-1) zeigen 0; ihr Rahmen ist ohnehin ausgeblendet.
     */
    engineUhrStellen() {
        const partie = this.partie
        if (partie.engineUhrLaeuft) {
            this.uhrEngine.starten(partie.restzeitEngine)
        } else {
            this.uhrEngine.zeigen(Math.max(0, partie.restzeitEngine))
        }
    }

    /**
     * Hält beide Uhren an und nimmt die Hervorhebung weg; die Anzeigen
     * bleiben stehen.
     */
    uhrenAnhalten() {
        this.uhr.anhalten()
        this.uhrEngine.anhalten()
        this.uhrHervorheben(null)
    }

    /**
     * Hebt die gerade laufende Uhr hervor.
     *
     * @param {string|null} seite „spieler", „engine" oder null für keine
     */
    uhrHervorheben(seite) {
        this.feld.rahmenSpieler.classList.toggle(UHR_AKTIV, seite === "spieler")
        this.feld.rahmenEngine.classList.toggle(UHR_AKTIV, seite === "engine")
    }

    /**
     * Blendet die Knöpfe passend zum Stand ein und aus.
     *
     * „Remis anbieten“ gibt es nur in gewerteten Partien, wenn der Spieler am
     * Zug ist und der Server es erlaubt (remisErlaubt); gesperrt ist der Knopf
     * nur, solange Stockfish ein Angebot prüft (siehe remisAnbieten()).
     */
    knoepfeSetzen() {
        const knoepfe = this.feld.knoepfe
        const laeuft = this.partie !== null && this.partie.status === "laeuft"
        const uebung = this.modus === "uebung"
        const spielerAmZug = laeuft && (uebung ? this.chess.turn() === this.partie.farbe : this.partie.spielerAmZug)
        const eigeneZuege = this.partie === null ? 0 : this.eigeneZuege()

        knoepfe.abbrechen.hidden = uebung || !laeuft || eigeneZuege > 0
        knoepfe.remis.hidden = uebung || !spielerAmZug || !this.partie.remisErlaubt
        knoepfe.remis.disabled = false
        knoepfe.aufgeben.hidden = !laeuft || (!uebung && eigeneZuege === 0)
        knoepfe.zuruecknehmen.hidden = !uebung || !laeuft
        knoepfe.zuruecknehmen.disabled = !spielerAmZug || eigeneZuege === 0
        knoepfe.beenden.hidden = !uebung || !laeuft
        knoepfe.pgn.hidden = laeuft && !uebung
        knoepfe.neu.hidden = laeuft
    }

    /**
     * @returns {number} Zahl der Züge, die der Spieler schon gemacht hat
     */
    eigeneZuege() {
        const halbzuege = this.chess.history().length
        return this.partie.farbe === "w" ? Math.ceil(halbzuege / 2) : Math.floor(halbzuege / 2)
    }

    /**
     * Markiert Ausgangs- und Zielfeld eines Zuges.
     *
     * @param {Object} zug Zug von chess.js
     */
    zugMarkieren(zug) {
        this.brett.removeMarkers(MARKER_ZUG)
        this.brett.addMarker(MARKER_ZUG, zug.from)
        this.brett.addMarker(MARKER_ZUG, zug.to)
    }

    /**
     * Markiert den letzten Zug der Partie, falls es einen gibt.
     */
    letztenZugMarkieren() {
        const verlauf = this.chess.history({verbose: true})
        this.brett.removeMarkers(MARKER_ZUG)
        if (verlauf.length) {
            this.zugMarkieren(verlauf[verlauf.length - 1])
        }
    }

    /**
     * Zeigt eine Meldung.
     *
     * @param {string} text Der Text
     * @param {string} [art] „erfolg" oder „fehler" für die Farbe
     */
    status(text, art = "") {
        this.feld.status.textContent = text
        this.feld.status.dataset.art = art
    }

    /**
     * Meldet einen Fehler in der Konsole und auf der Seite.
     *
     * @param {Error} fehler Der Fehler
     */
    fehler(fehler) {
        console.error("Schachcomputer:", fehler)
        this.status(this.texte.fehler, "fehler")
    }
}

document.querySelectorAll(".schachcomputer[data-konfiguration]").forEach(element => new Schachcomputer(element))
