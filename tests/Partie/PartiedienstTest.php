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
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Ablauf;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partie;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partiedienst;
use Schachbulle\ContaoSchachcomputerBundle\Partie\PartieFehler;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Spieler;
use Schachbulle\ContaoSchachcomputerBundle\Statistik\Statistik;
use Schachbulle\ContaoSchachcomputerBundle\Tests\Datenbank;
use Schachbulle\ContaoSchachcomputerBundle\Tests\Musterpartie;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Glicko2;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsdienst;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsrechner;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
		$this->dienst = new Partiedienst($this->db, new Wertungsdienst($this->db, new Wertungsrechner(new Glicko2())), new Statistik($this->db, new NullLogger()), new NullLogger());
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
	 * eigenePartie() gibt eine existierende fremde Partie nicht heraus –
	 * weder an einen anderen Gast noch an ein anderes Mitglied – und prüft
	 * sie dabei auch nicht (keine Fristprüfung, kein Speichern).
	 */
	public function testEigenePartieNurFuerBesitzer(): void
	{
		$gastPartie = $this->dienst->starten(Spieler::gast('abc'), null, $this->blitz, 1500, 'w', self::T0);
		$mitgliedPartie = $this->dienst->starten(Spieler::mitglied(7), null, $this->blitz, 1500, 'w', self::T0);
		$spaet = self::T0 + 120000;

		foreach (array(Spieler::gast('xyz'), Spieler::mitglied(7), Spieler::mitglied(8)) as $fremder) {
			$this->assertNull($this->dienst->eigenePartie($fremder, null, $gastPartie->id, $spaet));
		}

		foreach (array(Spieler::gast('abc'), Spieler::gast('xyz'), Spieler::mitglied(8)) as $fremder) {
			$this->assertNull($this->dienst->eigenePartie($fremder, null, $mitgliedPartie->id, $spaet));
		}

		// Die Frist für den ersten Zug ist abgelaufen, aber nur der Besitzer löst die Prüfung aus
		$this->assertSame(Partie::LAEUFT, $this->dienst->laden($gastPartie->id)->status);
		$this->assertSame(Partie::LAEUFT, $this->dienst->laden($mitgliedPartie->id)->status);

		$this->assertSame($mitgliedPartie->id, $this->dienst->eigenePartie(Spieler::mitglied(7), null, $mitgliedPartie->id, $spaet)->id);
		$this->assertSame($gastPartie->id, $this->dienst->eigenePartie(Spieler::gast('abc'), null, $gastPartie->id, $spaet)->id);
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
	 * Die Uhr des Computers wird mit der Partie gespeichert und geladen.
	 */
	public function testComputerUhrWirdGespeichert(): void
	{
		$spieler = Spieler::mitglied(7);
		$partie = $this->dienst->starten($spieler, null, $this->blitz, 1500, 'w', self::T0);
		$this->assertSame(180000, $this->dienst->laden($partie->id)->restzeitEngine);

		$this->dienst->ziehen($spieler, null, $partie->id, 0, 'e2e4', null, self::T0 + 1000);
		$this->dienst->ziehen($spieler, null, $partie->id, 1, 'e7e5', null, self::T0 + 5000);

		// 4 s gemessen, abzüglich 1 s Ausgleich, plus 2 s Gutschrift
		$this->assertSame(180000 - 3000 + 2000, $this->dienst->laden($partie->id)->restzeitEngine);
	}

	/**
	 * Eine Partie aus der Zeit vor der Uhr des Computers (Spalte mit ihrem
	 * Standardwert) bekommt auch nach Zügen keine Uhr des Computers.
	 */
	public function testAltpartieBehaeltKeineComputerUhr(): void
	{
		$spieler = Spieler::mitglied(7);
		$zeile = Ablauf::starten(7, '', array('id' => $this->blitz, 'minuten' => 3, 'inkrement' => 2, 'klasse' => 'blitz'), 1500, 'w', self::T0)->alsZeile();
		unset($zeile['restzeitEngine']);
		$this->db->insert('tl_schachcomputer_partie', $zeile);
		$id = (int) $this->db->lastInsertId();
		$this->assertSame(-1, $this->dienst->laden($id)->restzeitEngine);

		$this->dienst->ziehen($spieler, null, $id, 0, 'e2e4', null, self::T0 + 1000);
		$this->dienst->ziehen($spieler, null, $id, 1, 'e7e5', null, self::T0 + 50000);

		$geladen = $this->dienst->laden($id);
		$this->assertSame(Partie::LAEUFT, $geladen->status);
		$this->assertSame(array('e2e4', 'e7e5'), $geladen->zuege);
		$this->assertSame(-1, $geladen->restzeitEngine);
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
	 * Scheitert die Verrechnung einer Mitgliederpartie, hält das die übrigen
	 * nicht auf; der nächste Lauf holt sie nach, und zwar genau einmal.
	 */
	public function testAllePruefenHoltGescheiterteVerrechnungNach(): void
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

		// Wirft für die gewählten Partien beim ersten Versuch, wie bei einem Deadlock
		$wertungsdienst = new class($this->db, new Wertungsrechner(new Glicko2())) extends Wertungsdienst {
			/**
			 * @var array<int, int> Partie-ID => verbleibende Fehlschläge
			 */
			public array $scheitern = array();

			/**
			 * Wirft, solange für die Partie Fehlschläge übrig sind, sonst wie gewohnt.
			 *
			 * @param Partie                $partie  Die Partie
			 * @param SessionInterface|null $session Sitzung des Gastes
			 *
			 * @return bool Wie Wertungsdienst::verrechnen()
			 */
			public function verrechnen(Partie $partie, ?SessionInterface $session): bool
			{
				if (($this->scheitern[$partie->id] ?? 0) > 0) {
					--$this->scheitern[$partie->id];

					throw new \RuntimeException('Deadlock beim Verrechnen');
				}

				return parent::verrechnen($partie, $session);
			}
		};

		$dienst = new Partiedienst($this->db, $wertungsdienst, new Statistik($this->db, new NullLogger()), $logger);
		$erste = $dienst->starten(Spieler::mitglied(7), null, $this->blitz, 1500, 'w', self::T0);
		$zweite = $dienst->starten(Spieler::mitglied(8), null, $this->blitz, 1500, 'w', self::T0);
		$dienst->ziehen(Spieler::mitglied(7), null, $erste->id, 0, 'e2e4', null, self::T0 + 1000);
		$dienst->ziehen(Spieler::mitglied(8), null, $zweite->id, 0, 'e2e4', null, self::T0 + 1000);
		$wertungsdienst->scheitern[$erste->id] = 1;

		// Erster Lauf: beide gelten als verlassen, die erste scheitert beim Verrechnen
		$dienst->allePruefen(self::T0 + 70000);

		$this->assertSame(Partie::BEENDET, $dienst->laden($erste->id)->status);
		$this->assertFalse($dienst->laden($erste->id)->verrechnet);
		$this->assertTrue($dienst->laden($zweite->id)->verrechnet);
		$this->assertCount(1, $logger->meldungen);
		$this->assertStringStartsWith('warning: Schachcomputer:', $logger->meldungen[0]);

		// Zweiter Lauf: die erste wird nachgeholt, keine doppelt verrechnet
		$dienst->allePruefen(self::T0 + 130000);
		$dienst->allePruefen(self::T0 + 190000);

		$this->assertTrue($dienst->laden($erste->id)->verrechnet);

		foreach (array(7, 8) as $memberId) {
			$this->assertSame(1, (int) $this->db->fetchOne('SELECT partien FROM tl_schachcomputer_spieler WHERE memberId=?', array($memberId)));
		}

		$this->assertSame(2, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_schachcomputer_verlauf'));
		$this->assertCount(1, $logger->meldungen);
	}

	/**
	 * In der eigentlichen Nachholschleife (verrechnet=0, status=beendet) wirft
	 * eine Partie bei jedem Versuch erneut; eine zweite, die im selben Lauf
	 * ebenfalls ansteht, wird davon nicht aufgehalten und verrechnet trotzdem.
	 */
	public function testAllePruefenNachholschleifeLaesstAndereGleichzeitigeVerrechnungZu(): void
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

		// „dauerhaft" scheitert öfter, als dieser Test allePruefen() aufruft –
		// das steht hier für „bei jedem Versuch"; „einmalig" erholt sich nach
		// dem ersten Fehlschlag wieder
		$wertungsdienst = new class($this->db, new Wertungsrechner(new Glicko2())) extends Wertungsdienst {
			/**
			 * @var array<int, int> Partie-ID => verbleibende Fehlschläge
			 */
			public array $scheitern = array();

			/**
			 * Wirft, solange für die Partie Fehlschläge übrig sind, sonst wie gewohnt.
			 *
			 * @param Partie                $partie  Die Partie
			 * @param SessionInterface|null $session Sitzung des Gastes
			 *
			 * @return bool Wie Wertungsdienst::verrechnen()
			 */
			public function verrechnen(Partie $partie, ?SessionInterface $session): bool
			{
				if (($this->scheitern[$partie->id] ?? 0) > 0) {
					--$this->scheitern[$partie->id];

					throw new \RuntimeException('Deadlock beim Verrechnen');
				}

				return parent::verrechnen($partie, $session);
			}
		};

		$dienst = new Partiedienst($this->db, $wertungsdienst, new Statistik($this->db, new NullLogger()), $logger);
		$dauerhaft = $dienst->starten(Spieler::mitglied(7), null, $this->blitz, 1500, 'w', self::T0);
		$einmalig = $dienst->starten(Spieler::mitglied(8), null, $this->blitz, 1500, 'w', self::T0);
		$dienst->ziehen(Spieler::mitglied(7), null, $dauerhaft->id, 0, 'e2e4', null, self::T0 + 1000);
		$dienst->ziehen(Spieler::mitglied(8), null, $einmalig->id, 0, 'e2e4', null, self::T0 + 1000);
		$wertungsdienst->scheitern[$dauerhaft->id] = 3;
		$wertungsdienst->scheitern[$einmalig->id] = 1;

		// Erster Lauf: beide gelten als verlassen (zweite Schleife), beide
		// scheitern dabei beim Verrechnen – ab jetzt stehen beide auch in der
		// eigentlichen Nachholschleife (erste Schleife) an
		$dienst->allePruefen(self::T0 + 70000);
		$this->assertFalse($dienst->laden($dauerhaft->id)->verrechnet);
		$this->assertFalse($dienst->laden($einmalig->id)->verrechnet);
		$meldungenVorher = \count($logger->meldungen);

		// Zweiter Lauf: die Nachholschleife greift für beide; „dauerhaft"
		// scheitert erneut, „einmalig" hat sich erholt und wird trotzdem verrechnet
		$dienst->allePruefen(self::T0 + 130000);

		$this->assertFalse($dienst->laden($dauerhaft->id)->verrechnet);
		$this->assertTrue($dienst->laden($einmalig->id)->verrechnet);
		$this->assertCount($meldungenVorher + 1, $logger->meldungen);
		$this->assertStringContainsString('Partie '.$dauerhaft->id.' nicht verrechnet', $logger->meldungen[$meldungenVorher]);

		// Dritter Lauf: „dauerhaft" scheitert wieder – bei jedem Versuch
		$dienst->allePruefen(self::T0 + 190000);
		$this->assertFalse($dienst->laden($dauerhaft->id)->verrechnet);
		$this->assertCount($meldungenVorher + 2, $logger->meldungen);
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
	 * Ein angenommenes Remisangebot beendet die Partie, verrechnet sie als
	 * Remis und zählt sie in der Statistik als Remis.
	 */
	public function testRemisAngenommenWirdVerrechnet(): void
	{
		$spieler = Spieler::mitglied(7);
		$partie = $this->bisZumAngebot($spieler);

		$ende = $this->dienst->remis($spieler, null, $partie->id, 38, true, self::T0 + 40000);

		$this->assertSame(Partie::BEENDET, $ende->status);
		$this->assertSame('einigung', $ende->grund);
		$this->assertTrue($ende->verrechnet);

		$gespeichert = $this->dienst->laden($partie->id);
		$this->assertSame(Partie::REMIS, $gespeichert->ergebnis);
		$this->assertSame('einigung', $gespeichert->grund);
		$this->assertSame(19, $gespeichert->remisAngebot);
		$this->assertTrue($gespeichert->verrechnet);

		$stand = $this->db->fetchAssociative('SELECT partien, remis FROM tl_schachcomputer_spieler WHERE memberId=7');
		$this->assertSame(array(1, 1), array((int) $stand['partien'], (int) $stand['remis']));
		$this->assertSame(1, (int) $this->db->fetchOne("SELECT SUM(anzahl) FROM tl_schachcomputer_statistik WHERE art='remis'"));
	}

	/**
	 * Ein abgelehntes Angebot wird gespeichert; die Partie und die Uhr des
	 * Spielers laufen weiter, und ein zweites Angebot kommt zu früh.
	 */
	public function testRemisAbgelehntWirdGespeichert(): void
	{
		$spieler = Spieler::mitglied(7);
		$partie = $this->bisZumAngebot($spieler);

		$weiter = $this->dienst->remis($spieler, null, $partie->id, 38, false, self::T0 + 40000);
		$this->assertSame(Partie::LAEUFT, $weiter->status);

		$gespeichert = $this->dienst->laden($partie->id);
		$this->assertSame(Partie::LAEUFT, $gespeichert->status);
		$this->assertSame(19, $gespeichert->remisAngebot);
		$this->assertSame(self::T0 + 38000, $gespeichert->uhrSeit);
		$this->assertSame($partie->restzeit, $gespeichert->restzeit);
		$this->assertFalse($gespeichert->verrechnet);

		$this->expectExceptionObject(new PartieFehler(PartieFehler::NICHT_ERLAUBT));
		$this->dienst->remis($spieler, null, $partie->id, 38, true, self::T0 + 41000);
	}

	/**
	 * Eine Anfrage mit altem Stand (etwa aus einem zweiten Tab) überschreibt
	 * eine inzwischen gespeicherte Ablehnung nicht.
	 *
	 * Die Ablehnung ändert die Zugnummer nicht; ohne remisAngebot in der
	 * WHERE-Bedingung träfe das bedingte Speichern des alten Standes trotzdem
	 * und setzte remisAngebot auf 0 zurück – der Spieler dürfte sofort
	 * wieder anbieten.
	 */
	public function testAlterStandUeberschreibtGespeicherteAblehnungNicht(): void
	{
		$spieler = Spieler::mitglied(7);
		$partie = $this->bisZumAngebot($spieler);
		$veraltet = $this->dienst->laden($partie->id);

		$this->dienst->remis($spieler, null, $partie->id, 38, false, self::T0 + 40000);

		// Mit dem alten Stand (remisAngebot 0) wäre die Uhr des Spielers längst abgelaufen
		$geprueft = $this->dienst->pruefen($veraltet, null, self::T0 + 38000 + 180000 + 5000);

		$this->assertSame(Partie::LAEUFT, $geprueft->status, 'es gilt der neuere Stand aus der Datenbank');
		$this->assertSame(19, $geprueft->remisAngebot);

		$gespeichert = $this->dienst->laden($partie->id);
		$this->assertSame(Partie::LAEUFT, $gespeichert->status);
		$this->assertSame(19, $gespeichert->remisAngebot, 'die Ablehnung bleibt erhalten');
	}

	/**
	 * In fremden Partien lässt sich kein Remis anbieten.
	 */
	public function testRemisInFremderPartie(): void
	{
		$partie = $this->bisZumAngebot(Spieler::mitglied(7));

		foreach (array(Spieler::gast('xyz'), Spieler::mitglied(8)) as $fremder) {
			try {
				$this->dienst->remis($fremder, null, $partie->id, 38, true, self::T0 + 40000);
				$this->fail('Erwartet: PartieFehler');
			} catch (PartieFehler $fehler) {
				$this->assertSame(PartieFehler::NICHT_GEFUNDEN, $fehler->kennung());
			}
		}

		$this->assertSame(Partie::LAEUFT, $this->dienst->laden($partie->id)->status);
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
	 * Start, Ende und Übung werden gezählt, jedes Ende genau einmal.
	 */
	public function testStatistik(): void
	{
		$mitglied = Spieler::mitglied(7);
		$gast = Spieler::gast('abc');

		$erste = $this->dienst->starten($mitglied, null, $this->blitz, 1500, 'w', self::T0);
		$this->dienst->ziehen($mitglied, null, $erste->id, 0, 'e2e4', null, self::T0 + 1000);
		$this->dienst->aufgeben($mitglied, null, $erste->id, self::T0 + 2000);

		$zweite = $this->dienst->starten($gast, null, $this->blitz, 1500, 'w', self::T0 + 3000);
		$veraltet = $this->dienst->laden($zweite->id);
		$this->dienst->pruefen($veraltet, null, self::T0 + 3000 + 61001);
		$this->dienst->pruefen($this->dienst->laden($zweite->id), null, self::T0 + 3000 + 99999);
		$this->dienst->pruefen($veraltet, null, self::T0 + 3000 + 61002);

		$this->dienst->uebungSpeichern(7, 900, 'w', array('e2e4'), true, self::T0);

		$zaehler = array();

		foreach ($this->db->fetchAllAssociative('SELECT art, gast, SUM(anzahl) AS anzahl FROM tl_schachcomputer_statistik GROUP BY art, gast') as $zeile) {
			$zaehler[$zeile['art'].('1' === (string) $zeile['gast'] ? ':gast' : '')] = (int) $zeile['anzahl'];
		}

		ksort($zaehler);
		$this->assertSame(array('abgebrochen:gast' => 1, 'gestartet' => 1, 'gestartet:gast' => 1, 'uebung' => 1, 'verloren' => 1), $zaehler);
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

	/**
	 * Legt eine laufende Partie mit Weiß an, die schon 38 Halbzüge aus
	 * Musterpartie::ZUEGE hinter sich hat: Der Spieler hat 19 Züge gemacht, ist
	 * am Zug und darf zum ersten Mal Remis anbieten.
	 *
	 * Die Züge werden gesetzt statt einzeln gespielt, weil jeder Zug die
	 * ganze Partie nachspielt (siehe AblaufTest::gespielt()); den Weg über
	 * einzelne Züge prüft PartieControllerTest::testRemisAnbieten().
	 *
	 * @param Spieler $spieler Mitglied oder Gast
	 *
	 * @return Partie Die gespeicherte Partie mit ID (uhrSeit T0 + 38 s)
	 */
	private function bisZumAngebot(Spieler $spieler): Partie
	{
		$partie = Ablauf::starten($spieler->memberId() ?? 0, $spieler->gastkennung(), array('id' => $this->blitz, 'minuten' => 3, 'inkrement' => 2, 'klasse' => 'blitz'), 1500, 'w', self::T0);
		$partie->zuege = Musterpartie::zuege(38);
		$partie->uhrSeit = self::T0 + 38000;

		$this->db->insert('tl_schachcomputer_partie', $partie->alsZeile());
		$partie->id = (int) $this->db->lastInsertId();

		return $partie;
	}
}
