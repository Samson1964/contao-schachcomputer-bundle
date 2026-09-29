/*
 * Tests für engine.js. Aufruf: node --test tests/js/
 */

import {test} from "node:test"
import assert from "node:assert/strict"
import {optionsBefehle, goBefehl, rechenzeit, zufallsZug, bestmove, mitZeitlimit, Engine} from "../../src/Resources/public/engine.js"

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

/**
 * Baut einen nachgebildeten Worker, der uci und isready beantwortet und auf
 * „go" das tut, was aufGo vorgibt.
 *
 * @param {function(Object): void} aufGo Bekommt den Worker, wenn „go" ankommt
 * @param {Object} protokoll Sammelt erzeugte und beendete Worker
 * @returns {Function} Die Klasse für globalThis.Worker
 */
function nachgebildeterWorker(aufGo, protokoll) {
    return class {
        constructor() {
            protokoll.erzeugt.push(this)
        }
        postMessage(befehl) {
            if (befehl.startsWith("go")) {
                setTimeout(() => aufGo(this), 0)
                return
            }
            const antwort = {uci: "uciok", isready: "readyok"}[befehl]
            if (antwort) {
                setTimeout(() => this.onmessage({data: antwort}), 0)
            }
        }
        terminate() {
            protokoll.beendet.push(this)
        }
    }
}

test("ein Fehler im Worker weist die wartende Suche ab", async () => {
    const protokoll = {erzeugt: [], beendet: []}
    globalThis.Worker = nachgebildeterWorker(worker => worker.onerror({message: "WebAssembly fehlt"}), protokoll)

    const engine = new Engine("stockfish.js")
    await assert.rejects(engine.zug([], GEEICHT, 100), /WebAssembly fehlt/)

    assert.equal(protokoll.beendet.length, 1, "der ausgefallene Worker wird beendet")
    assert.equal(engine.worker, null)
    assert.equal(engine.bereit, null)
    delete globalThis.Worker
})

test("beenden() weist eine laufende Suche ab, statt sie hängen zu lassen", async () => {
    const protokoll = {erzeugt: [], beendet: []}
    let goAngekommen
    const go = new Promise(erfuellen => { goAngekommen = erfuellen })
    // Antwortet nie auf „go": ohne Abweisung wartete die Suche ewig
    globalThis.Worker = nachgebildeterWorker(() => goAngekommen(), protokoll)

    const engine = new Engine("stockfish.js")
    const suche = engine.zug([], GEEICHT, 100)
    await go
    engine.beenden()

    await assert.rejects(suche)
    assert.equal(protokoll.beendet.length, 1)
    delete globalThis.Worker
})

test("nach einem Fehler startet der nächste Zug einen neuen Worker", async () => {
    const protokoll = {erzeugt: [], beendet: []}
    globalThis.Worker = nachgebildeterWorker(worker => {
        if (protokoll.erzeugt.length === 1) {
            worker.onerror({message: "abgestürzt"})
        } else {
            worker.onmessage({data: "bestmove e2e4"})
        }
    }, protokoll)

    const engine = new Engine("stockfish.js")
    await assert.rejects(engine.zug([], GEEICHT, 100))
    const zug = await engine.zug([], GEEICHT, 100)

    assert.equal(zug, "e2e4")
    assert.equal(protokoll.erzeugt.length, 2)
    engine.beenden()
    delete globalThis.Worker
})

test("starten() mit Zeitlimit weist ab, wenn die Engine nicht bereit wird", async () => {
    const protokoll = {erzeugt: [], beendet: []}
    // Meldet sich nie: so sieht eine .wasm aus, die über Mobilfunk nicht ankommt
    globalThis.Worker = class {
        constructor() {
            protokoll.erzeugt.push(this)
        }
        postMessage() {
        }
        terminate() {
            protokoll.beendet.push(this)
        }
    }

    const engine = new Engine("stockfish.js")
    await assert.rejects(engine.starten(20), /nicht rechtzeitig/)
    engine.beenden()
    delete globalThis.Worker
})

test("starten() erfüllt, sobald die Engine readyok gemeldet hat", async () => {
    const protokoll = {erzeugt: [], beendet: []}
    globalThis.Worker = nachgebildeterWorker(() => undefined, protokoll)

    const engine = new Engine("stockfish.js")
    await engine.starten(1000)
    await engine.starten(1000)

    assert.equal(protokoll.erzeugt.length, 1, "ein bereiter Worker wird weiterverwendet")
    engine.beenden()
    delete globalThis.Worker
})

test("mitZeitlimit reicht das Ergebnis durch oder weist nach Ablauf ab", async () => {
    assert.equal(await mitZeitlimit(Promise.resolve("e2e4"), 50, "zu spät"), "e2e4")
    await assert.rejects(mitZeitlimit(new Promise(() => undefined), 10, "zu spät"), /zu spät/)
})
