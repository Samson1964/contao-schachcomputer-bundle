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
