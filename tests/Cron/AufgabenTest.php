<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Cron;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachcomputerBundle\Cron\AufraeumCron;
use Schachbulle\ContaoSchachcomputerBundle\Cron\StichtagCron;
use Schachbulle\ContaoSchachcomputerBundle\Cron\ZeitablaufCron;
use Schachbulle\ContaoSchachcomputerBundle\EventListener\MitgliedLoeschenListener;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partie;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partiedienst;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Spieler;
use Schachbulle\ContaoSchachcomputerBundle\Rangliste\Stichtagsliste;
use Schachbulle\ContaoSchachcomputerBundle\Tests\Datenbank;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Glicko2;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsdienst;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsrechner;

/**
 * Prüft Cronjobs und das Aufräumen beim Löschen eines Mitglieds.
 */
class AufgabenTest extends TestCase
{
	private const T0 = 1790000000000;

	private Connection $db;

	private Partiedienst $partiedienst;

	/**
	 * Legt Datenbank und Partiedienst an.
	 */
	protected function setUp(): void
	{
		$this->db = Datenbank::verbindung();
		$this->partiedienst = new Partiedienst($this->db, new Wertungsdienst($this->db, new Wertungsrechner(new Glicko2())));
	}

	/**
	 * Der minütliche Lauf beendet eine verlassene Partie und verrechnet sie.
	 */
	public function testZeitablaufCron(): void
	{
		$bedenkzeit = Datenbank::bedenkzeit($this->db);
		$spieler = Spieler::mitglied(7);
		$partie = $this->partiedienst->starten($spieler, null, $bedenkzeit, 1500, 'w', self::T0);
		$this->partiedienst->ziehen($spieler, null, $partie->id, 0, 'e2e4', null, self::T0 + 1000);

		$this->assertSame(1, (new ZeitablaufCron($this->partiedienst))->pruefen(self::T0 + 1000 + 60001));
		$this->assertTrue($this->partiedienst->laden($partie->id)->verrechnet);
	}

	/**
	 * Der stündliche Lauf legt die Listen aller Klassen genau einmal an.
	 */
	public function testStichtagCron(): void
	{
		$anna = Datenbank::mitglied($this->db, 'Anna', 'Alt');
		$this->db->insert('tl_schachcomputer_spieler', array('memberId' => $anna, 'klasse' => 'schnell', 'volatilitaet' => 0.06));
		$pid = (int) $this->db->lastInsertId();
		$this->db->insert('tl_schachcomputer_verlauf', array('pid' => $pid, 'partie' => 1, 'zeit' => 1790000000, 'wertung' => 1650, 'abweichung' => 70));

		$cron = new StichtagCron(new Stichtagsliste($this->db, new Glicko2()));

		// 1790000000 liegt am 21. September 2026; +20 und +25 Tage sind beide im Oktober
		$this->assertSame(1, $cron->erstellen(1790000000 + 20 * 86400));
		$this->assertSame(0, $cron->erstellen(1790000000 + 25 * 86400));
		$this->assertSame('schnell', $this->db->fetchOne('SELECT klasse FROM tl_schachcomputer_stichtag'));
	}

	/**
	 * Nur beendete Gastpartien nach Ablauf der Frist werden gelöscht.
	 */
	public function testAufraeumCron(): void
	{
		$jetzt = 1790100000;
		$alt = $this->partie(0, Partie::BEENDET, $jetzt - 90000);
		$frisch = $this->partie(0, Partie::BEENDET, $jetzt - 3600);
		$laufend = $this->partie(0, Partie::LAEUFT, 0);
		$mitglied = $this->partie(7, Partie::BEENDET, $jetzt - 90000);

		$this->assertSame(1, (new AufraeumCron($this->db))->aufraeumen($jetzt));

		$uebrig = array_map('intval', $this->db->fetchFirstColumn('SELECT id FROM tl_schachcomputer_partie ORDER BY id'));
		$this->assertSame(array($frisch, $laufend, $mitglied), $uebrig);
		$this->assertNotContains($alt, $uebrig);
	}

	/**
	 * Beim Löschen verschwinden alle Zeilen des Mitglieds, beim Deaktivieren keine.
	 */
	public function testMitgliedLoeschen(): void
	{
		foreach (array(7, 8) as $memberId) {
			$this->db->insert('tl_schachcomputer_spieler', array('memberId' => $memberId, 'klasse' => 'blitz'));
			$this->db->insert('tl_schachcomputer_verlauf', array('pid' => (int) $this->db->lastInsertId(), 'zeit' => 1));
			$this->db->insert('tl_schachcomputer_stichtag', array('monat' => '2026-10', 'klasse' => 'blitz', 'memberId' => $memberId));
			$this->partie($memberId, Partie::BEENDET, 1);
		}

		$listener = new MitgliedLoeschenListener($this->db);
		$listener->imFrontend(7, 'close_deactivate');
		$this->assertSame(2, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_schachcomputer_spieler'));

		$listener->imFrontend(7, 'close_delete');

		foreach (array('tl_schachcomputer_spieler', 'tl_schachcomputer_verlauf', 'tl_schachcomputer_stichtag', 'tl_schachcomputer_partie') as $tabelle) {
			$this->assertSame(1, (int) $this->db->fetchOne("SELECT COUNT(*) FROM $tabelle"), $tabelle);
		}

		$this->assertSame(8, (int) $this->db->fetchOne('SELECT memberId FROM tl_schachcomputer_spieler'));
	}

	/**
	 * Legt eine Partie mit Status und Ende an.
	 *
	 * @param int    $memberId ID des Mitglieds, 0 für Gäste
	 * @param string $status   Status der Partie
	 * @param int    $ende     Ende in Sekunden
	 *
	 * @return int ID der Partie
	 */
	private function partie(int $memberId, string $status, int $ende): int
	{
		$partie = new Partie();
		$partie->memberId = $memberId;
		$partie->gast = 0 === $memberId ? 'abc' : '';
		$partie->status = $status;
		$partie->ende = $ende;
		$this->db->insert('tl_schachcomputer_partie', $partie->alsZeile());

		return (int) $this->db->lastInsertId();
	}
}
