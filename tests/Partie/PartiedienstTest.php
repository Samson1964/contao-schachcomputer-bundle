<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Partie;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partie;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partiedienst;
use Schachbulle\ContaoSchachcomputerBundle\Partie\PartieFehler;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Spieler;
use Schachbulle\ContaoSchachcomputerBundle\Tests\Datenbank;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Glicko2;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsdienst;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsrechner;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Prüft den Partiedienst gegen eine SQLite-Datenbank.
 */
class PartiedienstTest extends TestCase
{
	private const T0 = 1790000000000;

	private Connection $db;

	private Partiedienst $dienst;

	private int $blitz;

	/**
	 * Legt Datenbank, Dienste und eine Bedenkzeit 3+2 an.
	 */
	protected function setUp(): void
	{
		$this->db = Datenbank::verbindung();
		$this->dienst = new Partiedienst($this->db, new Wertungsdienst($this->db, new Wertungsrechner(new Glicko2())));
		$this->blitz = Datenbank::bedenkzeit($this->db, 'blitz', 3, 2);
	}

	/**
	 * Nur veröffentlichte Bedenkzeiten, sortiert nach Klasse und Minuten.
	 */
	public function testBedenkzeiten(): void
	{
		Datenbank::bedenkzeit($this->db, 'lang', 30, 0);
		Datenbank::bedenkzeit($this->db, 'blitz', 1, 0);
		Datenbank::bedenkzeit($this->db, 'schnell', 10, 5, '');

		$namen = array_column($this->dienst->bedenkzeiten(), 'name');

		$this->assertSame(array('1+0', '3+2', '30+0'), $namen);
	}

	/**
	 * Der Start legt die Partie mit ID in der Datenbank an.
	 */
	public function testStarten(): void
	{
		$partie = $this->dienst->starten(Spieler::mitglied(7), null, $this->blitz, 1500, 'w', self::T0);

		$this->assertGreaterThan(0, $partie->id);
		$this->assertSame('blitz', $partie->klasse);
		$this->assertSame(Partie::LAEUFT, $this->dienst->laden($partie->id)->status);
	}

	/**
	 * Eine unveröffentlichte Bedenkzeit gibt es für Spieler nicht.
	 */
	public function testStartenMitVerborgenerBedenkzeit(): void
	{
		$verborgen = Datenbank::bedenkzeit($this->db, 'schnell', 10, 5, '');

		$this->expectExceptionObject(new PartieFehler(PartieFehler::NICHT_GEFUNDEN));
		$this->dienst->starten(Spieler::mitglied(7), null, $verborgen, 1500, 'w', self::T0);
	}

	/**
	 * Während eine Partie läuft, beginnt keine zweite.
	 */
	public function testKeineZweitePartie(): void
	{
		$this->dienst->starten(Spieler::mitglied(7), null, $this->blitz, 1500, 'w', self::T0);

		$this->expectExceptionObject(new PartieFehler(PartieFehler::LAEUFT_SCHON));
		$this->dienst->starten(Spieler::mitglied(7), null, $this->blitz, 1500, 'w', self::T0 + 1000);
	}

	/**
	 * Die Zufallsfarbe wird beim Start ausgewürfelt.
	 */
	public function testZufallsfarbe(): void
	{
		$partie = $this->dienst->starten(Spieler::mitglied(7), null, $this->blitz, 1500, 'zufall', self::T0);

		$this->assertContains($partie->farbe, array('w', 'b'));
	}

	/**
	 * Fremde Partien lassen sich weder als Mitglied noch als Gast ziehen.
	 */
	public function testFremdePartie(): void
	{
		$partie = $this->dienst->starten(Spieler::gast('abc'), null, $this->blitz, 1500, 'w', self::T0);

		foreach (array(Spieler::gast('xyz'), Spieler::mitglied(7)) as $fremder) {
			try {
				$this->dienst->ziehen($fremder, null, $partie->id, 0, 'e2e4', null, self::T0 + 1000);
				$this->fail('Erwartet: PartieFehler');
			} catch (PartieFehler $fehler) {
				$this->assertSame(PartieFehler::NICHT_GEFUNDEN, $fehler->kennung());
			}
		}
	}

	/**
	 * Eine ganze Partie bis zum Matt wird gespeichert und verrechnet.
	 */
	public function testPartieBisZumMatt(): void
	{
		$spieler = Spieler::mitglied(7);
		$partie = $this->dienst->starten($spieler, null, $this->blitz, 1500, 'b', self::T0);

		$this->dienst->ziehen($spieler, null, $partie->id, 0, 'f2f3', null, self::T0 + 1000);
		$this->dienst->ziehen($spieler, null, $partie->id, 1, 'e7e5', 1500, self::T0 + 3000);
		$this->dienst->ziehen($spieler, null, $partie->id, 2, 'g2g4', null, self::T0 + 4000);
		$ende = $this->dienst->ziehen($spieler, null, $partie->id, 3, 'd8h4', 900, self::T0 + 5000);

		$this->assertSame(Partie::BEENDET, $ende->status);
		$this->assertTrue($ende->verrechnet);
		$this->assertGreaterThan(1500.0, $ende->wertungNachher);

		$gespeichert = $this->dienst->laden($partie->id);
		$this->assertSame(array('f2f3', 'e7e5', 'g2g4', 'd8h4'), $gespeichert->zuege);
		$this->assertSame('matt', $gespeichert->grund);
		$this->assertTrue($gespeichert->verrechnet);
		$this->assertSame(1, (int) $this->db->fetchOne('SELECT siege FROM tl_schachcomputer_spieler WHERE memberId=7'));
	}

	/**
	 * Wer eine veraltete Zugnummer schickt, bekommt VERALTET.
	 */
	public function testDoppelterZug(): void
	{
		$spieler = Spieler::mitglied(7);
		$partie = $this->dienst->starten($spieler, null, $this->blitz, 1500, 'w', self::T0);
		$this->dienst->ziehen($spieler, null, $partie->id, 0, 'e2e4', null, self::T0 + 1000);

		$this->expectExceptionObject(new PartieFehler(PartieFehler::VERALTET));
		$this->dienst->ziehen($spieler, null, $partie->id, 0, 'd2d4', null, self::T0 + 1100);
	}

	/**
	 * Prüft der Cron einen veralteten Stand, gewinnt der neuere aus der Datenbank.
	 */
	public function testCronUeberschreibtKeinenNeuerenStand(): void
	{
		$spieler = Spieler::mitglied(7);
		$partie = $this->dienst->starten($spieler, null, $this->blitz, 1500, 'w', self::T0);
		$veraltet = $this->dienst->laden($partie->id);

		$this->dienst->ziehen($spieler, null, $partie->id, 0, 'e2e4', null, self::T0 + 1000);
		$geprueft = $this->dienst->pruefen($veraltet, null, self::T0 + 61001);

		$this->assertSame(Partie::LAEUFT, $geprueft->status);
		$this->assertSame(array('e2e4'), $geprueft->zuege);
	}

	/**
	 * Eine abgelaufene Partie wird beim Abruf beendet und verrechnet.
	 */
	public function testLaufendeBeendetAbgelaufenePartie(): void
	{
		$spieler = Spieler::mitglied(7);
		$partie = $this->dienst->starten($spieler, null, $this->blitz, 1500, 'w', self::T0);
		$this->dienst->ziehen($spieler, null, $partie->id, 0, 'e2e4', null, self::T0 + 1000);

		$this->assertNotNull($this->dienst->laufende($spieler, null, self::T0 + 30000));
		$this->assertNull($this->dienst->laufende($spieler, null, self::T0 + 1000 + 60001));

		$beendet = $this->dienst->laden($partie->id);
		$this->assertSame('verlassen', $beendet->grund);
		$this->assertTrue($beendet->verrechnet);
	}

	/**
	 * Der Cronjob beendet alle abgelaufenen Partien und lässt die übrigen.
	 */
	public function testAllePruefen(): void
	{
		$alt = $this->dienst->starten(Spieler::mitglied(7), null, $this->blitz, 1500, 'w', self::T0);
		$neu = $this->dienst->starten(Spieler::mitglied(8), null, $this->blitz, 1500, 'w', self::T0 + 50000);

		$this->assertSame(1, $this->dienst->allePruefen(self::T0 + 70000));
		$this->assertSame(Partie::ABGEBROCHEN, $this->dienst->laden($alt->id)->status);
		$this->assertSame(Partie::LAEUFT, $this->dienst->laden($neu->id)->status);
	}

	/**
	 * Aufgeben verrechnet sofort, Abbrechen gar nicht.
	 */
	public function testAufgebenUndAbbrechen(): void
	{
		$spieler = Spieler::mitglied(7);
		$erste = $this->dienst->starten($spieler, null, $this->blitz, 1500, 'w', self::T0);
		$this->dienst->ziehen($spieler, null, $erste->id, 0, 'e2e4', null, self::T0 + 1000);
		$aufgegeben = $this->dienst->aufgeben($spieler, null, $erste->id, self::T0 + 2000);

		$this->assertSame('aufgabe', $aufgegeben->grund);
		$this->assertLessThan(1500.0, $aufgegeben->wertungNachher);

		$zweite = $this->dienst->starten($spieler, null, $this->blitz, 1500, 'b', self::T0 + 3000);
		$abgebrochen = $this->dienst->abbrechen($spieler, null, $zweite->id, self::T0 + 4000);

		$this->assertSame(Partie::ABGEBROCHEN, $abgebrochen->status);
		$this->assertFalse($abgebrochen->verrechnet);
		$this->assertSame(1, (int) $this->db->fetchOne('SELECT partien FROM tl_schachcomputer_spieler WHERE memberId=7'));
	}

	/**
	 * Gäste spielen mit Sitzung; ihre Wertung landet nur dort.
	 */
	public function testGastpartie(): void
	{
		$sitzung = new Session(new MockArraySessionStorage());
		$gast = Spieler::gast('abc');
		$partie = $this->dienst->starten($gast, $sitzung, $this->blitz, 1500, 'w', self::T0);
		$this->dienst->ziehen($gast, $sitzung, $partie->id, 0, 'e2e4', null, self::T0 + 1000);
		$this->dienst->aufgeben($gast, $sitzung, $partie->id, self::T0 + 2000);

		$this->assertIsArray($sitzung->get(Wertungsdienst::SITZUNG)['blitz']);
		$this->assertFalse($this->db->fetchOne('SELECT id FROM tl_schachcomputer_spieler'));
	}

	/**
	 * Eigene Partien: nur beendete, neueste zuerst, mit Blättern.
	 */
	public function testEigenePartien(): void
	{
		$erste = $this->dienst->uebungSpeichern(7, 900, 'w', array('e2e4'), true, self::T0);
		$zweite = $this->dienst->uebungSpeichern(7, 900, 'w', array('d2d4'), true, self::T0 + 5000);
		$this->dienst->uebungSpeichern(8, 900, 'w', array('c2c4'), true, self::T0);
		$this->dienst->starten(Spieler::mitglied(7), null, $this->blitz, 1500, 'w', self::T0);

		$this->assertSame(2, $this->dienst->anzahlEigenePartien(7));
		$this->assertSame(array($zweite->id, $erste->id), array_map(static fn (Partie $p): int => $p->id, $this->dienst->eigenePartien(7, 10, 0)));
		$this->assertSame(array($erste->id), array_map(static fn (Partie $p): int => $p->id, $this->dienst->eigenePartien(7, 1, 1)));
	}

	/**
	 * Übungspartien werden ungewertet gespeichert.
	 */
	public function testUebungSpeichern(): void
	{
		$partie = $this->dienst->uebungSpeichern(7, 900, 'w', array('e2e4', 'e7e5'), true, self::T0);

		$gespeichert = $this->dienst->laden($partie->id);
		$this->assertFalse($gespeichert->gewertet);
		$this->assertSame('aufgabe', $gespeichert->grund);
		$this->assertNull($this->dienst->laufende(Spieler::mitglied(7), null, self::T0));
	}
}
