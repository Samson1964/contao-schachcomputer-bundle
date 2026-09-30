/*
 * Anzeige der Schachuhr für das Schachcomputer-Bundle.
 *
 * Die Uhr zeigt nur an; maßgeblich ist die Uhr des Servers. Läuft sie im
 * Browser ab, fragt spielen.js den Server nach dem Stand.
 *
 * @license LGPL-3.0-or-later
 */

/**
 * Formatiert eine Restzeit: ab 10 Sekunden „m:ss", darunter „0:0s.z" mit
 * Zehnteln.
 *
 * @param {number} ms Restzeit in Millisekunden; negative Werte zählen als 0
 * @returns {string} Die Anzeige, etwa „3:00", „0:59" oder „0:09.4"
 */
export function formatieren(ms) {
    const rest = Math.max(0, Math.floor(ms))
    if (rest < 10000) {
        const zehntel = Math.floor(rest / 100)
        return `0:0${Math.floor(zehntel / 10)}.${zehntel % 10}`
    }
    const sekunden = Math.floor(rest / 1000)
    return `${Math.floor(sekunden / 60)}:${String(sekunden % 60).padStart(2, "0")}`
}

/**
 * Ermittelt die Restzeit, unter der eine Uhr rot gefärbt wird.
 *
 * Normal färben sich die Ziffern, sobald die Uhr 0:59 zeigt. Bei sehr kurzen
 * Bedenkzeiten (Grundzeit höchstens eine Minute, gleich welche Gutschrift)
 * wäre die Uhr sonst von der ersten Sekunde an rot; dort gilt erst 0:20.
 * Die Anzeige rundet Sekunden ab, deshalb liegt die Grenze eine Sekunde über
 * dem gemeinten Wert: Bei 20 999 ms steht 0:20 auf der Uhr, bei 21 000 ms
 * noch 0:21.
 *
 * @param {number} minuten Grundzeit der Partie in Minuten
 * @returns {number} Grenze in ms; unterhalb davon ist die Uhr knapp
 */
export function warngrenze(minuten) {
    return minuten <= 1 ? 21000 : 60000
}

/**
 * Eine ablaufende Uhr mit Anzeige in einem Element.
 */
export class Uhr {

    /**
     * @param {{textContent: string}} anzeige Element für die Anzeige
     * @param {function(): void} aufAblauf Wird einmal gerufen, wenn die Zeit abläuft
     * @param {function(): number} [jetzt] Zeitquelle in ms, für Tests ersetzbar
     */
    constructor(anzeige, aufAblauf, jetzt = () => performance.now()) {
        this.anzeige = anzeige
        this.aufAblauf = aufAblauf
        this.jetzt = jetzt
        this.restzeit = 0
        this.start = null
        this.takt = null
        this.warnung = null
    }

    /**
     * Legt fest, ab welcher Restzeit die Uhr als knapp gilt und welches
     * Element dann eine Klasse bekommt.
     *
     * Die Klasse wird bei jeder Anzeige neu gesetzt oder entfernt (zeigen() und
     * ticken()), also auch bei einer stehenden Uhr. Der gerade gezeigte Stand
     * wird sofort bewertet. Ohne Aufruf dieser Methode fasst die Uhr kein
     * Element an. Wechselt das Element bei einem späteren Aufruf, bleibt eine
     * Klasse am alten Element stehen; spielen.js übergibt immer dasselbe.
     *
     * @param {number|null} grenzeMs Knapp ist eine Restzeit unterhalb dieser Grenze (ms);
     *                               null schaltet die Warnung aus und nimmt die Klasse weg,
     *                               etwa für eine Uhr ohne Wert
     * @param {{classList: {toggle: function(string, boolean): boolean}}} element Element, das die Klasse trägt
     * @param {string} klasse Name der Klasse
     */
    warnungSetzen(grenzeMs, element, klasse) {
        this.warnung = {grenzeMs, element, klasse}
        this.warnungPruefen(this.verbleibend())
    }

    /**
     * Setzt oder entfernt die Warnklasse passend zu einer Restzeit.
     *
     * Ohne vorheriges warnungSetzen() geschieht nichts. Eine Restzeit genau auf
     * der Grenze ist noch nicht knapp.
     *
     * @param {number} restzeit Die angezeigte Restzeit in ms
     */
    warnungPruefen(restzeit) {
        if (this.warnung === null) {
            return
        }
        const {grenzeMs, element, klasse} = this.warnung
        element.classList.toggle(klasse, grenzeMs !== null && restzeit < grenzeMs)
    }

    /**
     * Zeigt eine Restzeit an, ohne die Uhr laufen zu lassen.
     *
     * Färbt die Uhr, wenn die Restzeit unter der Warngrenze liegt (siehe
     * warnungSetzen()).
     *
     * @param {number} restzeit Restzeit in ms
     */
    zeigen(restzeit) {
        this.anhalten()
        this.restzeit = restzeit
        this.anzeige.textContent = formatieren(restzeit)
        this.warnungPruefen(restzeit)
    }

    /**
     * Lässt die Uhr ab der angegebenen Restzeit laufen.
     *
     * @param {number} restzeit Restzeit in ms
     */
    starten(restzeit) {
        this.zeigen(restzeit)
        this.start = this.jetzt()
        this.takt = setInterval(() => this.ticken(), 100)
    }

    /**
     * Aktualisiert die Anzeige samt Warnfarbe und meldet den Ablauf.
     */
    ticken() {
        const rest = this.verbleibend()
        this.anzeige.textContent = formatieren(rest)
        this.warnungPruefen(rest)
        if (rest <= 0 && this.start !== null) {
            this.anhalten()
            this.restzeit = 0
            this.aufAblauf()
        }
    }

    /**
     * @returns {number} Die verbleibende Zeit in ms
     */
    verbleibend() {
        return this.start === null ? this.restzeit : Math.max(0, this.restzeit - (this.jetzt() - this.start))
    }

    /**
     * Hält die Uhr an; die Anzeige bleibt stehen.
     */
    anhalten() {
        if (this.takt !== null) {
            clearInterval(this.takt)
        }
        if (this.start !== null) {
            this.restzeit = this.verbleibend()
        }
        this.takt = null
        this.start = null
    }
}
