<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_module']['schachcomputer_legend'] = 'Schachcomputer';

$GLOBALS['TL_LANG']['tl_module']['schachcomputerModus'] = array('Liste', 'Aktuelle Rangliste, ewige Bestenliste (Höchstwerte) oder Monatsrangliste (Stand am Monatsersten).');
$GLOBALS['TL_LANG']['tl_module']['schachcomputerKlasse'] = array('Wertungsklasse', 'Jede Klasse hat eigene Wertungen und Ranglisten.');
$GLOBALS['TL_LANG']['tl_module']['schachcomputerAnzahl'] = array('Anzahl', 'So viele Einträge werden höchstens gezeigt.');

$GLOBALS['TL_LANG']['tl_module']['schachcomputerModi'] = array
(
	'aktuell'  => 'Aktuelle Rangliste',
	'ewig'     => 'Ewige Bestenliste',
	'stichtag' => 'Monatsrangliste',
);

$GLOBALS['TL_LANG']['tl_module']['schachcomputerKlassen'] = array
(
	'blitz'   => 'Blitz',
	'schnell' => 'Schnellschach',
	'lang'    => 'Langpartie',
);
