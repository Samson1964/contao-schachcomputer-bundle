<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

// Legends
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['bedenkzeit_legend'] = 'Time control';
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['zeit_legend'] = 'Time';
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['publish_legend'] = 'Publishing';

// Fields
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['name'] = array('Name', 'How the time control appears in the selection, e.g. "3+2".');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['klasse'] = array('Rating category', 'Each category has its own ratings and rankings.');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['minuten'] = array('Minutes', 'Base time of the player in minutes.');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['inkrement'] = array('Increment', 'Seconds added after each move (from the second move on).');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['published'] = array('Published', 'Only published time controls can be selected.');

// Rating categories
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['klassen'] = array
(
	'blitz'   => 'Blitz',
	'schnell' => 'Rapid',
	'lang'    => 'Classical',
);

// Operations
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['edit'] = array('Edit', 'Edit time control ID %s');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['copy'] = array('Copy', 'Copy time control ID %s');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['delete'] = array('Delete', 'Delete time control ID %s');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['toggle'] = array('Publish', 'Publish/unpublish time control ID %s');
$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['show'] = array('Details', 'Show the details of time control ID %s');
