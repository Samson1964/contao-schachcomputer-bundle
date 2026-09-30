/*
 * Tests für uhr.js. Aufruf: node --test tests/js/
 */

import {test} from "node:test"
import assert from "node:assert/strict"
import {formatieren, warngrenze, Uhr} from "../../src/Resources/public/uhr.js"

test("Anzeige in Minuten und Sekunden, unter 10 s mit Zehnteln", () => {
    assert.equal(formatieren(180000), "3:00")
    assert.equal(formatieren(59999), "0:59")
    assert.equal(formatieren(10000), "0:10")
    assert.equal(formatieren(9999), "0:09.9")
    assert.equal(formatieren(400), "0:00.4")
    assert.equal(formatieren(-5), "0:00.0")
    assert.equal(formatieren(10800000), "180:00")
})

test("die Uhr läuft ab, meldet den Ablauf einmal und merkt sich den Rest beim Anhalten", () => {
    let zeit = 1000
    let abgelaufen = 0
    const anzeige = {textContent: ""}
    const uhr = new Uhr(anzeige, () => abgelaufen++, () => zeit)

    uhr.starten(3000)
    assert.equal(anzeige.textContent, "0:03.0")

    zeit += 1500
    uhr.ticken()
    assert.equal(anzeige.textContent, "0:01.5")

    uhr.anhalten()
    zeit += 5000
    assert.equal(uhr.verbleibend(), 1500)

    uhr.starten(1500)
    zeit += 2000
    uhr.ticken()
    uhr.ticken()
    assert.equal(abgelaufen, 1)
    assert.equal(anzeige.textContent, "0:00.0")
})

/**
 * Ersatz für ein Element, der nur die Klassenliste nachbildet, die die Uhr braucht.
 */
function rahmen() {
    const klassen = new Set()
    return {
        klassen,
        classList: {
            toggle(name, an) {
                if (an) {
                    klassen.add(name)
                } else {
                    klassen.delete(name)
                }
                return an
            }
        }
    }
}

test("Warngrenze: bis 1 Minute Grundzeit erst ab 0:20, sonst ab 0:59", () => {
    assert.equal(warngrenze(1), 21000)
    assert.equal(warngrenze(0.5), 21000)
    assert.equal(warngrenze(2), 60000)
    assert.equal(warngrenze(3), 60000)
    assert.equal(warngrenze(180), 60000)
})

test("eine stehende Uhr färbt sich genau ab 0:59, nicht schon bei 1:00", () => {
    const anzeige = {textContent: ""}
    const feld = rahmen()
    const uhr = new Uhr(anzeige, () => {}, () => 0)
    uhr.warnungSetzen(60000, feld, "knapp")

    uhr.zeigen(60000)
    assert.equal(anzeige.textContent, "1:00")
    assert.equal(feld.klassen.has("knapp"), false)

    uhr.zeigen(59999)
    assert.equal(anzeige.textContent, "0:59")
    assert.equal(feld.klassen.has("knapp"), true)

    uhr.zeigen(120000)
    assert.equal(feld.klassen.has("knapp"), false, "nach einer Gutschrift wieder normal")
})

test("bei kurzer Bedenkzeit färbt sich die Uhr erst ab 0:20", () => {
    const anzeige = {textContent: ""}
    const feld = rahmen()
    const uhr = new Uhr(anzeige, () => {}, () => 0)
    uhr.warnungSetzen(warngrenze(1), feld, "knapp")

    uhr.zeigen(21000)
    assert.equal(anzeige.textContent, "0:21")
    assert.equal(feld.klassen.has("knapp"), false)

    uhr.zeigen(20999)
    assert.equal(anzeige.textContent, "0:20")
    assert.equal(feld.klassen.has("knapp"), true)
})

test("eine laufende Uhr färbt sich beim Unterschreiten der Grenze und bleibt rot", () => {
    let zeit = 1000
    const anzeige = {textContent: ""}
    const feld = rahmen()
    const uhr = new Uhr(anzeige, () => {}, () => zeit)
    uhr.warnungSetzen(60000, feld, "knapp")

    uhr.starten(65000)
    assert.equal(feld.klassen.has("knapp"), false)

    zeit += 5000
    uhr.ticken()
    assert.equal(anzeige.textContent, "1:00")
    assert.equal(feld.klassen.has("knapp"), false, "genau 60 000 ms sind noch nicht rot")

    zeit += 1
    uhr.ticken()
    assert.equal(anzeige.textContent, "0:59")
    assert.equal(feld.klassen.has("knapp"), true)

    zeit += 30000
    uhr.ticken()
    assert.equal(feld.klassen.has("knapp"), true)

    uhr.anhalten()
})

test("die abgelaufene Uhr bleibt rot", () => {
    let zeit = 0
    let abgelaufen = 0
    const feld = rahmen()
    const uhr = new Uhr({textContent: ""}, () => abgelaufen++, () => zeit)
    uhr.warnungSetzen(21000, feld, "knapp")

    uhr.starten(30000)
    zeit += 31000
    uhr.ticken()

    assert.equal(abgelaufen, 1)
    assert.equal(feld.klassen.has("knapp"), true)
})

test("ohne Grenze oder ohne Warnung bleibt die Uhr ungefärbt", () => {
    const feld = rahmen()
    const uhr = new Uhr({textContent: ""}, () => {}, () => 0)

    uhr.zeigen(1000)
    assert.equal(feld.klassen.size, 0, "ohne warnungSetzen() wird nichts angefasst")

    uhr.warnungSetzen(60000, feld, "knapp")
    assert.equal(feld.klassen.has("knapp"), true, "der gezeigte Stand wird sofort bewertet")

    uhr.warnungSetzen(null, feld, "knapp")
    assert.equal(feld.klassen.has("knapp"), false, "ohne Grenze (Uhr ohne Wert) ist die Warnung aus")
    uhr.zeigen(0)
    assert.equal(feld.klassen.has("knapp"), false)
})
