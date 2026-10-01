# Änderungen

## Version 1.3.0 (2026-10-01)

* Add: Bedenkzeit und Farbe des Startformulars bleiben für den Besuch erhalten. Nach einer Partie oder dem Neuladen der Seite stehen die zuletzt gewählten Werte wieder da (im `sessionStorage` des Browsers; es wird nichts an den Server gesendet). Eine Bedenkzeit, die es nicht mehr gibt, wird ignoriert.
* Fix: In der Backend-Liste *Schachcomputer → Partien* stand unter Contao 4.13 beim Beginn ein Unix-Zeitstempel statt Datum und Uhrzeit. Die Spalte wird jetzt in beiden Contao-Fassungen gleich im Datums- und Zeitformat der Einstellungen angezeigt.
* Fix: Mitglieder ohne eingetragenen Vor- und Nachnamen erschienen in den Ranglisten und in der Backend-Statistik nur mit „–“. Jetzt steht ihr Benutzername da.
* Change: In der Backend-Statistik ist der Name unter „Aktivste Mitglieder“ mit dem Mitglied im Backend verlinkt.

## Version 1.2.0 (2026-10-01)

* Add: Countdown für den ersten Zug. Die 60 Sekunden, die der Spieler für seinen ersten Zug hat, zählen im Statustext sichtbar herunter (auch wenn Stockfish Weiß hat und die Frist erst nach seinem Zug beginnt). Nach Fristende holt die Seite wie bisher den Stand vom Server.
* Add: Rote Ziffern bei knapper Zeit. Zeigt eine Uhr 0:59 oder weniger, färben sich ihre Ziffern rot (Farbe `--schachcomputer-fehler`); bei Bedenkzeiten mit höchstens einer Minute Grundzeit erst ab 0:20. Eine Uhr ohne Wert (Partie aus der Zeit vor 1.1.0) bleibt ungefärbt.
* Change: Die beiden Uhren stehen jetzt übereinander neben dem Brett auf Höhe der Brettmitte (Stockfish oben, der Spieler unten) statt nebeneinander über den Knöpfen. Auf schmalen Bildschirmen steht die Uhr von Stockfish über dem Brett und die des Spielers darunter. Die erste Rasterspalte ist dafür von 36 auf 46 rem verbreitert; das Brett behält seine 36 rem. Wer das Stylesheet des Bundles überschrieben hat, prüft den Aufbau: Der Uhrenblock (`schachcomputer-uhren`) liegt nicht mehr im Partiebereich, sondern mit dem Brett im neuen Block `schachcomputer-bretteil`; die Uhren tragen zusätzlich die Klassen `schachcomputer-uhr--engine` und `schachcomputer-uhr--spieler`. Wer die Vorlage `mod_schachcomputer_spielen` überschrieben hat, übernimmt die neue Anordnung.

## Version 1.1.0 (2026-09-30)

* Add: Echte Schachuhr auch für den Computer. In gewerteten Partien stehen zwei Uhren nebeneinander („Stockfish“ und „Du“), die gerade laufende ist hervorgehoben. Der Server misst die Zeit des Computers selbst, zieht sie abzüglich bis zu 1 s Ausgleich für die Übertragung ab (wie beim Spieler) und schreibt die Gutschrift ab dem ersten Computerzug gut. Trifft sein Zug nach Ablauf seiner Uhr ein, verliert der Computer auf Zeit (remis, wenn der Spieler kein Mattmaterial mehr hat); bleibt der Zug ganz aus, gilt weiter die Frist von 60 Sekunden. Nach dem Update die Datenbank aktualisieren (neue Spalte `restzeitEngine`); Partien, die vorher begonnen wurden, laufen ohne Uhr des Computers zu Ende.
* Add: Remis anbieten in gewerteten Partien: möglich, wenn der Spieler am Zug ist, frühestens vor seinem 20. Zug und nach einem abgelehnten Angebot erst wieder fünf eigene Züge später. Stockfish prüft die Stellung rund eine Sekunde lang (bei knapper Uhr kürzer) auf Kosten der Uhr des Spielers und nimmt an, wenn er nicht besser steht. Angenommen endet die Partie remis durch Einigung (1/2-1/2, gewertet), abgelehnt läuft sie samt Uhr weiter. Nach dem Update die Datenbank aktualisieren (neue Spalte `remisAngebot`).
* Change: Zeiteinteilung von Stockfish in gewerteten Partien: Wird seine Uhr knapp, rechnet er kürzer (höchstens ein Dreißigstel der Restzeit plus die halbe Gutschrift, mindestens 0,2 Sekunden, nie mehr als die Restzeit plus 1 Sekunde Ausgleich abzüglich 0,5 Sekunden Abstand) und zieht auch auf schwachen Stufen schneller.

## Version 1.0.1 (2026-09-30)

* Fix: Stockfish startete nicht, wenn der Server die .wasm-Datei ohne Content-Type application/wasm auslieferte (etwa nginx ohne Eintrag in mime.types); stattdessen erschien „Stockfish ließ sich nicht laden“. Das Bundle lädt die Datei in diesem Fall selbst und reicht sie mit dem richtigen Typ weiter.

## Version 1.0.0 (2026-09-29)

* Add: Gewertete Partien gegen Stockfish 19 im Browser, Bedenkzeiten aus dem Backend in den Klassen Blitz, Schnellschach und Langpartie
* Add: Glicko-2-Wertung je Klasse mit Ruhezeit und Höchstwert; Gäste mit Wertung in der Sitzung
* Add: Aktuelle Rangliste, ewige Bestenliste und Monatsrangliste zum Monatsersten
* Add: Übungspartien mit Zurücknehmen und PGN, eigene Partien mit PGN-Download, Wertungsverlauf
* Add: Cronjobs für verlassene Partien, Monatsranglisten und alte Gastpartien; Aufräumen beim Löschen eines Mitglieds
* Add: Stockfish lädt schon beim Aufruf der Seite; lässt es sich nicht laden, beginnt keine gewertete Partie (mit Hinweis), und eine hängende Engine wird einmal frisch gestartet
* Add: Statistik im Backend (Schachcomputer → Partien → Statistik) wie im Schachaufgaben-Bundle: Aufrufe, begonnene, beendete und abgebrochene Partien, Übungen, Punktquote, zwei Diagramme, meistgespielte Bedenkzeiten und aktivste Mitglieder
