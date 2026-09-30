# Änderungen

## Version 1.1.0 (2026-09-30)

* Add: Echte Schachuhr auch für den Computer. In gewerteten Partien stehen zwei Uhren nebeneinander („Stockfish“ und „Du“), die gerade laufende ist hervorgehoben. Der Server misst die Zeit des Computers selbst, zieht sie abzüglich bis zu 1 s Ausgleich für die Übertragung ab (wie beim Spieler) und schreibt die Gutschrift ab dem ersten Computerzug gut. Trifft sein Zug nach Ablauf seiner Uhr ein, verliert der Computer auf Zeit (remis, wenn der Spieler kein Mattmaterial mehr hat); bleibt der Zug ganz aus, gilt weiter die Frist von 60 Sekunden. Nach dem Update die Datenbank aktualisieren (neue Spalte `restzeitEngine`); Partien, die vorher begonnen wurden, laufen ohne Uhr des Computers zu Ende.
* Change: Zeiteinteilung von Stockfish in gewerteten Partien: Wird seine Uhr knapp, rechnet er kürzer (höchstens ein Dreißigstel der Restzeit plus die halbe Gutschrift, mindestens 0,2 Sekunden) und zieht auch auf schwachen Stufen schneller.

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
