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
use Schachbulle\ContaoSchachcomputerBundle\Backend\PartieBeschriftung;

/**
 * Prüft die Spalten der Backend-Partienliste.
 */
class PartieBeschriftungTest extends TestCase
{
	private string $zeitzone;

	/**
	 * Legt Zeitzone und Contao-Format fest, damit der Text vorhersagbar ist.
	 */
	protected function setUp(): void
	{
		$this->zeitzone = date_default_timezone_get();
		date_default_timezone_set('Europe/Berlin');
		$GLOBALS['TL_CONFIG']['datimFormat'] = 'd.m.Y H:i';
	}

	/**
	 * Stellt Zeitzone und Contao-Einstellungen wieder her.
	 */
	protected function tearDown(): void
	{
		date_default_timezone_set($this->zeitzone);
		unset($GLOBALS['TL_CONFIG']['datimFormat'], $GLOBALS['TL_DCA']);
	}

	/**
	 * Der Beginn erscheint als Datum mit Uhrzeit statt als Zeitstempel, die
	 * übrigen Spalten bleiben – passend zur Spaltenfolge der echten DCA.
	 */
	public function testBeginnAlsDatumMitUhrzeit(): void
	{
		$GLOBALS['TL_DCA'] = array();
		$GLOBALS['TL_LANG'] = array();
		include __DIR__.'/../../src/Resources/contao/dca/tl_schachcomputer_partie.php';
		unset($GLOBALS['TL_LANG']);
		$felder = $GLOBALS['TL_DCA']['tl_schachcomputer_partie']['list']['label']['fields'];

		$zeile = array('beginn' => mktime(2, 10, 0, 10, 1, 2026), 'memberId' => 0, 'klasse' => 'blitz', 'stufe' => 900, 'ergebnis' => '*', 'grund' => 'erster_zug');
		// So übergibt Contao die Spalten: je Feld der Liste ein Text, in dieser Reihenfolge
		$spalten = array_map(static fn (string $feld): string => (string) $zeile[$feld], $felder);

		$ergebnis = (new PartieBeschriftung())->beschriften($zeile, '', $this->createMock(DataContainer::class), $spalten);

		$spalte = static fn (string $feld): string => $ergebnis[array_search($feld, $felder, true)];
		$this->assertSame('01.10.2026 02:10', $spalte('beginn'));
		$this->assertSame('blitz', $spalte('klasse'));
		$this->assertSame('900', $spalte('stufe'));
		$this->assertSame('*', $spalte('ergebnis'));
	}

	/**
	 * Ohne Zeitstempel (0) steht ein Strich, wie bei Contao selbst.
	 */
	public function testFehlenderZeitstempelAlsStrich(): void
	{
		$GLOBALS['TL_DCA']['tl_schachcomputer_partie']['list']['label']['fields'] = array('beginn', 'klasse');

		$ergebnis = (new PartieBeschriftung())->beschriften(array('beginn' => 0, 'klasse' => 'blitz'), '', $this->createMock(DataContainer::class), array('0', 'blitz'));

		$this->assertSame(array('-', 'blitz'), $ergebnis);
	}
}
