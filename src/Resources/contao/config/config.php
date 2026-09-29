<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

use Schachbulle\ContaoSchachcomputerBundle\Module\SpielenModule;

// Eigene Gruppe im Backend-Menü; der Schlüssel landet als CSS-Klasse im
// Markup, der Anzeigename kommt aus TL_LANG['MOD']['schachcomputer']
$GLOBALS['BE_MOD']['schachcomputer'] = array
(
	'schachcomputer_bedenkzeiten' => array
	(
		'tables' => array('tl_schachcomputer_bedenkzeit'),
	),
	'schachcomputer_spieler' => array
	(
		'tables' => array('tl_schachcomputer_spieler', 'tl_schachcomputer_verlauf'),
	),
	'schachcomputer_partien' => array
	(
		'tables' => array('tl_schachcomputer_partie'),
	),
);

// Frontend-Module in eigener Gruppe
$GLOBALS['FE_MOD']['schachcomputer'] = array
(
	'schachcomputer_spielen'   => SpielenModule::class,
);
