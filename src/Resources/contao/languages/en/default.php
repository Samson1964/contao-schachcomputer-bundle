<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

// Module "play": texts for template and spielen.js (%s is replaced)
$GLOBALS['TL_LANG']['MSC']['schachcomputer'] = array
(
	'brett'             => 'Chessboard',
	'uhr'               => 'Your remaining time',
	'laden'             => 'Loading …',
	'fehler'            => 'Something went wrong. Please reload the page.',
	'deineWertung'      => 'Your rating',
	'bedenkzeit'        => 'Time control',
	'stufe'             => 'Computer strength',
	'farbe'             => 'Your colour',
	'weiss'             => 'White',
	'schwarz'           => 'Black',
	'zufall'            => 'Random',
	'gewertetStarten'   => 'Start rated game',
	'uebungStarten'     => 'Practice game',
	'keineBedenkzeit'   => 'No time controls for rated games are available at the moment.',
	'gastHinweis'       => 'As a guest, your rating is only kept for this visit. Log in to keep it and to appear in the rankings.',
	'skala'             => 'The computer strength is based on Stockfish\'s calibration against the CCRL blitz list. These are not national ratings: Stockfish at 1500 plays stronger than a club player rated 1500. Levels below 1400 are estimates.',
	'gegner'            => 'Stockfish, level %s',
	'uebung'            => 'Practice game',
	'spieler'           => 'Player',
	'pgnEvent'          => 'Game against Stockfish',
	'amZug'             => 'Your move.',
	'ersterZug'         => 'Make your first move within %s seconds, otherwise the game is aborted.',
	'engineDenkt'       => 'The computer is thinking …',
	'engineNichtBereit' => 'Stockfish could not be loaded, so no game was started. Check your connection and try again.',
	'engineAusgefallen' => 'The computer is not responding. Reload the page so it can continue – if its move does not arrive within one minute, the game counts as abandoned.',
	'gewonnen'          => 'Won',
	'verloren'          => 'Lost',
	'remis'             => 'Draw',
	'unbeendet'         => 'Game over.',
	'abgebrochen'       => 'The game was aborted without rating.',
	'wertungAenderung'  => 'Your rating: %s → %s.',
	'abbrechen'         => 'Abort',
	'aufgeben'          => 'Resign',
	'aufgebenFrage'     => 'Do you really want to resign?',
	'zuruecknehmen'     => 'Take back',
	'beenden'           => 'End game',
	'pgnKopieren'       => 'Copy PGN',
	'pgnKopiert'        => 'The PGN is in the clipboard.',
	'neuePartie'        => 'New game',
	'uebungGespeichert' => 'The game is now listed under "My games".',
	'klassen'           => array
	(
		'blitz'   => 'Blitz',
		'schnell' => 'Rapid',
		'lang'    => 'Classical',
	),
	'gruende'           => array
	(
		'matt'         => 'Checkmate',
		'patt'         => 'Stalemate',
		'material'     => 'Insufficient material',
		'wiederholung' => 'Threefold repetition',
		'fuenfzig'     => 'Fifty-move rule',
		'zeit'         => 'Time forfeit',
		'aufgabe'      => 'Resignation',
		'verlassen'    => 'Game abandoned',
		'abbruch'      => 'Aborted',
		'erster_zug'   => 'First move not made',
		'unbeendet'    => 'Not finished',
	),
);

// Module "ranking" (%s is replaced)
$GLOBALS['TL_LANG']['MSC']['schachcomputer_rangliste'] = array
(
	'platz'        => 'Rank',
	'name'         => 'Name',
	'wertung'      => 'Rating',
	'hoechstwert'  => 'Peak rating',
	'partien'      => 'Games',
	'datum'        => 'Reached on',
	'veraenderung' => 'Ranks / points',
	'neu'          => 'new',
	'leer'         => 'Nobody is on this list yet. Players are listed once their rating is established.',
	'monat'        => 'Month',
	'anzeigen'     => 'Show',
	'stand'        => 'As of 1 %s',
);

// Module "my games"
$GLOBALS['TL_LANG']['MSC']['schachcomputer_partien'] = array
(
	'nurMitglieder' => 'Log in to see your games.',
	'leer'          => 'You have not finished a game yet.',
	'datum'         => 'Date',
	'art'           => 'Time control',
	'uebung'        => 'Practice',
	'farbe'         => 'Colour',
	'weiss'         => 'White',
	'schwarz'       => 'Black',
	'stufe'         => 'Level',
	'ergebnis'      => 'Result',
	'gewonnen'      => 'Won',
	'verloren'      => 'Lost',
	'remis'         => 'Draw',
	'offen'         => 'Open',
	'wertung'       => 'Rating',
	'pgn'           => 'PGN',
	'allePgn'       => 'Download all games as PGN',
);

// Module "rating history" (%s is replaced: category, rating, games)
$GLOBALS['TL_LANG']['MSC']['schachcomputer_verlauf'] = array
(
	'nurMitglieder' => 'Log in to see your rating history.',
	'leer'          => 'You have not finished a rated game yet.',
	'beschriftung'  => '%s: %s after %s games',
	'kurve'         => 'Rating history %s',
);

// Back end statistics (do=schachcomputer_partien&key=statistik; %s is replaced)
$GLOBALS['TL_LANG']['MSC']['schachcomputer_statistik'] = array
(
	'zurueckModul'      => 'Go back',
	'ueberschrift'      => 'Statistics of visits and games',
	'zeitraumTitel'     => 'Period',
	'ebene_tag'         => 'Day',
	'ebene_monat'       => 'Month',
	'ebene_jahr'        => 'Year',
	'zurueck'           => 'back',
	'vor'               => 'forward',
	'heute'             => 'until today',
	'bestand'           => '%s published time controls · %s members with a rating · %s new players in this period',
	'keineDaten'        => 'Nothing was counted in this period. Use "back" to go to an earlier period; the buttons above switch from a day to a whole month or year.',
	'art_aufruf'        => 'visits',
	'art_gestartet'     => 'rated games started',
	'art_beendet'       => 'rated games finished',
	'art_abgebrochen'   => 'aborted',
	'art_uebung'        => 'practice games saved',
	'davon'             => '%s members · %s guests',
	'quote'             => 'Score',
	'ergebnisse'        => '%s won · %s drawn · %s lost',
	'diagrammGestartet' => 'Visits and games started',
	'diagrammGewonnen'  => 'Games finished and won',
	'legendeGestartet'  => array('started', 'visited'),
	'legendeGewonnen'   => array('won', 'finished'),
	'bedenkzeiten'      => 'Most played time controls (members)',
	'aktivste'          => 'Most active members',
	'keineMitglieder'   => 'No member finished a rated game in this period.',
	'platz'             => 'Rank',
	'partien'           => 'Games',
	'bedenkzeit'        => 'Time control',
	'stufe'             => 'Ø level',
	'punkte'            => 'Points',
	'name'              => 'Name',
	'besteWertung'      => 'Best rating',
	'hinweis'           => 'Counting starts with the introduction of the statistics: visits when the page with the "play" module is loaded, plus the start and end of every rated game and saved practice games. Practice games of guests run only in the browser and are not counted. The tables are based on the games of members; guest games are deleted after one day.',
);
