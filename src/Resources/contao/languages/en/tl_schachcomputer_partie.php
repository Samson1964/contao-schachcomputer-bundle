<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

// Fields
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['memberId'] = array('Member', 'Empty for guests.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['gast'] = array('Guest ID', 'Random ID from the session of a guest.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['gewertet'] = array('Rated', 'Practice games are not rated.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['bedenkzeit'] = array('Time control', 'ID of the chosen time control.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['minuten'] = array('Minutes', 'Base time at the start.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['inkrement'] = array('Increment', 'Seconds per move at the start.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['klasse'] = array('Rating category', 'Empty for practice games.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['stufe'] = array('Level', 'Playing level of the engine.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['farbe'] = array('Colour', 'Colour of the player (w/b).');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['status'] = array('Status', 'Running, finished or aborted.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['ergebnis'] = array('Result', 'Result in PGN notation.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['grund'] = array('Reason', 'How the game ended.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['zuege'] = array('Moves', 'All moves in UCI notation.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['zeiten'] = array('Thinking times', 'Time charged per player move in milliseconds.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['zugnummer'] = array('Plies', 'Number of plies played.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['restzeit'] = array('Remaining time', 'Remaining time of the player in milliseconds.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['uhrSeit'] = array('Clock since', 'Start of the running clock in milliseconds.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['beginn'] = array('Start', 'Start of the game.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['ende'] = array('End', 'End of the game.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['verrechnet'] = array('Rated in', 'Whether the game has been included in the rating.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['wertungVorher'] = array('Rating before', 'Rating of the player before the game.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['wertungNachher'] = array('Rating after', 'Rating of the player after the game.');

// Rating categories, status and reasons
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['klassen'] = array
(
	'blitz'   => 'Blitz',
	'schnell' => 'Rapid',
	'lang'    => 'Classical',
);
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['stati'] = array
(
	'laeuft'      => 'running',
	'beendet'     => 'finished',
	'abgebrochen' => 'aborted',
);
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['gruende'] = array
(
	'matt'         => 'Checkmate',
	'patt'         => 'Stalemate',
	'material'     => 'Insufficient material',
	'wiederholung' => 'Threefold repetition',
	'fuenfzig'     => 'Fifty-move rule',
	'zeit'         => 'Time forfeit',
	'aufgabe'      => 'Resignation',
	'verlassen'    => 'Abandoned',
	'abbruch'      => 'Aborted',
	'erster_zug'   => 'First move not made',
	'unbeendet'    => 'Not finished',
);

// Operations
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['delete'] = array('Delete', 'Delete game ID %s');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['show'] = array('Details', 'Show the details of game ID %s');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['pgn'] = array('PGN', 'Download game ID %s as PGN');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['nichtGefunden'] = 'Game %d not found.';
