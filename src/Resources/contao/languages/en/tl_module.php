<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_module']['schachcomputer_legend'] = 'Chess computer';

$GLOBALS['TL_LANG']['tl_module']['schachcomputerModus'] = array('List', 'Current ranking, all-time best list (peak ratings) or monthly ranking (as of the first of the month).');
$GLOBALS['TL_LANG']['tl_module']['schachcomputerKlasse'] = array('Rating category', 'Each category has its own ratings and rankings.');
$GLOBALS['TL_LANG']['tl_module']['schachcomputerAnzahl'] = array('Number', 'Show at most this many entries.');

$GLOBALS['TL_LANG']['tl_module']['schachcomputerModi'] = array
(
	'aktuell'  => 'Current ranking',
	'ewig'     => 'All-time best list',
	'stichtag' => 'Monthly ranking',
);

$GLOBALS['TL_LANG']['tl_module']['schachcomputerKlassen'] = array
(
	'blitz'   => 'Blitz',
	'schnell' => 'Rapid',
	'lang'    => 'Classical',
);
