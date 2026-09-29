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
 * Tabelle tl_schachcomputer_stichtag: die eingefrorenen Monatsranglisten.
 *
 * monat ist der Monat des Stichtags (JJJJ-MM; „2026-10" = Stand 1. Oktober
 * 2026, 0 Uhr). platzVormonat 0 heißt: im Vormonat nicht in der Liste.
 * Geschrieben vom StichtagCron, keine Backend-Ansicht in 1.0.
 */
$GLOBALS['TL_DCA']['tl_schachcomputer_stichtag'] = array
(
	'config' => array
	(
		'dataContainer' => DC_Table::class,
		'closed'        => true,
		'notEditable'   => true,
		'notCopyable'   => true,
		'sql'           => array
		(
			'keys' => array
			(
				'id'                    => 'primary',
				'monat,klasse,memberId' => 'unique',
			)
		)
	),

	'list' => array
	(
		'sorting' => array
		(
			'mode'   => DataContainer::MODE_SORTED,
			'fields' => array('monat', 'klasse', 'platz'),
			'flag'   => DataContainer::SORT_ASC,
		),
		'label' => array
		(
			'fields'      => array('monat', 'klasse', 'platz', 'memberId', 'wertung'),
			'showColumns' => true,
		)
	),

	'fields' => array
	(
		'id' => array
		(
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		),
		'tstamp' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'monat' => array
		(
			'sql' => "varchar(7) NOT NULL default ''",
		),
		'klasse' => array
		(
			'sql' => "varchar(8) NOT NULL default ''",
		),
		'memberId' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'platz' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'wertung' => array
		(
			'sql' => "smallint(5) unsigned NOT NULL default '0'",
		),
		'partien' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'platzVormonat' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'wertungVormonat' => array
		(
			'sql' => "smallint(5) unsigned NOT NULL default '0'",
		)
	)
);
