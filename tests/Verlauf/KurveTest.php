<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Verlauf;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachcomputerBundle\Verlauf\Kurve;

/**
 * Prüft die SVG-Kurve des Wertungsverlaufs.
 */
class KurveTest extends TestCase
{
	/**
	 * Ohne Punkte gibt es keine Kurve.
	 */
	public function testLeer(): void
	{
		$this->assertSame('', Kurve::svg(array(), 'Blitz'));
	}

	/**
	 * Ein einzelner Punkt wird als Kreis gezeichnet.
	 */
	public function testEinPunkt(): void
	{
		$svg = Kurve::svg(array(array('zeit' => 1, 'wertung' => 1523.4)), 'Blitz');

		$this->assertStringContainsString('<circle class="kurve"', $svg);
		$this->assertStringNotContainsString('<polyline', $svg);
	}

	/**
	 * Mehrere Punkte ergeben eine Linie; die Achse reicht über volle Fünfziger.
	 */
	public function testLinieUndAchse(): void
	{
		$svg = Kurve::svg(array(
			array('zeit' => 1, 'wertung' => 1480.0),
			array('zeit' => 2, 'wertung' => 1623.0),
			array('zeit' => 3, 'wertung' => 1590.0),
		), 'Blitz & Co');

		$this->assertMatchesRegularExpression('/<polyline class="kurve" points="48\.0,[\d.]+ 318\.0,[\d.]+ 588\.0,[\d.]+"\/>/', $svg);
		$this->assertStringContainsString('>1650</text>', $svg);
		$this->assertStringContainsString('>1450</text>', $svg);
		$this->assertStringContainsString('aria-label="Blitz &amp; Co"', $svg);
		$this->assertNotFalse(simplexml_load_string($svg), 'gültiges XML');
	}

	/**
	 * Lange Verläufe werden ausgedünnt; Anfang und Ende bleiben.
	 */
	public function testAusduennen(): void
	{
		$punkte = array();

		for ($i = 0; $i < 1000; ++$i) {
			$punkte[] = array('zeit' => $i, 'wertung' => 1500.0 + $i);
		}

		$duenn = Kurve::ausduennen($punkte, 300);

		$this->assertCount(300, $duenn);
		$this->assertSame(0, $duenn[0]['zeit']);
		$this->assertSame(999, $duenn[299]['zeit']);

		preg_match('/points="([^"]+)"/', Kurve::svg($punkte, 'Blitz'), $treffer);
		$this->assertCount(300, explode(' ', $treffer[1]));
	}
}
