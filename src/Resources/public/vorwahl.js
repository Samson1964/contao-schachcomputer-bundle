/*
 * Schachcomputer-Bundle: merkt sich Bedenkzeit und Farbe des Startformulars.
 *
 * Gespeichert wird im sessionStorage des Browsers, also für die Dauer des
 * Besuchs und je Tab: Wer eine Partie beendet oder die Seite neu lädt, findet
 * seine letzte Wahl wieder. Nichts davon geht an den Server. Das Skript hängt
 * weder an der Seite noch an der Engine und lässt sich mit Node testen
 * (tests/js/vorwahl.test.mjs).
 *
 * @license LGPL-3.0-or-later
 */

/** Schlüssel im Speicher. */
export const SCHLUESSEL = "schachcomputer.vorwahl"

/** Zulässige Farbwahlen des Startformulars. */
const FARBEN = ["w", "b", "zufall"]

/**
 * Liefert den Speicher des Browsers, falls er sich benutzen lässt.
 *
 * Schon der Zugriff auf sessionStorage kann eine Ausnahme auslösen (Browser
 * mit gesperrten Website-Daten, eingebettete Seiten); dann gibt es keine
 * Vorwahl, und das Formular verhält sich wie bisher.
 *
 * @returns {Storage|null} Der sessionStorage, oder null
 */
export function speicherHolen() {
    try {
        return globalThis.sessionStorage ?? null
    } catch (fehler) {
        return null
    }
}

/**
 * Liest die zuletzt gemerkte Wahl.
 *
 * Unlesbare, fremde oder veraltete Einträge werden wie „nichts gemerkt“
 * behandelt, damit das Formular nie an einem Rest im Speicher scheitert.
 *
 * @param {Storage|null} speicher Speicher mit getItem(), oder null
 * @returns {{bedenkzeit: string|null, farbe: string|null}} Kennung der
 *     Bedenkzeit (als Text, wie im Wert der Auswahl) und Farbe („w“, „b“ oder
 *     „zufall“); je null, wenn nichts oder nichts Brauchbares gemerkt ist
 */
export function vorwahlLesen(speicher) {
    const leer = {bedenkzeit: null, farbe: null}
    if (!speicher) {
        return leer
    }
    try {
        const daten = JSON.parse(speicher.getItem(SCHLUESSEL) ?? "null")
        if (daten === null || typeof daten !== "object") {
            return leer
        }
        const bedenkzeit = /^[0-9]+$/.test(String(daten.bedenkzeit ?? "")) ? String(daten.bedenkzeit) : null
        const farbe = FARBEN.includes(daten.farbe) ? daten.farbe : null
        return {bedenkzeit, farbe}
    } catch (fehler) {
        return leer
    }
}

/**
 * Merkt sich die aktuelle Wahl.
 *
 * Eine leere Bedenkzeit (die Auswahl hat gar keine Einträge) oder eine
 * unbekannte Farbe überschreibt nichts Gemerktes mit Unbrauchbarem: Von
 * beiden wird nur der gültige Teil übernommen, der andere bleibt, wie er war.
 * Ein voller oder gesperrter Speicher wird still hingenommen.
 *
 * @param {Storage|null} speicher    Speicher mit getItem() und setItem(), oder null
 * @param {string}       bedenkzeit  Wert der Bedenkzeit-Auswahl, leer wenn keine gewählt
 * @param {string}       farbe       „w“, „b“ oder „zufall“
 * @returns {void}
 */
export function vorwahlSchreiben(speicher, bedenkzeit, farbe) {
    if (!speicher) {
        return
    }
    const bisher = vorwahlLesen(speicher)
    const neu = {
        bedenkzeit: /^[0-9]+$/.test(String(bedenkzeit ?? "")) ? String(bedenkzeit) : bisher.bedenkzeit,
        farbe: FARBEN.includes(farbe) ? farbe : bisher.farbe
    }
    try {
        speicher.setItem(SCHLUESSEL, JSON.stringify(neu))
    } catch (fehler) {
        // Kein Speicher, keine Vorwahl: kein Grund, das Spiel zu stören
    }
}
