<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Partie;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Uhr;

/**
 * Prüft die Rechenregeln der Schachuhr.
 */
class UhrTest extends TestCase
{
	/**
	 * Die Browsermessung zählt nur innerhalb des Ausgleichsfensters.
	 *
	 * @dataProvider abzuege
	 */
	public function testAbzug(int $server, ?int $browser, int $erwartet): void
	{
		$this->assertSame($erwartet, Uhr::abzug($server, $browser));
	}

	/**
	 * Liefert Server- und Browsermessung und den erwarteten Abzug.
	 *
	 * @return array<string, array{int, int|null, int}>
	 */
	public function abzuege(): array
	{
		return array(
			'ohne Browsermessung'    => array(5000, null, 5000),
			'negative Messung'       => array(5000, -3, 5000),
			'im Fenster'             => array(5000, 4300, 4300),
			'genau am Fensterrand'   => array(5000, 4000, 4000),
			'zu wenig gemeldet'      => array(5000, 1000, 4000),
			'mehr als der Server'    => array(5000, 7000, 5000),
			'kurzer Zug'             => array(300, 100, 100),
			'negative Servermessung' => array(-20, 50, 0),
		);
	}

	/**
	 * Nach dem Zug wird abgezogen und gutgeschrieben.
	 */
	public function testNachZugMitGutschrift(): void
	{
		$this->assertSame(178000, Uhr::nachZug(180000, 4000, 2000));
	}

	/**
	 * Genau aufgebraucht ist noch rechtzeitig, mehr nicht.
	 */
	public function testNachZugAmLimit(): void
	{
		$this->assertSame(2000, Uhr::nachZug(4000, 4000, 2000));
		$this->assertNull(Uhr::nachZug(4000, 4001, 2000));
	}

	/**
	 * Die angezeigte Zeit läuft ab und bleibt bei 0 stehen.
	 */
	public function testVerbleibend(): void
	{
		$this->assertSame(60000, Uhr::verbleibend(60000, 1000, 1000));
		$this->assertSame(50000, Uhr::verbleibend(60000, 1000, 11000));
		$this->assertSame(0, Uhr::verbleibend(60000, 1000, 99000));
		$this->assertSame(60000, Uhr::verbleibend(60000, 5000, 1000));
	}

	/**
	 * Abgelaufen erst nach Restzeit plus Ausgleich.
	 */
	public function testAbgelaufen(): void
	{
		$this->assertFalse(Uhr::abgelaufen(10000, 0, 11000));
		$this->assertTrue(Uhr::abgelaufen(10000, 0, 11001));
	}
}
