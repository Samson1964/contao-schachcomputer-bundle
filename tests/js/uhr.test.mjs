/*
 * Tests für uhr.js. Aufruf: node --test tests/js/
 */

import {test} from "node:test"
import assert from "node:assert/strict"
import {formatieren, Uhr} from "../../src/Resources/public/uhr.js"

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
