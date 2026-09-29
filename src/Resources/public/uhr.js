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
    }

    /**
     * Zeigt eine Restzeit an, ohne die Uhr laufen zu lassen.
     *
     * @param {number} restzeit Restzeit in ms
     */
    zeigen(restzeit) {
        this.anhalten()
        this.restzeit = restzeit
        this.anzeige.textContent = formatieren(restzeit)
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
     * Aktualisiert die Anzeige und meldet den Ablauf.
     */
    ticken() {
        const rest = this.verbleibend()
        this.anzeige.textContent = formatieren(rest)
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
