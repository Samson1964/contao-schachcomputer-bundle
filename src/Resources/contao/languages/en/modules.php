<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

// Backend modules
$GLOBALS['TL_LANG']['MOD']['schachcomputer'] = 'Chess computer';
$GLOBALS['TL_LANG']['MOD']['schachcomputer_bedenkzeiten'] = array('Time controls', 'Time controls for rated games against the chess computer');
$GLOBALS['TL_LANG']['MOD']['schachcomputer_spieler'] = array('Players', 'Ratings of the members per rating category');
$GLOBALS['TL_LANG']['MOD']['schachcomputer_partien'] = array('Games', 'All games against the chess computer');

// Front end modules
$GLOBALS['TL_LANG']['FMD']['schachcomputer'] = 'Chess computer';
$GLOBALS['TL_LANG']['FMD']['schachcomputer_spielen'] = array('Chess computer: play', 'Rated games and practice games against Stockfish in the browser.');
$GLOBALS['TL_LANG']['FMD']['schachcomputer_rangliste'] = array('Chess computer: ranking', 'Current ranking, all-time best list or monthly ranking of a rating category.');
$GLOBALS['TL_LANG']['FMD']['schachcomputer_partien'] = array('Chess computer: my games', 'The games of the logged-in member with PGN download.');
$GLOBALS['TL_LANG']['FMD']['schachcomputer_verlauf'] = array('Chess computer: rating history', 'The rating of the logged-in member as a chart per rating category.');
