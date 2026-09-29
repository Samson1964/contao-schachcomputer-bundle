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
 * Tabelle tl_schachcomputer_spieler: Glicko-2-Wertung und Zähler je Mitglied
 * und Wertungsklasse.
 *
 * Gäste stehen nicht hier; ihre Wertung lebt nur in der Sitzung. Geschrieben
 * wird ausschließlich vom Wertungsdienst. Im Backend nur lesen und löschen;
 * Löschen entfernt über ctable auch den Wertungsverlauf (etwa nach einer
 * Manipulation). Die Wertung wird ungerundet gespeichert.
 */
$GLOBALS['TL_DCA']['tl_schachcomputer_spieler'] = array
(
	'config' => array
	(
		'dataContainer' => DC_Table::class,
		'ctable'        => array('tl_schachcomputer_verlauf'),
		'closed'        => true,
		'notEditable'   => true,
		'notCopyable'   => true,
		'sql'           => array
		(
			'keys' => array
			(
				'id'                 => 'primary',
				'memberId,klasse'    => 'unique',
				'klasse,wertung'     => 'index',
				'klasse,hoechstwert' => 'index',
			)
		)
	),

	'list' => array
	(
		'sorting' => array
		(
			'mode'        => DataContainer::MODE_SORTABLE,
			'fields'      => array('wertung'),
			'flag'        => DataContainer::SORT_DESC,
			'panelLayout' => 'filter;sort,limit',
		),
		// Wertung, Abweichung und Höchstwert rundet Backend\SpielerBeschriftung (label_callback per Attribut)
		'label' => array
		(
			'fields'      => array('memberId', 'klasse', 'wertung', 'abweichung', 'partien', 'hoechstwert'),
			'showColumns' => true,
		),
		'operations' => array
		(
			'delete' => array
			(
				'label'      => &$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['delete'],
				'href'       => 'act=delete',
				'icon'       => 'delete.svg',
				'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false;Backend.getScrollOffset()"',
			),
			'show' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['show'],
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
			'foreignKey' => "tl_member.CONCAT(firstname, ' ', lastname)",
			'sql'        => "int(10) unsigned NOT NULL default '0'",
		),
		'klasse' => array
		(
			'filter'    => true,
			'options'   => Klassen::ALLE,
			'reference' => &$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['klassen'],
			'sql'       => "varchar(8) NOT NULL default ''",
		),
		'wertung' => array
		(
			'sorting' => true,
			'sql'     => "double NOT NULL default '1500'",
		),
		'abweichung' => array
		(
			'sql' => "double NOT NULL default '350'",
		),
		'volatilitaet' => array
		(
			'sql' => "double NOT NULL default '0.06'",
		),
		'partien' => array
		(
			'sorting' => true,
			'sql'     => "int(10) unsigned NOT NULL default '0'",
		),
		'siege' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'remis' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'niederlagen' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		// Höchste gesicherte Wertung (Abweichung höchstens 110), 0 solange es keine gab
		'hoechstwert' => array
		(
			'sorting' => true,
			'sql'     => "double NOT NULL default '0'",
		),
		'hoechstwertDatum' => array
		(
			'eval' => array('rgxp' => 'datim'),
			'sql'  => "int(10) unsigned NOT NULL default '0'",
		),
		'letztePartie' => array
		(
			'sorting' => true,
			'flag'    => DataContainer::SORT_DESC,
			'eval'    => array('rgxp' => 'datim'),
			'sql'     => "int(10) unsigned NOT NULL default '0'",
		)
	)
);
