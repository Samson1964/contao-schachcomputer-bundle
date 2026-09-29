<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachcomputerBundle\Engine\Stufen;

/**
 * Prüft die Liste der Spielstufen und ihre Engine-Einstellungen.
 */
class StufenTest extends TestCase
{
	/**
	 * Zwanzig Stufen von 600 bis 2500 in 100er-Schritten.
	 */
	public function testAlleStufen(): void
	{
		$alle = Stufen::alle();

		$this->assertCount(20, $alle);
		$this->assertSame(600, $alle[0]);
		$this->assertSame(2500, $alle[19]);
	}

	/**
	 * Nur volle Hunderter zwischen 600 und 2500 sind Stufen.
	 *
	 * @dataProvider gueltigkeiten
	 */
	public function testGueltig(int $stufe, bool $erwartet): void
	{
		$this->assertSame($erwartet, Stufen::gueltig($stufe));
	}

	/**
	 * Liefert Zahlen und ob sie eine Stufe sind.
	 *
	 * @return array<string, array{int, bool}>
	 */
	public function gueltigkeiten(): array
	{
		return array(
			'untere Grenze'  => array(600, true),
			'obere Grenze'   => array(2500, true),
			'darunter'       => array(500, false),
			'darüber'        => array(2600, false),
			'kein Hunderter' => array(1550, false),
		);
	}

	/**
	 * Der Vorschlag rundet auf die nächste Stufe und bleibt im Bereich.
	 *
	 * @dataProvider vorschlaege
	 */
	public function testNaechsteStufe(float $wertung, int $erwartet): void
	{
		$this->assertSame($erwartet, Stufen::naechste($wertung));
	}

	/**
	 * Liefert Wertungen und die erwartete Stufe.
	 *
	 * @return array<string, array{float, int}>
	 */
	public function vorschlaege(): array
	{
		return array(
			'Startwertung' => array(1500.0, 1500),
			'abrunden'     => array(1549.9, 1500),
			'aufrunden'    => array(1550.0, 1600),
			'sehr schwach' => array(250.0, 600),
			'sehr stark'   => array(2900.0, 2500),
		);
	}

	/**
	 * Ab 1400 steuert UCI_Elo die Stärke, ohne Tiefe und ohne Zufall.
	 */
	public function testGeeichteStufeNutztUciElo(): void
	{
		$einstellungen = Stufen::einstellungen(1400);

		$this->assertSame(1400, $einstellungen['uciElo']);
		$this->assertNull($einstellungen['skill']);
		$this->assertNull($einstellungen['tiefe']);
		$this->assertSame(0.0, $einstellungen['zufall']);
		$this->assertSame(1000, $einstellungen['zeitMin']);
		$this->assertSame(2000, $einstellungen['zeitMax']);
	}

	/**
	 * Unter 1400 gelten Skill Level 0, begrenzte Tiefe und Zufallszüge.
	 */
	public function testNachgebauteStufeNutztTiefeUndZufall(): void
	{
		$schwach = Stufen::einstellungen(600);
		$this->assertNull($schwach['uciElo']);
		$this->assertSame(0, $schwach['skill']);
		$this->assertSame(1, $schwach['tiefe']);
		$this->assertSame(0.4, $schwach['zufall']);

		$fast = Stufen::einstellungen(1300);
		$this->assertSame(5, $fast['tiefe']);
		$this->assertSame(0.05, $fast['zufall']);
	}

	/**
	 * Der Anteil der Zufallszüge fällt mit jeder höheren Stufe oder bleibt gleich.
	 */
	public function testZufallSinktMitDerStufe(): void
	{
		$vorher = 1.0;

		foreach (Stufen::alle() as $stufe) {
			$zufall = Stufen::einstellungen($stufe)['zufall'];
			$this->assertLessThanOrEqual($vorher, $zufall, "Stufe $stufe");
			$vorher = $zufall;
		}
	}

	/**
	 * Eine Zahl, die keine Stufe ist, wird abgewiesen.
	 */
	public function testUngueltigeStufeWirftFehler(): void
	{
		$this->expectException(\InvalidArgumentException::class);

		Stufen::einstellungen(1550);
	}
}
