<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

// Fields
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['memberId'] = array('Member', 'The member from the member management.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['klasse'] = array('Rating category', 'Blitz, rapid or classical.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['wertung'] = array('Rating', 'Glicko-2 rating after the last game.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['abweichung'] = array('Deviation', 'Uncertainty of the rating; above 110 it is provisional.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['volatilitaet'] = array('Volatility', 'How much the performance varies.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['partien'] = array('Games', 'Number of rated games.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['siege'] = array('Wins', 'Games won.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['remis'] = array('Draws', 'Games drawn.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['niederlagen'] = array('Losses', 'Games lost.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['hoechstwert'] = array('Peak rating', 'Highest established rating.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['hoechstwertDatum'] = array('Peak rating on', 'Date of the peak rating.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['letztePartie'] = array('Last game', 'End of the last rated game.');

// Rating categories
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['klassen'] = array
(
	'blitz'   => 'Blitz',
	'schnell' => 'Rapid',
	'lang'    => 'Classical',
);

// Operations
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['delete'] = array('Delete', 'Delete rating ID %s including its history');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['show'] = array('Details', 'Show the details of rating ID %s');
