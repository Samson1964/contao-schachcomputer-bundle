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
 * Tabelle tl_schachcomputer_bedenkzeit: die Bedenkzeiten, aus denen Spieler
 * für gewertete Partien wählen.
 *
 * Jede Bedenkzeit gehört zu einer Wertungsklasse. Beim Partiestart werden
 * Minuten, Gutschrift und Klasse in die Partie kopiert; spätere Änderungen
 * hier verfälschen deshalb keine alten Partien.
 */
$GLOBALS['TL_DCA']['tl_schachcomputer_bedenkzeit'] = array
(
	'config' => array
	(
		'dataContainer'    => DC_Table::class,
		'enableVersioning' => true,
		'sql'              => array
		(
			'keys' => array
			(
				'id'        => 'primary',
				'published' => 'index',
			)
		)
	),

	'list' => array
	(
		'sorting' => array
		(
			'mode'        => DataContainer::MODE_SORTED,
			'fields'      => array('klasse', 'minuten', 'inkrement'),
			'flag'        => DataContainer::SORT_ASC,
			'panelLayout' => 'filter;search,limit',
		),
		'label' => array
		(
			'fields'      => array('name', 'klasse', 'minuten', 'inkrement'),
			'showColumns' => true,
		),
		'global_operations' => array
		(
			'all' => array
			(
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
			)
		),
		'operations' => array
		(
			'edit' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['edit'],
				'href'  => 'act=edit',
				'icon'  => 'edit.svg',
			),
			'copy' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['copy'],
				'href'  => 'act=copy',
				'icon'  => 'copy.svg',
			),
			'delete' => array
			(
				'label'      => &$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['delete'],
				'href'       => 'act=delete',
				'icon'       => 'delete.svg',
				'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false;Backend.getScrollOffset()"',
			),
			'toggle' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['toggle'],
				'href'  => 'act=toggle&amp;field=published',
				'icon'  => 'visible.svg',
			),
			'show' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['show'],
				'href'  => 'act=show',
				'icon'  => 'show.svg',
			)
		)
	),

	'palettes' => array
	(
		'default' => '{bedenkzeit_legend},name,klasse;{zeit_legend},minuten,inkrement;{publish_legend},published',
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
		'name' => array
		(
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 32, 'tl_class' => 'w50'),
			'sql'       => "varchar(32) NOT NULL default ''",
		),
		'klasse' => array
		(
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'select',
			'options'   => Klassen::ALLE,
			'reference' => &$GLOBALS['TL_LANG']['tl_schachcomputer_bedenkzeit']['klassen'],
			'eval'      => array('mandatory' => true, 'tl_class' => 'w50'),
			'sql'       => "varchar(8) NOT NULL default ''",
		),
		'minuten' => array
		(
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'rgxp' => 'natural', 'minval' => 1, 'maxval' => 180, 'maxlength' => 3, 'tl_class' => 'w50'),
			'sql'       => "smallint(5) unsigned NOT NULL default '0'",
		),
		'inkrement' => array
		(
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('rgxp' => 'natural', 'maxval' => 180, 'maxlength' => 3, 'tl_class' => 'w50'),
			'sql'       => "smallint(5) unsigned NOT NULL default '0'",
		),
		'published' => array
		(
			'exclude'   => true,
			'filter'    => true,
			'toggle'    => true,
			'inputType' => 'checkbox',
			'eval'      => array('doNotCopy' => true),
			'sql'       => "char(1) NOT NULL default ''",
		)
	)
);
