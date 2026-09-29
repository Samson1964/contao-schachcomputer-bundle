<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

/*
 * Paletten und Felder der Frontend-Module.
 *
 * Verwendet werden nur Kernfelder, die es in Contao 4.13 und 5.7
 * gleichermaßen gibt; „guests" und „space" fehlen in Contao 5 und würden die
 * Palette dort abbrechen lassen.
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['schachcomputer_spielen']
	= '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},cssID';
