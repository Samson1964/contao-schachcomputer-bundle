/*
 * Tests für engine.js. Aufruf: node --test tests/js/
 */

import {test} from "node:test"
import assert from "node:assert/strict"
import {optionsBefehle, goBefehl, rechenzeit, zeitBudget, zufallsZug, bestmove, bewertungAus, nimmtRemisAn, VOLLE_STAERKE, mitZeitlimit, wasmUrlVon, wasmAdresse, Engine} from "../../src/Resources/public/engine.js"

const GEEICHT = {stufe: 1500, uciElo: 1500, skill: null, tiefe: null, zufall: 0, zeitMin: 1000, zeitMax: 2000}
const SCHWACH = {stufe: 600, uciElo: null, skill: 0, tiefe: 1, zufall: 0.4, zeitMin: 1000, zeitMax: 2000}

/**
 * Baut eine Engine, die die .wasm nicht selbst lädt: Die nachgebildeten
 * Worker brauchen keine, und fetch() auf eine relative Adresse gibt es in Node
 * nicht.
 *
 * @returns {Engine} Engine mit der Adresse „stockfish.js"
 */
function neueEngine() {
    return new Engine("stockfish.js", {wasmLaden: async () => null})
}

/**
 * Bildet fetch() für wasmAdresse() nach.
 *
 * @param {string} typ Content-Type, den der Server für die .wasm meldet ("" für keinen)
 * @param {Object} protokoll Sammelt die Abrufe als „METHODE adresse"
 * @param {number} [status] HTTP-Status der Antworten
 * @returns {function(string, Object=): Promise<Response>} Ersatz für fetch()
 */
function nachgebildetesFetch(typ, protokoll, status = 200) {
    return async (adresse, optionen = {}) => {
        protokoll.push(`${optionen.method ?? "GET"} ${adresse}`)
        const kopf = typ ? {"content-type": typ} : {}
        return new Response(optionen.method === "HEAD" ? null : new Uint8Array([0, 97, 115, 109]), {status, headers: kopf})
    }
}

test("die Adresse der .wasm folgt aus der des Skripts, samt Versionsangabe", () => {
    assert.equal(wasmUrlVon("/bundles/x/stockfish-19-lite-single.js"), "/bundles/x/stockfish-19-lite-single.wasm")
    assert.equal(wasmUrlVon("/bundles/x/stockfish.js?v=abc#hash"), "/bundles/x/stockfish.wasm?v=abc")
})

test("liefert der Server application/wasm, bleibt es bei der Adresse des Servers", async () => {
    const protokoll = []
    const adresse = await wasmAdresse("https://beispiel.test/sf.wasm", nachgebildetesFetch("application/wasm", protokoll))

    assert.equal(adresse, "https://beispiel.test/sf.wasm")
    assert.deepEqual(protokoll, ["HEAD https://beispiel.test/sf.wasm"], "nur der Kopf wird abgefragt")
})

test("fehlt der Typ (nginx ohne Eintrag), wird die .wasm als Blob mit richtigem Typ bereitgestellt", async () => {
    const protokoll = []
    const adresse = await wasmAdresse("https://beispiel.test/sf.wasm", nachgebildetesFetch("", protokoll))

    assert.match(adresse, /^blob:/)
    assert.deepEqual(protokoll, ["HEAD https://beispiel.test/sf.wasm", "GET https://beispiel.test/sf.wasm"])
    const blob = await (await fetch(adresse)).blob()
    assert.equal(blob.type, "application/wasm")
    assert.equal(blob.size, 4)
    URL.revokeObjectURL(adresse)
})

test("ist die .wasm nicht abrufbar, weist wasmAdresse() ab", async () => {
    await assert.rejects(wasmAdresse("https://beispiel.test/sf.wasm", nachgebildetesFetch("", [], 404)), /HTTP 404/)
})

test("die Engine reicht die Adresse der .wasm im Hash an den Worker", async () => {
    const adressen = []
    globalThis.Worker = class {
        constructor(url) {
            adressen.push(url)
        }
        postMessage(befehl) {
            const antwort = {uci: "uciok", isready: "readyok"}[befehl]
            if (antwort) {
                setTimeout(() => this.onmessage({data: antwort}), 0)
            }
        }
        terminate() {
        }
    }

    const geladen = []
    const engine = new Engine("/bundles/x/stockfish.js?v=1", {wasmLaden: async adresse => { geladen.push(adresse); return "blob:https://beispiel.test/1234" }})
    await engine.starten(1000)

    assert.deepEqual(geladen, ["/bundles/x/stockfish.wasm?v=1"])
    assert.deepEqual(adressen, ["/bundles/x/stockfish.js?v=1#" + encodeURIComponent("blob:https://beispiel.test/1234")])
    engine.beenden()
    delete globalThis.Worker
})

test("wird die Engine beendet, während die .wasm lädt, entsteht kein Worker", async () => {
    const protokoll = {erzeugt: [], beendet: []}
    globalThis.Worker = nachgebildeterWorker(() => undefined, protokoll)
    let wasmFertig
    const engine = new Engine("stockfish.js", {wasmLaden: () => new Promise(erfuellen => { wasmFertig = erfuellen })})

    const start = engine.starten()
    engine.beenden()
    wasmFertig("blob:x")

    await assert.rejects(start, /beendet/)
    assert.equal(protokoll.erzeugt.length, 0)
    assert.equal(engine.bereit, null)
    delete globalThis.Worker
})

test("scheitert das Laden der .wasm, startet der nächste Aufruf neu", async () => {
    const protokoll = {erzeugt: [], beendet: []}
    globalThis.Worker = nachgebildeterWorker(() => undefined, protokoll)
    let versuche = 0
    const engine = new Engine("stockfish.js", {wasmLaden: async () => {
        if (++versuche === 1) {
            throw new Error("Netz weg")
        }
        return null
    }})

    await assert.rejects(engine.starten(), /Netz weg/)
    await engine.starten(1000)

    assert.equal(versuche, 2)
    assert.equal(protokoll.erzeugt.length, 1)
    engine.beenden()
    delete globalThis.Worker
})

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

test("zeitBudget: bei viel Zeit ein Dreißigstel der Restzeit ohne eine Sekunde Reserve", () => {
    assert.equal(zeitBudget(181000, 0), 6000)
    assert.equal(zeitBudget(20000, 0), 633, "ganze Millisekunden für go movetime")
})

test("zeitBudget: die halbe Gutschrift kommt dazu", () => {
    assert.equal(zeitBudget(31000, 2000), 2000)
    assert.equal(zeitBudget(4000, 1000), 600)
})

test("zeitBudget: bei knapper Uhr wenig Zeit, aber nie unter 200 ms", () => {
    assert.equal(zeitBudget(10000, 0), 300)
    assert.equal(zeitBudget(7000, 0), 200)
    assert.equal(zeitBudget(2000, 0), 200)
    assert.equal(zeitBudget(500, 0), 200)
    assert.equal(zeitBudget(0, 0), 200)
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

test("bewertungAus liest die letzte Bewertung, in Centipawns oder als Matt", () => {
    assert.equal(bewertungAus([]), null)
    assert.equal(bewertungAus(["info string NNUE evaluation using nn-37f18f62d772.nnue", "info depth 1 seldepth 1 nodes 20"]), null)
    assert.deepEqual(bewertungAus([
        "info depth 10 seldepth 14 multipv 1 score cp 35 nodes 12000 nps 400000 pv e2e4 e7e5",
        "info depth 11 seldepth 15 multipv 1 score cp -12 upperbound nodes 15000 pv e2e4",
        "info depth 12 seldepth 16 multipv 1 score cp 28 nodes 20000 pv d2d4 d7d5"
    ]), {cp: 28})
    assert.deepEqual(bewertungAus(["info depth 5 score cp 10 pv e2e4", "info depth 20 score mate -3 pv h7h8"]), {matt: -3})
    assert.deepEqual(bewertungAus(["info depth 20 score mate 2 pv h7h8", "info string Ende der Suche"]), {matt: 2})
})

test("bewertungAus übergeht Nebenvarianten (multipv 2 und höher)", () => {
    assert.deepEqual(bewertungAus([
        "info depth 12 multipv 1 score cp 40 pv e2e4",
        "info depth 12 multipv 2 score cp -80 pv a2a3"
    ]), {cp: 40})
})

test("nimmtRemisAn: Stockfish nimmt an, wenn er nicht besser steht", () => {
    // Die Bewertung gilt aus Sicht der Seite am Zug – das ist der Spieler
    assert.equal(nimmtRemisAn({cp: 50}), true, "der Spieler steht besser")
    assert.equal(nimmtRemisAn({cp: 0}), true, "ausgeglichen")
    assert.equal(nimmtRemisAn({cp: -1}), false, "Stockfish steht besser (+1 aus seiner Sicht)")
    assert.equal(nimmtRemisAn({cp: -250}), false, "Stockfish steht klar besser")
    assert.equal(nimmtRemisAn({matt: 3}), true, "gegen Stockfish läuft ein Matt")
    assert.equal(nimmtRemisAn({matt: -2}), false, "Stockfish setzt matt")
    assert.equal(nimmtRemisAn({matt: 0}), false, "der Spieler ist schon matt")
})

test("VOLLE_STAERKE bewertet ohne begrenzte Spielstärke und mit fester Rechenzeit", () => {
    assert.deepEqual(optionsBefehle(VOLLE_STAERKE), ["setoption name UCI_LimitStrength value false", "setoption name Skill Level value 20"])
    assert.equal(goBefehl(VOLLE_STAERKE, 800), "go movetime 800")
})

test("bewerten() rechnet über die Stellung und liefert die letzte Bewertung", async () => {
    const gesendet = []
    globalThis.Worker = class {
        postMessage(befehl) {
            gesendet.push(befehl)
            const antworten = {uci: "uciok", isready: "readyok"}
            const antwort = befehl.startsWith("go")
                ? "info depth 1 seldepth 1 multipv 1 score cp 12 pv e7e5\ninfo depth 2 seldepth 2 multipv 1 score cp -34 pv e7e5 g1f3\nbestmove e7e5 ponder g1f3"
                : antworten[befehl]
            if (antwort) {
                setTimeout(() => this.onmessage({data: antwort}), 0)
            }
        }
        terminate() {
        }
    }

    const engine = neueEngine()
    const bewertung = await engine.bewerten(["e2e4"], VOLLE_STAERKE, 800)

    assert.deepEqual(bewertung, {cp: -34})
    assert.deepEqual(gesendet, [
        "uci", "isready",
        "setoption name UCI_LimitStrength value false", "setoption name Skill Level value 20", "isready",
        "position startpos moves e2e4", "go movetime 800",
        "setoption name Clear Hash", "isready"
    ])
    engine.beenden()
    delete globalThis.Worker
})

test("bewerten() weist ab, wenn Stockfish keine Bewertung meldet", async () => {
    globalThis.Worker = nachgebildeterWorker(worker => worker.onmessage({data: "bestmove e7e5"}), {erzeugt: [], beendet: []})

    const engine = neueEngine()
    await assert.rejects(engine.bewerten(["e2e4"], VOLLE_STAERKE, 100), /keine Bewertung/)
    engine.beenden()
    delete globalThis.Worker
})

/**
 * Baut einen Worker, der alle Befehle mitschreibt und auf „go" eine feste
 * Antwort gibt; uci und isready beantwortet er wie üblich.
 *
 * @param {string[]} gesendet Sammelt die Befehle an den Worker
 * @param {string} aufGo Die Ausgabe der Engine auf „go"
 * @returns {Function} Die Klasse für globalThis.Worker
 */
function mitschreibenderWorker(gesendet, aufGo) {
    return class {
        postMessage(befehl) {
            gesendet.push(befehl)
            const antwort = befehl.startsWith("go") ? aufGo : {uci: "uciok", isready: "readyok"}[befehl]
            if (antwort) {
                setTimeout(() => this.onmessage({data: antwort}), 0)
            }
        }
        terminate() {
        }
    }
}

test("nach bewerten() leert die Engine ihre Hashtabelle, bevor der nächste Zug die Stufe einstellt", async () => {
    // Die Bewertung rechnet in voller Stärke. Ihre Hashtabelle darf nicht in die
    // nachgebauten Stufen (go depth 1) hinüberwirken, sonst spielen sie stärker
    const gesendet = []
    globalThis.Worker = mitschreibenderWorker(gesendet, "info depth 1 score cp 5 pv e2e4\nbestmove e2e4")

    const engine = neueEngine()
    await engine.bewerten(["e2e4"], VOLLE_STAERKE, 800)
    await engine.zug(["e2e4"], SCHWACH, 1000)

    const leeren = gesendet.indexOf("setoption name Clear Hash")
    assert.ok(leeren >= 0, "die Hashtabelle wird geleert")
    assert.equal(gesendet[leeren + 1], "isready", "die Engine bestätigt das Leeren, bevor es weitergeht")
    assert.ok(gesendet.indexOf("go movetime 800") < leeren, "erst nach der Bewertung")
    const stufe = gesendet.indexOf("setoption name Skill Level value 0")
    assert.ok(stufe > leeren, "die Optionen der Partie folgen danach")
    assert.ok(gesendet.indexOf("go depth 1") > stufe)
    engine.beenden()
    delete globalThis.Worker
})

test("scheitert die Bewertung, wird die Hashtabelle trotzdem geleert und der Fehler bleibt der echte", async () => {
    const gesendet = []
    globalThis.Worker = mitschreibenderWorker(gesendet, "bestmove e7e5")

    const engine = neueEngine()
    await assert.rejects(engine.bewerten(["e2e4"], VOLLE_STAERKE, 100), /keine Bewertung/)

    assert.ok(gesendet.includes("setoption name Clear Hash"))
    engine.beenden()
    delete globalThis.Worker
})

test("fällt die Engine während der Bewertung aus, bleibt deren Fehler und nichts wird an den toten Worker geschickt", async () => {
    const gesendet = []
    globalThis.Worker = class {
        postMessage(befehl) {
            gesendet.push(befehl)
            if (befehl.startsWith("go")) {
                setTimeout(() => this.onerror({message: "abgestürzt"}), 0)
                return
            }
            const antwort = {uci: "uciok", isready: "readyok"}[befehl]
            if (antwort) {
                setTimeout(() => this.onmessage({data: antwort}), 0)
            }
        }
        terminate() {
        }
    }

    const engine = neueEngine()
    await assert.rejects(engine.bewerten(["e2e4"], VOLLE_STAERKE, 100), /abgestürzt/)

    assert.ok(!gesendet.includes("setoption name Clear Hash"), "ein neuer Worker beginnt ohnehin mit leerer Tabelle")
    delete globalThis.Worker
})

test("bewerten() läuft über dieselbe Kette wie zug(), nie gleichzeitig", async () => {
    const gesendet = []
    globalThis.Worker = class {
        postMessage(befehl) {
            gesendet.push(befehl)
            const antworten = {uci: "uciok", isready: "readyok"}
            const antwort = befehl.startsWith("go") ? "info depth 1 score cp 5 pv e2e4\nbestmove e2e4" : antworten[befehl]
            if (antwort) {
                setTimeout(() => this.onmessage({data: antwort}), 5)
            }
        }
        terminate() {
        }
    }

    const engine = neueEngine()
    const [zug, bewertung] = await Promise.all([engine.zug([], GEEICHT, 100), engine.bewerten([], VOLLE_STAERKE, 100)])

    assert.equal(zug, "e2e4")
    assert.deepEqual(bewertung, {cp: 5})
    const erstesGo = gesendet.indexOf("go movetime 100")
    assert.ok(erstesGo >= 0)
    assert.ok(gesendet.indexOf("setoption name Skill Level value 20") > erstesGo, "die Bewertung beginnt erst nach der Zugsuche")
    engine.beenden()
    delete globalThis.Worker
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

    const engine = neueEngine()
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

    const engine = neueEngine()
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

    const engine = neueEngine()
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

    const engine = neueEngine()
    const suche = engine.zug([], GEEICHT, 100)
    await go
    engine.beenden()

    await assert.rejects(suche)
    assert.equal(protokoll.beendet.length, 1)
    delete globalThis.Worker
})

test("eine noch wartende (nicht gestartete) Suche wird nach beenden() abgewiesen, ohne einen neuen Worker zu starten", async () => {
    const protokoll = {erzeugt: [], beendet: []}
    let goAngekommen
    const go = new Promise(erfuellen => { goAngekommen = erfuellen })
    // Die erste Suche hängt in „go" fest, die zweite wartet in der Kette (this.kette)
    globalThis.Worker = nachgebildeterWorker(() => goAngekommen(), protokoll)

    const engine = neueEngine()
    const erste = engine.zug([], GEEICHT, 100)
    await go
    const zweite = engine.zug([], GEEICHT, 100)
    engine.beenden()

    await assert.rejects(erste)
    await assert.rejects(zweite, /beendet, bevor die Suche beginnen konnte/)
    assert.equal(protokoll.erzeugt.length, 1, "die wartende Suche prüft die Epoche, statt einen zweiten Worker zu starten")
    delete globalThis.Worker
})

test("ein synchroner Fehler bei new Worker() weist ab, statt die Ausführung abzubrechen", async () => {
    globalThis.Worker = class {
        constructor() {
            throw new Error("Worker sind durch die Content-Security-Policy verboten.")
        }
    }

    const engine = neueEngine()
    let versprechen
    // starten() darf nicht synchron werfen: den Fehler des Konstruktors fängt es ab
    assert.doesNotThrow(() => { versprechen = engine.starten() })
    await assert.rejects(versprechen, /Content-Security-Policy/)
    // this.bereit wurde dabei nie gesetzt: der nächste Versuch startet neu (und scheitert hier ebenso)
    await assert.rejects(engine.zug([], GEEICHT, 100), /Content-Security-Policy/)
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

    const engine = neueEngine()
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

    const engine = neueEngine()
    await assert.rejects(engine.starten(20), /nicht rechtzeitig/)
    engine.beenden()
    delete globalThis.Worker
})

test("starten() erfüllt, sobald die Engine readyok gemeldet hat", async () => {
    const protokoll = {erzeugt: [], beendet: []}
    globalThis.Worker = nachgebildeterWorker(() => undefined, protokoll)

    const engine = neueEngine()
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
