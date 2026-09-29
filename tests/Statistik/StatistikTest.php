<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Statistik;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partie;
use Schachbulle\ContaoSchachcomputerBundle\Statistik\Statistik;
use Schachbulle\ContaoSchachcomputerBundle\Tests\Datenbank;

/**
 * Prüft Zählung und Auswertung der Statistik gegen SQLite.
 */
class StatistikTest extends TestCase
{
	/**
	 * 29. September 2026, 14:30 Uhr in Berlin.
	 */
	private const ZEIT = 1790685000;

	private Connection $db;

	private Statistik $statistik;

	private string $zeitzone;

	/**
	 * Legt Datenbank, Dienst und feste Zeitzone an.
	 */
	protected function setUp(): void
	{
		$this->zeitzone = date_default_timezone_get();
		date_default_timezone_set('Europe/Berlin');
		$this->db = Datenbank::verbindung();
		$this->statistik = new Statistik($this->db, new NullLogger());
	}

	/**
	 * Stellt die Zeitzone wieder her.
	 */
	protected function tearDown(): void
	{
		date_default_timezone_set($this->zeitzone);
	}

	/**
	 * Mehrere Ereignisse derselben Stunde, Art und Gruppe teilen sich eine Zeile.
	 */
	public function testZaehlenJeStunde(): void
	{
		$this->statistik->zaehlen(Statistik::AUFRUF, true, self::ZEIT);
		$this->statistik->zaehlen(Statistik::AUFRUF, true, self::ZEIT + 60);
		$this->statistik->zaehlen(Statistik::AUFRUF, false, self::ZEIT);
		$this->statistik->zaehlen(Statistik::AUFRUF, true, self::ZEIT + 3600);

		$zeilen = $this->db->fetchAllAssociative('SELECT datum, stunde, gast, anzahl FROM tl_schachcomputer_statistik ORDER BY stunde, gast');

		$this->assertCount(3, $zeilen);
		$this->assertSame(array(20260929, 14, '', 1), array((int) $zeilen[0]['datum'], (int) $zeilen[0]['stunde'], $zeilen[0]['gast'], (int) $zeilen[0]['anzahl']));
		$this->assertSame(2, (int) $zeilen[1]['anzahl']);
		$this->assertSame(15, (int) $zeilen[2]['stunde']);
	}

	/**
	 * Ein Fehler beim Zählen landet im Protokoll statt beim Spieler.
	 */
	public function testFehlerWirdProtokolliert(): void
	{
		$logger = new class() extends AbstractLogger {
			/**
			 * @var array<int, string>
			 */
			public array $meldungen = array();

			/**
			 * Merkt sich jede Meldung.
			 *
			 * @param mixed                $level   Stufe
			 * @param string|\Stringable   $message Meldung
			 * @param array<string, mixed> $context Zusatzangaben
			 */
			public function log($level, $message, array $context = array()): void
			{
				$this->meldungen[] = $level.': '.$message;
			}
		};

		$this->db->executeStatement('DROP TABLE tl_schachcomputer_statistik');
		(new Statistik($this->db, $logger))->zaehlen(Statistik::GESTARTET, false, self::ZEIT);

		$this->assertCount(1, $logger->meldungen);
		$this->assertStringStartsWith('warning: Schachcomputer: Statistik nicht gezählt (gestartet)', $logger->meldungen[0]);
	}

	/**
	 * Summen je Art, getrennt nach Mitgliedern und Gästen, nur im Zeitraum.
	 */
	public function testSummen(): void
	{
		$this->statistik->zaehlen(Statistik::GESTARTET, false, self::ZEIT);
		$this->statistik->zaehlen(Statistik::GESTARTET, true, self::ZEIT);
		$this->statistik->zaehlen(Statistik::GESTARTET, true, self::ZEIT + 86400);
		$this->statistik->zaehlen(Statistik::REMIS, false, self::ZEIT);

		$summen = $this->statistik->summen(20260929, 20260929);

		$this->assertSame(array('mitglieder' => 1, 'gaeste' => 1, 'gesamt' => 2), $summen[Statistik::GESTARTET]);
		$this->assertSame(1, $summen[Statistik::REMIS]['gesamt']);
		$this->assertSame(0, $summen[Statistik::UEBUNG]['gesamt']);
		$this->assertSame(Statistik::ARTEN, array_keys($summen));
	}

	/**
	 * Der Verlauf fasst mehrere Arten zusammen, nach Stunde, Tag oder Monat.
	 */
	public function testVerlauf(): void
	{
		$this->statistik->zaehlen(Statistik::GEWONNEN, false, self::ZEIT);
		$this->statistik->zaehlen(Statistik::VERLOREN, true, self::ZEIT);
		$this->statistik->zaehlen(Statistik::REMIS, false, self::ZEIT + 86400);
		$this->statistik->zaehlen(Statistik::REMIS, false, self::ZEIT + 2 * 86400);
		$this->statistik->zaehlen(Statistik::AUFRUF, false, self::ZEIT);

		$this->assertSame(array(14 => 2), $this->statistik->verlauf(Statistik::BEENDET, 20260929, 20260929, 'stunde'));
		$this->assertSame(array(29 => 2, 30 => 1), $this->statistik->verlauf(Statistik::BEENDET, 20260901, 20260931, 'tag'));
		$this->assertSame(array(9 => 3, 10 => 1), $this->statistik->verlauf(Statistik::BEENDET, 20260101, 20261231, 'monat'));
		$this->assertSame(array(), $this->statistik->verlauf(array(), 20260101, 20261231, 'monat'));
	}

	/**
	 * Bedenkzeiten mit Punktquote und Durchschnittsstufe; Gäste und Übungen zählen nicht.
	 */
	public function testBedenkzeiten(): void
	{
		$this->partie(7, 'w', Partie::SIEG_WEISS, 1500, 3, 2);
		$this->partie(7, 'b', Partie::SIEG_WEISS, 1700, 3, 2);
		$this->partie(8, 'w', Partie::REMIS, 1600, 3, 2);
		$this->partie(8, 'w', Partie::SIEG_WEISS, 1200, 10, 5);
		$this->partie(0, 'w', Partie::SIEG_WEISS, 1500, 3, 2);
		$this->partie(7, 'w', Partie::SIEG_WEISS, 900, 3, 2, false);

		$zeilen = $this->statistik->bedenkzeiten(self::ZEIT - 3600, self::ZEIT + 3600, 20);

		$this->assertCount(2, $zeilen);
		$this->assertSame(array('klasse' => 'blitz', 'minuten' => 3, 'inkrement' => 2, 'partien' => 3, 'quote' => 50, 'stufe' => 1600), $zeilen[0]);
		$this->assertSame(10, $zeilen[1]['minuten']);
		$this->assertSame(array(), $this->statistik->bedenkzeiten(self::ZEIT + 3600, self::ZEIT + 7200, 20));
	}

	/**
	 * Aktivste Mitglieder mit Punkten und bester Wertung über alle Klassen.
	 */
	public function testAktivsteMitglieder(): void
	{
		$anna = Datenbank::mitglied($this->db, 'Anna', 'Alt');
		$bernd = Datenbank::mitglied($this->db, 'Bernd', 'Bach');
		$this->partie($anna, 'w', Partie::SIEG_WEISS, 1500, 3, 2);
		$this->partie($anna, 'w', Partie::REMIS, 1500, 3, 2);
		$this->partie($bernd, 'b', Partie::SIEG_WEISS, 1500, 3, 2);
		$this->db->insert('tl_schachcomputer_spieler', array('memberId' => $anna, 'klasse' => 'blitz', 'wertung' => 1611.6));
		$this->db->insert('tl_schachcomputer_spieler', array('memberId' => $anna, 'klasse' => 'lang', 'wertung' => 1702.2));

		$liste = $this->statistik->aktivsteMitglieder(self::ZEIT - 3600, self::ZEIT + 3600, 20);

		$this->assertSame(array('name' => 'Anna A.', 'partien' => 2, 'punkte' => 1.5, 'wertung' => 1702), $liste[0]);
		$this->assertSame(array('name' => 'Bernd B.', 'partien' => 1, 'punkte' => 0.0, 'wertung' => null), $liste[1]);
	}

	/**
	 * Bestand: veröffentlichte Bedenkzeiten, Spieler mit Partien, neue Spieler im Zeitraum.
	 */
	public function testBestand(): void
	{
		Datenbank::bedenkzeit($this->db);
		Datenbank::bedenkzeit($this->db, 'lang', 30, 0, '');
		$this->db->insert('tl_schachcomputer_spieler', array('memberId' => 7, 'klasse' => 'blitz', 'partien' => 2));
		$this->db->insert('tl_schachcomputer_spieler', array('memberId' => 7, 'klasse' => 'lang', 'partien' => 1));
		$this->db->insert('tl_schachcomputer_spieler', array('memberId' => 8, 'klasse' => 'blitz', 'partien' => 0));
		$this->partie(7, 'w', Partie::SIEG_WEISS, 1500, 3, 2, true, self::ZEIT - 86400 * 40);
		$this->partie(7, 'w', Partie::SIEG_WEISS, 1500, 3, 2);
		$this->partie(8, 'w', Partie::SIEG_WEISS, 1500, 3, 2);

		$bestand = $this->statistik->bestand(self::ZEIT - 86400, self::ZEIT + 86400);

		$this->assertSame(array('bedenkzeiten' => 1, 'spieler' => 1, 'neueSpieler' => 1), $bestand);
	}

	/**
	 * Legt eine beendete Partie an.
	 *
	 * @param int    $memberId  ID des Mitglieds, 0 für Gäste
	 * @param string $farbe     Farbe des Spielers
	 * @param string $ergebnis  PGN-Ergebnis
	 * @param int    $stufe     Stufe der Engine
	 * @param int    $minuten   Grundzeit
	 * @param int    $inkrement Zeitgutschrift
	 * @param bool   $gewertet  Gewertet oder Übung
	 * @param int    $zeit      Beginn und Ende in Sekunden
	 */
	private function partie(int $memberId, string $farbe, string $ergebnis, int $stufe, int $minuten, int $inkrement, bool $gewertet = true, int $zeit = self::ZEIT): void
	{
		$partie = new Partie();
		$partie->memberId = $memberId;
		$partie->gast = 0 === $memberId ? 'abc' : '';
		$partie->gewertet = $gewertet;
		$partie->klasse = 'blitz';
		$partie->minuten = $minuten;
		$partie->inkrement = $inkrement;
		$partie->stufe = $stufe;
		$partie->farbe = $farbe;
		$partie->status = Partie::BEENDET;
		$partie->ergebnis = $ergebnis;
		$partie->beginn = $zeit;
		$partie->ende = $zeit;

		$this->db->insert('tl_schachcomputer_partie', $partie->alsZeile());
	}
}
