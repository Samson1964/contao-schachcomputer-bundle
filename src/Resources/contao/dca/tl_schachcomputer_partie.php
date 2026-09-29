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
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Klassen;

/*
 * Tabelle tl_schachcomputer_partie: alle Partien, gewertet und Übung.
 *
 * Aufbau siehe Partie\Partie. zugnummer ist die Zahl der Halbzüge und dient
 * beim Speichern als Versionsnummer (bedingtes UPDATE), damit zwei
 * gleichzeitige Anfragen einander nicht überschreiben. Uhrzeiten stehen in
 * Millisekunden (restzeit, uhrSeit, zeiten), Zeitpunkte in Sekunden.
 * Im Backend nur lesen, als PGN herunterladen und löschen.
 */
$GLOBALS['TL_DCA']['tl_schachcomputer_partie'] = array
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
				'id'                => 'primary',
				'memberId,ende'     => 'index',
				'gast'              => 'index',
				'status'            => 'index',
				// Für die minütliche Nachhol-Abfrage in Partiedienst::allePruefen()
				// (memberId>0 AND gewertet=1 AND status='beendet' AND verrechnet=0)
				'verrechnet,status' => 'index',
			)
		)
	),

	'list' => array
	(
		'sorting' => array
		(
			'mode'        => DataContainer::MODE_SORTABLE,
			'fields'      => array('beginn'),
			'flag'        => DataContainer::SORT_DESC,
			'panelLayout' => 'filter;sort,limit',
		),
		'label' => array
		(
			'fields'      => array('beginn', 'memberId', 'klasse', 'stufe', 'ergebnis', 'grund'),
			'showColumns' => true,
		),
		'global_operations' => array
		(
			'statistik' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['statistik'],
				'href'  => 'key=statistik',
				'class' => 'header_statistik',
				'icon'  => 'bundles/contaoschachcomputer/statistik.svg',
			)
		),
		'operations' => array
		(
			'pgn' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['pgn'],
				'href'  => 'key=pgn',
				'icon'  => 'theme_export.svg',
			),
			'delete' => array
			(
				'label'      => &$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['delete'],
				'href'       => 'act=delete',
				'icon'       => 'delete.svg',
				'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false;Backend.getScrollOffset()"',
			),
			'show' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['show'],
				'href'  => 'act=show',
				'icon'  => 'show.svg',
			)
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
		'memberId' => array
		(
			'filter'     => true,
			'foreignKey' => "tl_member.CONCAT(firstname, ' ', lastname)",
			'sql'        => "int(10) unsigned NOT NULL default '0'",
		),
		'gast' => array
		(
			'sql' => "varchar(32) NOT NULL default ''",
		),
		'gewertet' => array
		(
			'filter'    => true,
			'inputType' => 'checkbox',
			'sql'       => "tinyint(1) unsigned NOT NULL default '0'",
		),
		'bedenkzeit' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'minuten' => array
		(
			'sql' => "smallint(5) unsigned NOT NULL default '0'",
		),
		'inkrement' => array
		(
			'sql' => "smallint(5) unsigned NOT NULL default '0'",
		),
		'klasse' => array
		(
			'filter'    => true,
			'options'   => Klassen::ALLE,
			'reference' => &$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['klassen'],
			'sql'       => "varchar(8) NOT NULL default ''",
		),
		'stufe' => array
		(
			'sorting' => true,
			'sql'     => "smallint(5) unsigned NOT NULL default '0'",
		),
		'farbe' => array
		(
			'sql' => "char(1) NOT NULL default 'w'",
		),
		'status' => array
		(
			'filter'    => true,
			'reference' => &$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['stati'],
			'sql'       => "varchar(12) NOT NULL default 'laeuft'",
		),
		'ergebnis' => array
		(
			'sql' => "varchar(8) NOT NULL default ''",
		),
		'grund' => array
		(
			'filter'    => true,
			'reference' => &$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['gruende'],
			'sql'       => "varchar(16) NOT NULL default ''",
		),
		'zuege' => array
		(
			'sql' => 'text NULL',
		),
		'zeiten' => array
		(
			'sql' => 'text NULL',
		),
		'zugnummer' => array
		(
			'sql' => "smallint(5) unsigned NOT NULL default '0'",
		),
		'restzeit' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'uhrSeit' => array
		(
			'sql' => "bigint(20) unsigned NOT NULL default '0'",
		),
		'beginn' => array
		(
			'sorting' => true,
			'flag'    => DataContainer::SORT_DESC,
			'eval'    => array('rgxp' => 'datim'),
			'sql'     => "int(10) unsigned NOT NULL default '0'",
		),
		'ende' => array
		(
			'eval' => array('rgxp' => 'datim'),
			'sql'  => "int(10) unsigned NOT NULL default '0'",
		),
		'verrechnet' => array
		(
			'sql' => "tinyint(1) unsigned NOT NULL default '0'",
		),
		'wertungVorher' => array
		(
			'sql' => "double NOT NULL default '0'",
		),
		'wertungNachher' => array
		(
			'sql' => "double NOT NULL default '0'",
		)
	)
);
