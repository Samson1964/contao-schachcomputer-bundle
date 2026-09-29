<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

// Legenden
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['bedenkzeit_legend'] = 'Bedenkzeit';
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['zeit_legend'] = 'Zeit';
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['publish_legend'] = 'Veröffentlichung';

// Felder
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['name'] = array('Bezeichnung', 'So erscheint die Bedenkzeit in der Auswahl, etwa „3+2".');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['klasse'] = array('Wertungsklasse', 'Jede Klasse hat eigene Wertungen und Ranglisten.');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['minuten'] = array('Minuten', 'Grundbedenkzeit des Spielers in Minuten.');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['inkrement'] = array('Zeitgutschrift', 'Sekunden, die nach jedem Zug gutgeschrieben werden (ab dem zweiten Zug).');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['published'] = array('Veröffentlicht', 'Nur veröffentlichte Bedenkzeiten stehen zur Auswahl.');

// Wertungsklassen
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['klassen'] = array
(
	'blitz'   => 'Blitz',
	'schnell' => 'Schnellschach',
	'lang'    => 'Langpartie',
);

// Operationen
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['edit'] = array('Bearbeiten', 'Bedenkzeit ID %s bearbeiten');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['copy'] = array('Kopieren', 'Bedenkzeit ID %s kopieren');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['delete'] = array('Löschen', 'Bedenkzeit ID %s löschen');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['toggle'] = array('Veröffentlichen', 'Bedenkzeit ID %s veröffentlichen/verbergen');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['show'] = array('Details', 'Details der Bedenkzeit ID %s anzeigen');
