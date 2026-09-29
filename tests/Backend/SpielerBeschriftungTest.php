<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Backend;

use Contao\DataContainer;
use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachcomputerBundle\Backend\SpielerBeschriftung;

/**
 * Prüft die Spalten der Backend-Spielerliste.
 */
class SpielerBeschriftungTest extends TestCase
{
	/**
	 * Wertung, Abweichung und Höchstwert erscheinen ganzzahlig gerundet, die
	 * übrigen Spalten unverändert – passend zur Spaltenfolge der echten DCA.
	 */
	public function testWertungenGanzzahlig(): void
	{
		$GLOBALS['TL_DCA'] = array();
		$GLOBALS['TL_LANG'] = array();
		include __DIR__.'/../../src/Resources/contao/dca/tl_schachcomputer_spieler.php';
		$felder = $GLOBALS['TL_DCA']['tl_schachcomputer_spieler']['list']['label']['fields'];

		$zeile = array('memberId' => 7, 'klasse' => 'blitz', 'wertung' => 1712.5678, 'abweichung' => 63.4999, 'partien' => 30, 'hoechstwert' => 1750.5);
		// So übergibt Contao die Spalten: je Feld der Liste ein Text, in dieser Reihenfolge
		$spalten = array_map(static fn (string $feld): string => 'memberId' === $feld ? 'Anna Alt' : (string) $zeile[$feld], $felder);

		$ergebnis = (new SpielerBeschriftung())->beschriften($zeile, 'Anna Alt', $this->createMock(DataContainer::class), $spalten);
		unset($GLOBALS['TL_DCA'], $GLOBALS['TL_LANG']);

		$spalte = static fn (string $feld): string => $ergebnis[array_search($feld, $felder, true)];
		$this->assertSame('1713', $spalte('wertung'));
		$this->assertSame('63', $spalte('abweichung'));
		$this->assertSame('1751', $spalte('hoechstwert'));
		$this->assertSame('30', $spalte('partien'));
		$this->assertSame('Anna Alt', $spalte('memberId'));
		$this->assertSame('blitz', $spalte('klasse'));
	}
}
