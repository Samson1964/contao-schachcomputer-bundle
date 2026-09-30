# Schachcomputer für Contao

Mitglieder und Gäste spielen im Browser gewertete Partien gegen die
Schach-Engine Stockfish. Aus den Partien entstehen Wertungszahlen und daraus
Ranglisten. Läuft unter **Contao 4.13 und Contao 5.7** (PHP 8.1 bis 8.4).

## Funktionen

- **Gewertete Partien** mit Schachuhr für beide Seiten, Remisangebot und
  Bedenkzeiten, die der Redakteur im Backend anlegt.
  Jede Bedenkzeit gehört zu einer Wertungsklasse: Blitz, Schnellschach oder
  Langpartie. Jede Klasse hat eigene Wertungen und Ranglisten.
- **Spielstärke** von 600 bis 2500 in 100er-Schritten. Vorausgewählt ist die
  Stufe, die der eigenen Wertung am nächsten liegt.
- **Übungspartien** ohne Uhr, mit Zurücknehmen und „PGN kopieren". Für
  Mitglieder werden sie gespeichert.
- **Ranglisten**: aktuelle Rangliste, ewige Bestenliste (Höchstwerte) und
  Monatsrangliste (Stand am Monatsersten, mit Veränderung zum Vormonat).
- **Eigene Partien** mit PGN-Download und **Wertungsverlauf** als Kurve.
- **Gäste** spielen ohne Anmeldung; ihre Wertung gilt nur für den Besuch und
  erscheint in keiner Rangliste.
- **Statistik im Backend** unter *Schachcomputer → Partien → Statistik*, aufgebaut
  wie die Statistik des Schachaufgaben-Bundles (siehe unten).

## Einrichtung

1. Bundle installieren und die Datenbank aktualisieren
   (`contao:migrate` oder Contao Manager).
2. Im Backend unter **Schachcomputer → Bedenkzeiten** Bedenkzeiten anlegen und
   veröffentlichen, etwa „3+2" (Blitz), „10+5" (Schnellschach), „30+0" (Langpartie).
3. Frontend-Module anlegen und auf Seiten einbinden:
   - **Schachcomputer: Spielen**
   - **Schachcomputer: Rangliste** – Liste (aktuell, ewig, Monat), Klasse, Anzahl
   - **Schachcomputer: Eigene Partien** – Anzahl je Seite
   - **Schachcomputer: Wertungsverlauf**
4. **Cronjob einrichten.** Verlassene Partien, Monatsranglisten und das
   Aufräumen alter Gastpartien laufen über Contaos Cron. Ohne echten Cronjob
   läuft er nur, wenn jemand die Seite aufruft; Ergebnisse stimmen dann
   trotzdem, werden aber später verrechnet. Empfohlen, minütlich:

   ```
   * * * * * php /pfad/zu/contao/vendor/bin/contao-console contao:cron
   ```

5. **Content-Security-Policy.** Wer eine CSP setzt (ab Contao 5.3 je
   Startpunkt einstellbar), muss in `script-src` zusätzlich
   `'wasm-unsafe-eval'` erlauben. Sonst startet Stockfish nicht, und es
   beginnt keine gewertete Partie; die Seite zeigt dann einen Hinweis.
6. **Seiten-Cache.** Die Bedenkzeiten stehen im Quelltext der Seite mit dem
   Modul „Spielen“. Bei eingeschaltetem Seiten-Cache erscheinen neue oder
   geänderte Bedenkzeiten erst, wenn der Cache abgelaufen ist oder geleert
   wird. Für die Ranglisten gilt dasselbe.

## Spielregeln und Wertung

- Der Server führt jede gewertete Partie: Jeder Zug wird auf dem Server
  geprüft, die Uhren laufen auf dem Server.
- **Zwei Uhren:** Spieler und Computer bekommen dieselbe Bedenkzeit. Beide
  Uhren stehen nebeneinander, die gerade laufende ist hervorgehoben.
- Die Uhr des Spielers läuft ab seinem zweiten Zug; für den ersten Zug gibt
  es 60 Sekunden, sonst wird die Partie ungewertet abgebrochen. Die
  Zeitgutschrift gibt es ab dem zweiten Zug. Bis zu einer Sekunde
  Übertragungszeit je Zug wird ausgeglichen.
- Die Uhr des Computers läuft ab seinem ersten Zug, die Gutschrift gibt es
  ebenfalls ab dem ersten Zug. Seine Zeit misst der Server selbst: vom
  Speichern des Spielerzugs bis zum Eintreffen des Computerzugs, abzüglich
  bis zu 1 s Ausgleich für die Übertragung (wie beim Spieler).
- **Zeiteinteilung von Stockfish:** Die Engine rechnet 1 bis 2 Sekunden je
  Zug, und zwar im Browser des Spielers. Wird ihre Uhr knapp, rechnet sie
  kürzer: höchstens ein Dreißigstel der Restzeit (nach einer Sekunde
  Reserve) plus die halbe Gutschrift, mindestens 0,2 Sekunden.
- **Computer zu spät:** Trifft der Computerzug erst nach Ablauf seiner Uhr
  ein, wird er nicht mehr ausgeführt, und der Computer verliert auf Zeit –
  remis, wenn der Spieler kein Mattmaterial mehr hat.
- **Computerzug bleibt aus:** Bleibt ein Engine-Zug 60 Sekunden aus, gilt die
  Partie als verlassen und ist verloren, auch wenn die Uhr des Computers
  schon früher abgelaufen wäre. Die Uhr des Computers zählt nur, wenn sein
  Zug tatsächlich ankommt – sonst gewönne, wer nach dem eigenen Zug den Tab
  schließt.
- Partien, die vor Fassung 1.1.0 begonnen wurden, laufen ohne Uhr des
  Computers zu Ende.
- **Tab schließen:** Ist der Spieler am Zug, läuft seine Uhr weiter ab, und
  er verliert auf Zeit. Ist die Engine am Zug, gilt die Partie nach
  60 Sekunden als verlassen. Vor dem ersten eigenen Zug wird die Partie
  ungewertet abgebrochen.
- **Hintergrund-Tab:** Ein Tab im Hintergrund schadet nicht; die Engine
  zieht dort weiter. Auf Mobilgeräten kann das Betriebssystem einen Tab im
  Hintergrund aber anhalten – dann gilt dasselbe wie beim Schließen.
- **Stockfish lädt nicht:** Die Engine wird schon beim Aufruf der Seite
  geladen. Ist sie nach 20 Sekunden nicht bereit, beginnt keine gewertete
  Partie, und es erscheint ein Hinweis. Antwortet sie während einer Partie
  nicht, wird sie einmal frisch gestartet.
- Remis nach den Regeln (Patt, dreifache Wiederholung, 50 Züge, ungenügendes
  Material) wird selbsttätig erkannt. Läuft die Zeit des Spielers ab und hat
  die Engine kein Mattmaterial mehr, endet die Partie remis.
- **Remis anbieten:** In gewerteten Partien kann der Spieler Remis anbieten,
  wenn er am Zug ist – frühestens vor seinem 20. Zug, nach einem abgelehnten
  Angebot erst wieder fünf eigene Züge später. Stockfish prüft die Stellung
  in voller Stärke etwa eine Sekunde lang (bei knapper Uhr kürzer, mindestens
  0,2 Sekunden); die Zeit geht von der Uhr des Spielers ab. Er nimmt an, wenn
  er nicht besser steht (Bewertung aus seiner Sicht höchstens 0, oder gegen
  ihn läuft ein Matt). Angenommen endet die Partie remis durch Einigung und
  wird gewertet; abgelehnt geht sie weiter, die Uhr des Spielers läuft dabei
  durch. Übungspartien haben stattdessen „Partie beenden“.
- Wertung nach **Glicko-2**, Start 1500. Eine Wertung mit Abweichung über 110
  gilt als vorläufig (Anzeige mit „?") und steht in keiner Rangliste. Wer
  lange nicht spielt, wird wieder vorläufig – nach etwa drei Monaten.
- Die ewige Bestenliste zählt nur Höchstwerte gesicherter Wertungen.

**Zur Skala:** Stockfish hat seine Stärke ab 1320 an der CCRL-Blitzliste
geeicht. Das sind keine DWZ-Werte: Stockfish mit 1500 spielt stärker als ein
Vereinsspieler mit DWZ 1500. Die Stufen unter 1400 sind nachgebaut
(begrenzte Suchtiefe und Zufallszüge) und geschätzt.

**Bekannte Grenzen:** Die Engine rechnet im Browser des Spielers. Wer gezielt
manipuliert, könnte ihr schlechte Züge unterschieben; der Server prüft nur,
ob sie regelgerecht sind. Ebenso kann ein manipulierter Browser den fertigen
Engine-Zug bis knapp 60 Sekunden zurückhalten, während die Uhr des Spielers
steht; das kostet die Uhr des Computers und kann ihn bei knapper Uhr sogar
auf Zeit verlieren lassen. Auch über ein Remisangebot entscheidet Stockfish
im Browser: Ein manipulierter Browser kann ein Angebot annehmen lassen; der
Server prüft nur Uhr, Zugzahl und Abstand zum letzten Angebot. Für eine
Vereinsseite ist das vertretbar.

## Statistik im Backend

Der Knopf **Statistik** oben in *Schachcomputer → Partien* zeigt, wie der
Schachcomputer genutzt wird:

- Zeitraum **Tag**, **Monat** oder **Jahr**, mit „zurück", „vor" und „bis heute".
- Kennzahlen, jeweils nach Mitgliedern und Gästen getrennt: Aufrufe (Laden der
  Seite mit dem Modul „Spielen"), begonnene, beendete und abgebrochene gewertete
  Partien, gespeicherte Übungspartien; dazu die Punktquote der Spieler mit
  Siegen, Remis und Niederlagen.
- Zwei Balkendiagramme: begonnen vor aufgerufen, gewonnen vor beendet.
- Die 20 meistgespielten Bedenkzeiten und die 20 aktivsten Mitglieder.

Gezählt wird stündlich in `tl_schachcomputer_statistik` (eine Zeile je Stunde,
Art und Mitglied/Gast), ab der Installation. Übungspartien von Gästen laufen nur
im Browser und werden nicht erfasst. Die beiden Tabellen beruhen auf den Partien
der Mitglieder, weil Gastpartien nach einem Tag gelöscht werden.

## Mitgelieferte Programme

| Programm | Fassung | Lizenz |
| --- | --- | --- |
| [Stockfish](https://stockfishchess.org/) über [stockfish.js](https://github.com/nmrugg/stockfish.js) (lite, single-threaded) | 19.0.0 | GPL-3.0 |
| [cm-chessboard](https://github.com/shaack/cm-chessboard) | 8.14.2 | MIT |
| [chess.js](https://github.com/jhlywa/chess.js) | 1.4.0 | BSD-2-Clause |
| [p-chess/chess](https://github.com/p-chess/chess) (über Composer) | ^1.2 | MIT |

Stockfish läuft als eigenständiges Programm in einem Web Worker; das Bundle
spricht mit ihm nur über das UCI-Textprotokoll. Lizenztext und Quellenangabe
liegen unter `src/Resources/public/vendor/stockfish/`.

Stockfish lädt seine `.wasm`-Datei nur, wenn der Server sie als
`application/wasm` ausliefert. Fehlt dieser Typ (etwa bei nginx ohne
passenden Eintrag in `mime.types`), lädt das Bundle die Datei selbst und
reicht sie mit dem richtigen Typ als `blob:`-Adresse an Stockfish weiter.
Das funktioniert ohne Zutun; besser ist trotzdem der richtige Typ, bei nginx
etwa `types { application/wasm wasm; }`. Wer dann noch eine
Content-Security-Policy setzt, muss in `connect-src` auch `blob:` erlauben.

## Datenschutz

Gespeichert werden je Partie die Züge, die Denkzeiten und das Ergebnis, bei
Mitgliedern dazu Wertung und Wertungsverlauf. Öffentliche Ranglisten zeigen
Namen nur als „Vorname N.". Beim Löschen eines Mitglieds (Backend oder
„Konto schließen") werden alle seine Daten gelöscht. Gastpartien werden einen
Tag nach ihrem Ende gelöscht.

## Entwicklung

```
composer update
vendor/bin/phpunit
node --test "tests/js/*.test.mjs"
```

Die PHP-Tests laufen gegen SQLite im Arbeitsspeicher, die JavaScript-Tests
mit Node (samt Rauchtest der mitgelieferten Engine).
