<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Rangliste;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachcomputerBundle\Rangliste\Anzeigename;
use Schachbulle\ContaoSchachcomputerBundle\Rangliste\Platzierung;

/**
 * Prüft Platzvergabe und öffentliche Namen.
 */
class PlatzierungTest extends TestCase
{
	/**
	 * Gleiche Werte teilen sich den Platz, danach wird übersprungen.
	 */
	public function testGleicheWerteTeilenDenPlatz(): void
	{
		$liste = Platzierung::vergeben(array(
			array('wertung' => 1700),
			array('wertung' => 1650),
			array('wertung' => 1650),
			array('wertung' => 1600),
		), 'wertung');

		$this->assertSame(array(1, 2, 2, 4), array_column($liste, 'platz'));
	}

	/**
	 * Eine leere Liste bleibt leer.
	 */
	public function testLeereListe(): void
	{
		$this->assertSame(array(), Platzierung::vergeben(array(), 'wertung'));
	}

	/**
	 * Aufstieg ist Plus, Neueinsteiger bekommen den eigenen Text.
	 */
	public function testVeraenderung(): void
	{
		$this->assertSame('+2 / +15', Platzierung::veraenderung(3, 1715, 5, 1700, 'neu'));
		$this->assertSame('−1 / −8', Platzierung::veraenderung(4, 1692, 3, 1700, 'neu'));
		$this->assertSame('±0 / ±0', Platzierung::veraenderung(1, 1800, 1, 1800, 'neu'));
		$this->assertSame('neu', Platzierung::veraenderung(7, 1600, 0, 0, 'neu'));
	}

	/**
	 * Der öffentliche Name ist „Vorname N.".
	 */
	public function testAnzeigename(): void
	{
		$this->assertSame('Max M.', Anzeigename::kurz('Max', 'Mustermann'));
		$this->assertSame('Max', Anzeigename::kurz('Max', ''));
		$this->assertSame('–', Anzeigename::kurz('', null));
	}
}
