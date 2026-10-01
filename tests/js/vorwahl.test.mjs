/*
 * Tests für vorwahl.js. Aufruf: node --test tests/js/
 */

import {test} from "node:test"
import assert from "node:assert/strict"
import {SCHLUESSEL, speicherHolen, vorwahlLesen, vorwahlSchreiben} from "../../src/Resources/public/vorwahl.js"

/**
 * Einfacher Ersatz für den sessionStorage.
 *
 * @param {Object<string, string>} start Anfangsinhalt
 * @returns {{inhalt: Object<string, string>, getItem: Function, setItem: Function}}
 */
function speicher(start = {}) {
    const inhalt = {...start}
    return {
        inhalt,
        getItem: schluessel => (schluessel in inhalt ? inhalt[schluessel] : null),
        setItem: (schluessel, wert) => {
            inhalt[schluessel] = String(wert)
        }
    }
}

test("ohne Eintrag ist nichts vorgewählt", () => {
    assert.deepEqual(vorwahlLesen(speicher()), {bedenkzeit: null, farbe: null})
    assert.deepEqual(vorwahlLesen(null), {bedenkzeit: null, farbe: null})
})

test("geschriebene Wahl kommt beim Lesen wieder heraus", () => {
    const s = speicher()
    vorwahlSchreiben(s, "7", "b")
    assert.deepEqual(vorwahlLesen(s), {bedenkzeit: "7", farbe: "b"})
    vorwahlSchreiben(s, "12", "zufall")
    assert.deepEqual(vorwahlLesen(s), {bedenkzeit: "12", farbe: "zufall"})
})

test("eine leere Bedenkzeit oder unbekannte Farbe überschreibt nichts Gutes", () => {
    const s = speicher()
    vorwahlSchreiben(s, "7", "w")
    vorwahlSchreiben(s, "", "schwarz")
    assert.deepEqual(vorwahlLesen(s), {bedenkzeit: "7", farbe: "w"})
})

test("Reste im Speicher werden wie „nichts gemerkt“ behandelt", () => {
    assert.deepEqual(vorwahlLesen(speicher({[SCHLUESSEL]: "kein json"})), {bedenkzeit: null, farbe: null})
    assert.deepEqual(vorwahlLesen(speicher({[SCHLUESSEL]: "42"})), {bedenkzeit: null, farbe: null})
    assert.deepEqual(vorwahlLesen(speicher({[SCHLUESSEL]: JSON.stringify({bedenkzeit: "<x>", farbe: "rot"})})), {bedenkzeit: null, farbe: null})
})

test("ein gesperrter oder voller Speicher stört nicht", () => {
    const kaputt = {
        getItem: () => {
            throw new Error("gesperrt")
        },
        setItem: () => {
            throw new Error("voll")
        }
    }
    assert.deepEqual(vorwahlLesen(kaputt), {bedenkzeit: null, farbe: null})
    assert.doesNotThrow(() => vorwahlSchreiben(kaputt, "7", "w"))
    assert.doesNotThrow(() => vorwahlSchreiben(null, "7", "w"))
})

test("speicherHolen liefert einen Speicher oder null, nie eine Ausnahme", () => {
    // Neuere Node-Fassungen kennen einen (leeren) sessionStorage, ältere keinen
    const gefunden = speicherHolen()
    assert.ok(gefunden === null || typeof gefunden.getItem === "function")

    // Ein Zugriff, der eine Ausnahme auslöst (gesperrte Website-Daten)
    const bisher = Object.getOwnPropertyDescriptor(globalThis, "sessionStorage")
    Object.defineProperty(globalThis, "sessionStorage", {configurable: true, get: () => {
        throw new Error("gesperrt")
    }})
    try {
        assert.equal(speicherHolen(), null)
    } finally {
        if (bisher) {
            Object.defineProperty(globalThis, "sessionStorage", bisher)
        } else {
            delete globalThis.sessionStorage
        }
    }
})
