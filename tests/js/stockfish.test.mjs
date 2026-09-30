/*
 * Rauchtest für die mitgelieferte Engine: Läuft stockfish-19-lite-single.js
 * mit seiner .wasm-Datei, und kennt es die Optionen, auf die das Bundle baut?
 *
 * stockfish.js läuft auch unter Node; dort liest es UCI-Befehle von stdin.
 * Aufruf: node --test "tests/js/*.test.mjs"
 */

import {test} from "node:test"
import assert from "node:assert/strict"
import {spawn} from "node:child_process"
import {fileURLToPath} from "node:url"

const ENGINE = fileURLToPath(new URL("../../src/Resources/public/vendor/stockfish/stockfish-19-lite-single.js", import.meta.url))

/**
 * Startet die Engine, schickt Befehle und sammelt die Ausgabe bis zu einem Muster.
 *
 * @param {string[]} befehle UCI-Befehle
 * @param {RegExp} ende Muster, bei dem die Ausgabe vollständig ist
 * @returns {Promise<string>} Die gesammelte Ausgabe
 */
function befragen(befehle, ende) {
    return new Promise((erfuellen, ablehnen) => {
        const prozess = spawn(process.execPath, [ENGINE])
        let ausgabe = ""
        const frist = setTimeout(() => {
            prozess.kill()
            ablehnen(new Error("Keine Antwort in 20 s:\n" + ausgabe))
        }, 20000)

        prozess.stdout.on("data", daten => {
            ausgabe += daten
            if (ende.test(ausgabe)) {
                clearTimeout(frist)
                prozess.stdin.end("quit\n")
                prozess.kill()
                erfuellen(ausgabe)
            }
        })
        prozess.on("error", ablehnen)
        prozess.stdin.write(befehle.join("\n") + "\n")
    })
}

test("Stockfish 19 meldet die Optionen zur Spielstärke", async () => {
    const ausgabe = await befragen(["uci"], /uciok/)

    assert.match(ausgabe, /id name Stockfish 19/)
    assert.match(ausgabe, /option name UCI_Elo type spin default 1320 min 1320 max 3190/)
    assert.match(ausgabe, /option name Skill Level type spin default 20 min 0 max 20/)
    assert.match(ausgabe, /option name Clear Hash type button/, "engine.js leert damit nach einer Bewertung die Hashtabelle")
})

test("Stockfish findet mit begrenzter Stärke einen Zug", async () => {
    const ausgabe = await befragen([
        "uci",
        "setoption name UCI_LimitStrength value true",
        "setoption name UCI_Elo value 1500",
        "isready",
        "position startpos moves e2e4",
        "go movetime 300"
    ], /bestmove [a-h][1-8][a-h][1-8]/)

    assert.match(ausgabe, /readyok/)
})
