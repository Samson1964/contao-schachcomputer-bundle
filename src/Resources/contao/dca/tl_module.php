<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

use Schachbulle\ContaoSchachcomputerBundle\Module\RanglisteModule;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Klassen;

/*
 * Paletten und Felder der Frontend-Module.
 *
 * Verwendet werden nur Kernfelder, die es in Contao 4.13 und 5.7
 * gleichermaßen gibt; „guests" und „space" fehlen in Contao 5 und würden die
 * Palette dort abbrechen lassen.
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['schachcomputer_spielen']
	= '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_module']['palettes']['schachcomputer_rangliste']
	= '{title_legend},name,headline,type;{schachcomputer_legend},schachcomputerModus,schachcomputerKlasse,schachcomputerAnzahl;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_module']['palettes']['schachcomputer_partien']
	= '{title_legend},name,headline,type;{schachcomputer_legend},schachcomputerAnzahl;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_module']['fields']['schachcomputerModus'] = array
(
	'exclude'   => true,
	'inputType' => 'select',
	'options'   => RanglisteModule::MODI,
	'reference' => &$GLOBALS['TL_LANG']['tl_module']['schachcomputerModi'],
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "varchar(8) NOT NULL default 'aktuell'",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['schachcomputerKlasse'] = array
(
	'exclude'   => true,
	'inputType' => 'select',
	'options'   => Klassen::ALLE,
	'reference' => &$GLOBALS['TL_LANG']['tl_module']['schachcomputerKlassen'],
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "varchar(8) NOT NULL default 'blitz'",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['schachcomputerAnzahl'] = array
(
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'natural', 'minval' => 1, 'maxlength' => 4, 'tl_class' => 'w50'),
	'sql'       => "smallint(5) unsigned NOT NULL default '20'",
);
