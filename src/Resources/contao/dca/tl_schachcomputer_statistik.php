<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\DC_Table;

/*
 * Tabelle tl_schachcomputer_statistik: Zählerstände je Stunde.
 *
 * Eine Zeile je Tag, Stunde, Art und Spielergruppe; jedes Ereignis erhöht
 * nur den Zähler der passenden Zeile. Die Tabelle bleibt dadurch klein –
 * höchstens 24 × 7 × 2 Zeilen am Tag. Arten siehe Statistik\Statistik::ARTEN.
 * Keine Backend-Maske; angezeigt wird die Statistik unter Schachcomputer →
 * Partien → Statistik.
 */
$GLOBALS['TL_DCA']['tl_schachcomputer_statistik'] = array
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
				'datum,stunde,art,gast' => 'unique',
			)
		)
	),

	'fields' => array
	(
		'id' => array
		(
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		),
		// Tag als Zahl JJJJMMTT (Serverzeit), etwa 20260929
		'datum' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		// Stunde 0 bis 23
		'stunde' => array
		(
			'sql' => "smallint(5) unsigned NOT NULL default '0'",
		),
		'art' => array
		(
			'sql' => "varchar(16) NOT NULL default ''",
		),
		// '1' für Gäste, '' für Mitglieder
		'gast' => array
		(
			'sql' => "char(1) NOT NULL default ''",
		),
		'anzahl' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		)
	)
);
