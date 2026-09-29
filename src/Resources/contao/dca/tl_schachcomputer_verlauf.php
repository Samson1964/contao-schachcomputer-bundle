<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\DataContainer;
use Contao\DC_Table;

/*
 * Tabelle tl_schachcomputer_verlauf: eine Zeile je verrechneter Partie eines
 * Mitglieds mit Wertung und Abweichung danach.
 *
 * Grundlage für die Wertungskurve und die Stichtagslisten. Kindtabelle von
 * tl_schachcomputer_spieler (pid), damit Contao sie beim Löschen eines
 * Spielers mitlöscht. Keine eigene Backend-Ansicht.
 */
$GLOBALS['TL_DCA']['tl_schachcomputer_verlauf'] = array
(
	'config' => array
	(
		'dataContainer' => DC_Table::class,
		'ptable'        => 'tl_schachcomputer_spieler',
		'closed'        => true,
		'notEditable'   => true,
		'notCopyable'   => true,
		'sql'           => array
		(
			'keys' => array
			(
				'id'       => 'primary',
				'pid,zeit' => 'index',
			)
		)
	),

	'list' => array
	(
		'sorting' => array
		(
			'mode'   => DataContainer::MODE_SORTED,
			'fields' => array('zeit'),
			'flag'   => DataContainer::SORT_DESC,
		),
		'label' => array
		(
			'fields'      => array('zeit', 'wertung', 'abweichung'),
			'showColumns' => true,
		)
	),

	'fields' => array
	(
		'id' => array
		(
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		),
		'pid' => array
		(
			'foreignKey' => 'tl_schachcomputer_spieler.id',
			'sql'        => "int(10) unsigned NOT NULL default '0'",
		),
		'tstamp' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'partie' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'zeit' => array
		(
			'eval' => array('rgxp' => 'datim'),
			'sql'  => "int(10) unsigned NOT NULL default '0'",
		),
		'wertung' => array
		(
			'sql' => "double NOT NULL default '0'",
		),
		'abweichung' => array
		(
			'sql' => "double NOT NULL default '0'",
		)
	)
);
