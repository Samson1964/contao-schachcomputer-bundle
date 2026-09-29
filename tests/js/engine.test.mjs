/*
 * Tests für engine.js. Aufruf: node --test tests/js/
 */

import {test} from "node:test"
import assert from "node:assert/strict"
import {optionsBefehle, goBefehl, rechenzeit, zufallsZug, bestmove, Engine} from "../../src/Resources/public/engine.js"

const GEEICHT = {stufe: 1500, uciElo: 1500, skill: null, tiefe: null, zufall: 0, zeitMin: 1000, zeitMax: 2000}
const SCHWACH = {stufe: 600, uciElo: null, skill: 0, tiefe: 1, zufall: 0.4, zeitMin: 1000, zeitMax: 2000}

test("geeichte Stufen nutzen UCI_Elo und feste Rechenzeit", () => {
    assert.deepEqual(optionsBefehle(GEEICHT), ["setoption name UCI_LimitStrength value true", "setoption name UCI_Elo value 1500"])
    assert.equal(goBefehl(GEEICHT, 1234), "go movetime 1234")
})

test("nachgebaute Stufen nutzen Skill Level 0 und feste Tiefe", () => {
    assert.deepEqual(optionsBefehle(SCHWACH), ["setoption name UCI_LimitStrength value false", "setoption name Skill Level value 0"])
    assert.equal(goBefehl(SCHWACH, 1234), "go depth 1")
})

test("die Rechenzeit liegt zwischen zeitMin und zeitMax", () => {
    assert.equal(rechenzeit(GEEICHT, () => 0), 1000)
    assert.equal(rechenzeit(GEEICHT, () => 0.999999), 2000)
})

test("Zufallszüge nur mit Anteil und nur unterhalb der Schwelle", () => {
    const legale = ["e2e4", "d2d4", "g1f3"]
    assert.equal(zufallsZug(GEEICHT, legale, () => 0), null)
    assert.equal(zufallsZug(SCHWACH, legale, () => 0.5), null)
    const werte = [0.1, 0.7]
    assert.equal(zufallsZug(SCHWACH, legale, () => werte.shift()), "g1f3")
    assert.equal(zufallsZug(SCHWACH, [], () => 0), null)
})

test("bestmove liest den Zug samt Umwandlung", () => {
    assert.equal(bestmove("bestmove e2e4 ponder e7e5"), "e2e4")
    assert.equal(bestmove("bestmove a7a8q"), "a7a8q")
    assert.equal(bestmove("bestmove (none)"), null)
    assert.equal(bestmove("info depth 1"), null)
})

test("Engine spricht UCI mit dem Worker und liefert den Zug", async () => {
    const gesendet = []
    globalThis.Worker = class {
        constructor(url) {
            this.url = url
        }
        postMessage(befehl) {
            gesendet.push(befehl)
            const antworten = {uci: "id name Test\nuciok", isready: "readyok"}
            const antwort = befehl.startsWith("go") ? "info depth 1\nbestmove g1f3 ponder g8f6" : antworten[befehl]
            if (antwort) {
                setTimeout(() => this.onmessage({data: antwort}), 0)
            }
        }
        terminate() {
        }
    }

    const engine = new Engine("stockfish.js")
    const zug = await engine.zug(["e2e4", "e7e5"], GEEICHT, 1500)

    assert.equal(zug, "g1f3")
    assert.deepEqual(gesendet, [
        "uci", "isready",
        "setoption name UCI_LimitStrength value true", "setoption name UCI_Elo value 1500", "isready",
        "position startpos moves e2e4 e7e5", "go movetime 1500"
    ])
    engine.beenden()
    delete globalThis.Worker
})

test("zwei Suchen kurz nacheinander laufen nacheinander, nicht überlappend", async () => {
    const gesendet = []
    globalThis.Worker = class {
        postMessage(befehl) {
            gesendet.push(befehl)
            const antworten = {uci: "uciok", isready: "readyok"}
            const antwort = befehl.startsWith("go") ? `bestmove ${gesendet.filter(b => b.startsWith("go")).length === 1 ? "e2e4" : "d2d4"}` : antworten[befehl]
            if (antwort) {
                setTimeout(() => this.onmessage({data: antwort}), 5)
            }
        }
        terminate() {
        }
    }

    const engine = new Engine("stockfish.js")
    const [erster, zweiter] = await Promise.all([engine.zug([], GEEICHT, 100), engine.zug([], GEEICHT, 100)])

    assert.equal(erster, "e2e4")
    assert.equal(zweiter, "d2d4")
    const gos = gesendet.map((befehl, index) => befehl.startsWith("go") ? index : -1).filter(index => index >= 0)
    assert.equal(gos.length, 2)
    assert.ok(gesendet.slice(gos[0] + 1, gos[1]).includes("isready"), "die zweite Suche beginnt erst nach der ersten")
    engine.beenden()
    delete globalThis.Worker
})
