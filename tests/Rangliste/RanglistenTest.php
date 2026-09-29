<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Rangliste;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachcomputerBundle\Rangliste\Ranglisten;
use Schachbulle\ContaoSchachcomputerBundle\Rangliste\Stichtagsliste;
use Schachbulle\ContaoSchachcomputerBundle\Tests\Datenbank;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Glicko2;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsrechner;

/**
 * Prüft aktuelle, ewige und Monatsrangliste gegen SQLite.
 */
class RanglistenTest extends TestCase
{
	private const TAG = 86400;

	/**
	 * 1. Oktober 2026, 0 Uhr in Berlin.
	 */
	private const STICHTAG = 1790805600;

	private Connection $db;

	private string $zeitzone;

	/**
	 * Legt Datenbank und feste Zeitzone an.
	 */
	protected function setUp(): void
	{
		$this->zeitzone = date_default_timezone_get();
		date_default_timezone_set('Europe/Berlin');
		$this->db = Datenbank::verbindung();
	}

	/**
	 * Stellt die Zeitzone wieder her.
	 */
	protected function tearDown(): void
	{
		date_default_timezone_set($this->zeitzone);
	}

	/**
	 * Die aktuelle Liste zeigt nur gesicherte, aktive Mitglieder, gerundet und platziert.
	 */
	public function testAktuell(): void
	{
		$jetzt = self::STICHTAG;
		$this->spieler(Datenbank::mitglied($this->db, 'Anna', 'Alt'), 1712.4, 60, 30, $jetzt - self::TAG);
		$this->spieler(Datenbank::mitglied($this->db, 'Bernd', 'Bach'), 1650.0, 70, 40, $jetzt - self::TAG);
		$this->spieler(Datenbank::mitglied($this->db, 'Carla', 'Neu'), 1900.0, 200, 3, $jetzt - self::TAG);
		$this->spieler(Datenbank::mitglied($this->db, 'Dora', 'Pause'), 1800.0, 60, 50, $jetzt - 200 * self::TAG);
		$this->spieler(Datenbank::mitglied($this->db, 'Egon', 'Gesperrt', '1'), 1750.0, 60, 50, $jetzt - self::TAG);

		$liste = $this->ranglisten()->aktuell('blitz', $jetzt);

		$this->assertSame(array('Anna A.', 'Bernd B.'), array_column($liste, 'name'));
		$this->assertSame(array(1, 2), array_column($liste, 'platz'));
		$this->assertSame(1712, $liste[0]['wertung']);
		$this->assertSame('anna.alt', $liste[0]['username']);
		$this->assertSame(array(), $this->ranglisten()->aktuell('lang', $jetzt));
	}

	/**
	 * Abgelaufene Mitgliedschaften fehlen in aktueller und ewiger Liste;
	 * gesperrt ist nur ein Mitglied mit disable '1'.
	 */
	public function testNurAktiveMitglieder(): void
	{
		$jetzt = self::STICHTAG;
		$abgelaufen = Datenbank::mitglied($this->db, 'Otto', 'Alt');
		$laufend = Datenbank::mitglied($this->db, 'Paula', 'Neu');
		$gesperrt = Datenbank::mitglied($this->db, 'Egon', 'Gesperrt', '1');
		$this->db->update('tl_member', array('stop' => (string) ($jetzt - self::TAG)), array('id' => $abgelaufen));
		$this->db->update('tl_member', array('stop' => (string) ($jetzt + self::TAG)), array('id' => $laufend));

		foreach (array($abgelaufen, $laufend, $gesperrt) as $memberId) {
			$this->spieler($memberId, 1700, 60, 30, $jetzt - self::TAG, 1750.0, $jetzt - self::TAG);
		}

		$this->assertSame(array($laufend), array_column($this->ranglisten()->aktuell('blitz', $jetzt), 'memberId'));
		$this->assertSame(array($laufend), array_column($this->ranglisten()->ewig('blitz', $jetzt), 'memberId'));
	}

	/**
	 * Die ewige Liste ordnet nach Höchstwert; bei Gleichstand zählt das frühere Datum.
	 */
	public function testEwig(): void
	{
		$anna = Datenbank::mitglied($this->db, 'Anna', 'Alt');
		$bernd = Datenbank::mitglied($this->db, 'Bernd', 'Bach');
		$this->spieler($anna, 1600, 60, 30, 0, 1800.2, 2000);
		$this->spieler($bernd, 1700, 60, 30, 0, 1800.0, 1000);
		$this->spieler(Datenbank::mitglied($this->db, 'Carla', 'Neu'), 1900, 200, 3, 0, 0.0, 0);

		$liste = $this->ranglisten()->ewig('blitz', self::STICHTAG);

		$this->assertSame(array($bernd, $anna), array_column($liste, 'memberId'));
		$this->assertSame(array(1, 1), array_column($liste, 'platz'));
		$this->assertSame(1000, $liste[0]['datum']);
	}

	/**
	 * Die Monatsliste kommt mit Namen und Vormonatswerten zurück, die Monate neueste zuerst.
	 */
	public function testStichtagUndMonate(): void
	{
		$anna = Datenbank::mitglied($this->db, 'Anna', 'Alt');
		$pid = $this->spieler($anna, 1700, 60, 5, self::STICHTAG - self::TAG);
		$this->db->insert('tl_schachcomputer_verlauf', array('pid' => $pid, 'partie' => 1, 'zeit' => self::STICHTAG - self::TAG, 'wertung' => 1700, 'abweichung' => 60));

		$liste = new Stichtagsliste($this->db, new Glicko2());
		$liste->erstellen('blitz', self::STICHTAG);
		$liste->erstellen('blitz', Stichtagsliste::stichtag(self::STICHTAG + 40 * self::TAG));

		$november = $this->ranglisten()->stichtag('blitz', '2026-11');
		$this->assertSame('Anna A.', $november[0]['name']);
		$this->assertSame(1, $november[0]['platzVormonat']);
		$this->assertSame(array('2026-11', '2026-10'), $this->ranglisten()->monate('blitz'));
	}

	/**
	 * Baut den Dienst.
	 *
	 * @return Ranglisten Der Dienst
	 */
	private function ranglisten(): Ranglisten
	{
		return new Ranglisten($this->db, new Wertungsrechner(new Glicko2()));
	}

	/**
	 * Legt eine Spielerzeile in der Klasse Blitz an.
	 *
	 * @param int   $memberId         ID des Mitglieds
	 * @param float $wertung          Wertung
	 * @param float $abweichung       Abweichung
	 * @param int   $partien          Zahl der Partien
	 * @param int   $letztePartie     Zeitpunkt der letzten Partie
	 * @param float $hoechstwert      Höchstwert
	 * @param int   $hoechstwertDatum Datum des Höchstwerts
	 *
	 * @return int ID der Spielerzeile
	 */
	private function spieler(int $memberId, float $wertung, float $abweichung, int $partien, int $letztePartie, float $hoechstwert = 0.0, int $hoechstwertDatum = 0): int
	{
		$this->db->insert('tl_schachcomputer_spieler', array(
			'memberId'         => $memberId,
			'klasse'           => 'blitz',
			'wertung'          => $wertung,
			'abweichung'       => $abweichung,
			'volatilitaet'     => 0.06,
			'partien'          => $partien,
			'letztePartie'     => $letztePartie,
			'hoechstwert'      => $hoechstwert,
			'hoechstwertDatum' => $hoechstwertDatum,
		));

		return (int) $this->db->lastInsertId();
	}
}
