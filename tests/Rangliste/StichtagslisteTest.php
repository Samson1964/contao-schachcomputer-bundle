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
use Schachbulle\ContaoSchachcomputerBundle\Rangliste\Stichtagsliste;
use Schachbulle\ContaoSchachcomputerBundle\Tests\Datenbank;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Glicko2;

/**
 * Prüft Stichtag, Berechnung und Speichern der Monatsranglisten.
 */
class StichtagslisteTest extends TestCase
{
	private const TAG = 86400;

	/**
	 * 1. Oktober 2026, 0 Uhr in Berlin.
	 */
	private const STICHTAG = 1790805600;

	private Connection $db;

	private Stichtagsliste $liste;

	private string $zeitzone;

	/**
	 * Legt Datenbank, Dienst und feste Zeitzone an.
	 */
	protected function setUp(): void
	{
		$this->zeitzone = date_default_timezone_get();
		date_default_timezone_set('Europe/Berlin');
		$this->db = Datenbank::verbindung();
		$this->liste = new Stichtagsliste($this->db, new Glicko2());
	}

	/**
	 * Stellt die Zeitzone wieder her.
	 */
	protected function tearDown(): void
	{
		date_default_timezone_set($this->zeitzone);
	}

	/**
	 * Jeder Zeitpunkt eines Monats führt zum Monatsersten 0 Uhr, auch über
	 * Zeitumstellung und Jahreswechsel; Monat und Vormonat passen dazu.
	 */
	public function testStichtagMonatVormonat(): void
	{
		$this->assertSame(self::STICHTAG, Stichtagsliste::stichtag(self::STICHTAG + 30 * self::TAG));
		$this->assertSame('2026-10', Stichtagsliste::monat(self::STICHTAG));
		$this->assertSame('2027-01-01 00:00', date('Y-m-d H:i', Stichtagsliste::stichtag((int) strtotime('2027-01-01 00:30'))));
		$this->assertSame('2026-09', Stichtagsliste::vormonat('2026-10'));
		$this->assertSame('2026-12', Stichtagsliste::vormonat('2027-01'));
	}

	/**
	 * Vorläufige fallen heraus, Ruhezeit bis zum Stichtag zählt, Vormonat wird verglichen.
	 */
	public function testBerechnen(): void
	{
		$eintraege = array(
			array('memberId' => 1, 'volatilitaet' => 0.06, 'wertung' => 1700.4, 'abweichung' => 60, 'zeit' => self::STICHTAG - self::TAG, 'anzahl' => 30),
			array('memberId' => 2, 'volatilitaet' => 0.06, 'wertung' => 1800.0, 'abweichung' => 60, 'zeit' => self::STICHTAG - 200 * self::TAG, 'anzahl' => 90),
			array('memberId' => 3, 'volatilitaet' => 0.06, 'wertung' => 1750.0, 'abweichung' => 60, 'zeit' => self::STICHTAG - self::TAG, 'anzahl' => 12),
			array('memberId' => 4, 'volatilitaet' => 0.06, 'wertung' => 1900.0, 'abweichung' => 180, 'zeit' => self::STICHTAG - self::TAG, 'anzahl' => 4),
		);

		$zeilen = $this->liste->berechnen($eintraege, array(3 => array('platz' => 1, 'wertung' => 1720)), self::STICHTAG);

		$this->assertSame(array(3, 1), array_column($zeilen, 'memberId'));
		$this->assertSame(array(1, 2), array_column($zeilen, 'platz'));
		$this->assertSame(1700, $zeilen[1]['wertung']);
		$this->assertSame(1, $zeilen[0]['platzVormonat']);
		$this->assertSame(1720, $zeilen[0]['wertungVormonat']);
		$this->assertSame(0, $zeilen[1]['platzVormonat']);
	}

	/**
	 * Gespeichert wird der Stand vor dem Stichtag, auch wenn danach gespielt
	 * wurde; ein zweiter Lauf ändert nichts.
	 */
	public function testErstellenAusDemVerlauf(): void
	{
		$anna = Datenbank::mitglied($this->db, 'Anna', 'Alt');
		$gesperrt = Datenbank::mitglied($this->db, 'Egon', 'Gesperrt', '1');
		$pidAnna = $this->spieler($anna);
		$pidGesperrt = $this->spieler($gesperrt);

		$this->verlauf($pidAnna, self::STICHTAG - 2 * self::TAG, 1680);
		$this->verlauf($pidAnna, self::STICHTAG - self::TAG, 1700);
		$this->verlauf($pidAnna, self::STICHTAG + 3600, 1900);
		$this->verlauf($pidGesperrt, self::STICHTAG - self::TAG, 2000);

		$this->assertSame(1, $this->liste->erstellen('blitz', self::STICHTAG));
		$this->assertSame(0, $this->liste->erstellen('blitz', self::STICHTAG));
		$this->assertTrue($this->liste->vorhanden('blitz', '2026-10'));

		$zeile = $this->db->fetchAssociative('SELECT * FROM tl_schachcomputer_stichtag');
		$this->assertSame($anna, (int) $zeile['memberId']);
		$this->assertSame(1700, (int) $zeile['wertung']);
		$this->assertSame(2, (int) $zeile['partien']);
		$this->assertSame(1, (int) $zeile['platz']);
	}

	/**
	 * Wie in der aktuellen Rangliste fehlen Mitglieder, deren Mitgliedschaft
	 * vor dem Stichtag endete; endet sie danach, stehen sie in der Liste.
	 */
	public function testAbgelaufeneMitgliedschaftFehlt(): void
	{
		$abgelaufen = Datenbank::mitglied($this->db, 'Otto', 'Alt');
		$laufend = Datenbank::mitglied($this->db, 'Paula', 'Neu');
		$this->db->update('tl_member', array('stop' => (string) (self::STICHTAG - self::TAG)), array('id' => $abgelaufen));
		$this->db->update('tl_member', array('stop' => (string) (self::STICHTAG + 30 * self::TAG)), array('id' => $laufend));
		$this->verlauf($this->spieler($abgelaufen), self::STICHTAG - 2 * self::TAG, 1800);
		$this->verlauf($this->spieler($laufend), self::STICHTAG - 2 * self::TAG, 1700);

		$this->assertSame(1, $this->liste->erstellen('blitz', self::STICHTAG));
		$this->assertSame($laufend, (int) $this->db->fetchOne('SELECT memberId FROM tl_schachcomputer_stichtag'));
	}

	/**
	 * Legt eine Spielerzeile im Blitz an.
	 *
	 * @param int $memberId ID des Mitglieds
	 *
	 * @return int ID der Spielerzeile
	 */
	private function spieler(int $memberId): int
	{
		$this->db->insert('tl_schachcomputer_spieler', array('memberId' => $memberId, 'klasse' => 'blitz', 'volatilitaet' => 0.06));

		return (int) $this->db->lastInsertId();
	}

	/**
	 * Legt einen Verlaufseintrag mit gesicherter Abweichung an.
	 *
	 * @param int   $pid     ID der Spielerzeile
	 * @param int   $zeit    Zeitpunkt
	 * @param float $wertung Wertung danach
	 */
	private function verlauf(int $pid, int $zeit, float $wertung): void
	{
		$this->db->insert('tl_schachcomputer_verlauf', array('pid' => $pid, 'partie' => 1, 'zeit' => $zeit, 'wertung' => $wertung, 'abweichung' => 60));
	}
}
