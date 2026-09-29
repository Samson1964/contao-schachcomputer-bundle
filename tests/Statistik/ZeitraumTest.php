<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Statistik;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachcomputerBundle\Statistik\Zeitraum;

/**
 * Prüft die Zeiträume der Backend-Statistik.
 */
class ZeitraumTest extends TestCase
{
	private const MONATE = array('Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember');

	private string $zeitzone;

	/**
	 * Setzt eine feste Zeitzone.
	 */
	protected function setUp(): void
	{
		$this->zeitzone = date_default_timezone_get();
		date_default_timezone_set('Europe/Berlin');
	}

	/**
	 * Stellt die Zeitzone wieder her.
	 */
	protected function tearDown(): void
	{
		date_default_timezone_set($this->zeitzone);
	}

	/**
	 * Gültige Daten gelten, ungültige, leere und zukünftige werden zu heute.
	 */
	public function testZeitpunkt(): void
	{
		$jetzt = (int) strtotime('2026-09-29 08:15');

		$this->assertSame('2026-02-28 12:00', date('Y-m-d H:i', Zeitraum::zeitpunkt('2026-02-28', $jetzt)));
		$this->assertSame('2026-09-29 12:00', date('Y-m-d H:i', Zeitraum::zeitpunkt('2026-02-30', $jetzt)));
		$this->assertSame('2026-09-29 12:00', date('Y-m-d H:i', Zeitraum::zeitpunkt('', $jetzt)));
		$this->assertSame('2026-09-29 12:00', date('Y-m-d H:i', Zeitraum::zeitpunkt('2027-01-01', $jetzt)));
	}

	/**
	 * Grenzen von Tag, Monat und Jahr; der Tag der Zeitumstellung hat 25 Stunden.
	 *
	 * @dataProvider grenzfaelle
	 *
	 * @param array{0: int, 1: int, 2: string, 3: string} $erwartet
	 */
	public function testGrenzen(string $ebene, string $datum, array $erwartet): void
	{
		[$von, $bis, $beginn, $ende] = Zeitraum::grenzen($ebene, (int) strtotime($datum.' 12:00'));

		$this->assertSame($erwartet, array($von, $bis, date('Y-m-d H:i', $beginn), date('Y-m-d H:i', $ende)));
	}

	/**
	 * Liefert Ebene, Datum und erwartete Grenzen.
	 *
	 * @return array<string, array{string, string, array{0: int, 1: int, 2: string, 3: string}}>
	 */
	public function grenzfaelle(): array
	{
		return array(
			'Tag'             => array('tag', '2026-09-29', array(20260929, 20260929, '2026-09-29 00:00', '2026-09-30 00:00')),
			'Zeitumstellung'  => array('tag', '2026-10-25', array(20261025, 20261025, '2026-10-25 00:00', '2026-10-26 00:00')),
			'Monat'           => array('monat', '2026-02-10', array(20260201, 20260231, '2026-02-01 00:00', '2026-03-01 00:00')),
			'Dezember'        => array('monat', '2026-12-31', array(20261201, 20261231, '2026-12-01 00:00', '2027-01-01 00:00')),
			'Jahr'            => array('jahr', '2026-06-15', array(20260101, 20261231, '2026-01-01 00:00', '2027-01-01 00:00')),
		);
	}

	/**
	 * Die Diagrammeinheit ist eine Ebene feiner.
	 */
	public function testEinheit(): void
	{
		$this->assertSame('stunde', Zeitraum::einheit('tag'));
		$this->assertSame('tag', Zeitraum::einheit('monat'));
		$this->assertSame('monat', Zeitraum::einheit('jahr'));
	}

	/**
	 * Die Achse ist vollständig: 24 Stunden, alle Tage des Monats, 12 Monate.
	 */
	public function testAchse(): void
	{
		$februar = (int) strtotime('2028-02-10 12:00');

		$this->assertCount(24, Zeitraum::achse('tag', $februar, self::MONATE));
		$this->assertCount(29, Zeitraum::achse('monat', $februar, self::MONATE));
		$this->assertSame('29.02.', Zeitraum::achse('monat', $februar, self::MONATE)[29]);
		$this->assertSame('Mär', Zeitraum::achse('jahr', $februar, self::MONATE)[3]);
	}

	/**
	 * Blättern: Monate ohne Überlauf, Tage über den Monatswechsel, Jahre.
	 */
	public function testVerschieben(): void
	{
		$januar31 = (int) strtotime('2026-01-31 12:00');

		$this->assertSame('2026-02-01 12:00', date('Y-m-d H:i', Zeitraum::verschieben('monat', $januar31, 1)));
		$this->assertSame('2026-02-01 12:00', date('Y-m-d H:i', Zeitraum::verschieben('tag', $januar31, 1)));
		$this->assertSame('2025-01-01 12:00', date('Y-m-d H:i', Zeitraum::verschieben('jahr', $januar31, -1)));
	}

	/**
	 * Bezeichnungen der drei Ebenen.
	 */
	public function testBezeichnung(): void
	{
		$zeitpunkt = (int) strtotime('2026-09-29 12:00');

		$this->assertSame('29.09.2026', Zeitraum::bezeichnung('tag', $zeitpunkt, self::MONATE));
		$this->assertSame('September 2026', Zeitraum::bezeichnung('monat', $zeitpunkt, self::MONATE));
		$this->assertSame('2026', Zeitraum::bezeichnung('jahr', $zeitpunkt, self::MONATE));
	}
}
