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
use Psr\Log\NullLogger;
use Schachbulle\ContaoSchachcomputerBundle\Backend\PartiePgn;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Ablauf;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partie;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partiedienst;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Pgn;
use Schachbulle\ContaoSchachcomputerBundle\Partie\PgnExport;
use Schachbulle\ContaoSchachcomputerBundle\Statistik\Statistik;
use Schachbulle\ContaoSchachcomputerBundle\Tests\Datenbank;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Glicko2;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsdienst;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsrechner;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Prüft PGN-Erzeugung und -Export.
 */
class PgnTest extends TestCase
{
	private Connection $db;

	private Partiedienst $partiedienst;

	/**
	 * Legt Datenbank und Dienste an.
	 */
	protected function setUp(): void
	{
		$this->db = Datenbank::verbindung();
		$this->partiedienst = new Partiedienst($this->db, new Wertungsdienst($this->db, new Wertungsrechner(new Glicko2())), new Statistik($this->db, new NullLogger()), new NullLogger());
	}

	/**
	 * Kopf und Zugtext einer gewerteten Partie.
	 */
	public function testGewertetePartie(): void
	{
		$partie = $this->narrenmatt();

		$pgn = Pgn::erzeugen($partie, 'Mustermann, Max', 'example.org');

		$this->assertStringContainsString('[Event "Partie gegen Stockfish"]', $pgn);
		$this->assertStringContainsString('[Site "example.org"]', $pgn);
		$this->assertStringContainsString('[White "Stockfish 19 (Stufe 1500)"]', $pgn);
		$this->assertStringContainsString('[Black "Mustermann, Max"]', $pgn);
		$this->assertStringContainsString('[Result "0-1"]', $pgn);
		$this->assertStringContainsString('[TimeControl "180+2"]', $pgn);
		$this->assertStringContainsString('[Termination "normal"]', $pgn);
		$this->assertStringEndsWith("\n\n1. f3 e5 2. g4 Qh4# 0-1\n", $pgn);
	}

	/**
	 * Ein Remis durch Einigung steht als 1/2-1/2 in der PGN und gilt als
	 * regulär beendet.
	 */
	public function testRemisDurchEinigung(): void
	{
		$t0 = 1790000000000;
		$partie = Ablauf::starten(7, '', array('id' => 1, 'minuten' => 3, 'inkrement' => 2, 'klasse' => 'blitz'), 1500, 'w', $t0);
		// 19 eigene Züge gesetzt statt einzeln gespielt (siehe AblaufTest::gespielt())
		$partie->zuege = \array_slice(explode(' ', AblaufTest::ZUEGE), 0, 38);
		$partie->uhrSeit = $t0 + 38000;

		Ablauf::remis($partie, 38, true, $t0 + 40000);
		$pgn = Pgn::erzeugen($partie, 'Mustermann, Max', 'example.org');

		$this->assertStringContainsString('[Result "1/2-1/2"]', $pgn);
		$this->assertStringContainsString('[Termination "normal"]', $pgn);
		$this->assertMatchesRegularExpression('/19\. Qd2\s+h5\s+1\/2-1\/2\n$/', $pgn);
	}

	/**
	 * Übungspartien ohne Zeitkontrolle, unbeendete mit „*".
	 */
	public function testUebung(): void
	{
		$pgn = Pgn::erzeugen(Ablauf::uebung(7, 800, 'w', array('e2e4'), false, 1790000000000), 'Mustermann, Max', 'example.org');

		$this->assertStringContainsString('[Event "Übungspartie gegen Stockfish"]', $pgn);
		$this->assertStringContainsString('[TimeControl "-"]', $pgn);
		$this->assertStringContainsString('[Termination "unterminated"]', $pgn);
		$this->assertStringEndsWith("1. e4 *\n", $pgn);
	}

	/**
	 * Lange Zugtexte werden bei 80 Zeichen umbrochen; Anführungszeichen im Namen maskiert.
	 */
	public function testUmbruchUndMaskierung(): void
	{
		$zuege = explode(' ', 'g1f3 g8f6 f3g1 f6g8 b1c3 b8c6 c3b1 c6b8 e2e4 e7e5 d2d4 d7d5 c2c4 c7c5 a2a3 a7a6 h2h3 h7h6 b2b3 b7b6 g2g3 g7g6');
		$partie = Ablauf::uebung(7, 800, 'w', $zuege, false, 1790000000000);

		$pgn = Pgn::erzeugen($partie, 'Der "Blitz"', 'example.org');

		foreach (explode("\n", $pgn) as $zeile) {
			$this->assertLessThanOrEqual(80, \strlen($zeile), $zeile);
		}

		$this->assertStringContainsString('[White "Der \"Blitz\""]', $pgn);
	}

	/**
	 * Steuerzeichen in Kopfwerten würden die PGN zerbrechen: Sie werden zu
	 * einem Leerzeichen, am Rand entfernt.
	 */
	public function testSteuerzeichenImKopf(): void
	{
		$pgn = Pgn::erzeugen($this->narrenmatt(), "Muster\r\nmann,\tMax\x07 ", "example.org\n");

		$this->assertStringContainsString('[Black "Muster mann, Max"]', $pgn);
		$this->assertStringContainsString('[Site "example.org"]', $pgn);
		$this->assertSame(9, substr_count($pgn, "]\n"), 'jeder Kopfeintrag auf einer Zeile');
	}

	/**
	 * Contao speichert Namen teils HTML-kodiert (&#40; usw.); die PGN enthält
	 * sie dekodiert, Anführungszeichen maskiert, ohne Steuerzeichen.
	 */
	public function testExportDekodiertNamen(): void
	{
		$max = Datenbank::mitglied($this->db, 'Max &#40;Jr.&#41; &quot;Blitz&quot;', "Mustermann\t&amp;\r\nSöhne");
		$this->partiedienst->uebungSpeichern($max, 800, 'w', array('e2e4'), true, 1790000000000);

		$pgn = (new PgnExport($this->db, $this->partiedienst))->mitglied($max, 'example.org');

		$this->assertStringContainsString('[White "Mustermann & Söhne, Max (Jr.) \"Blitz\""]', $pgn);
	}

	/**
	 * Der Export setzt den vollen Namen ein und fasst alle beendeten Partien zusammen.
	 */
	public function testExportUndBackend(): void
	{
		$max = Datenbank::mitglied($this->db, 'Max', 'Mustermann');
		$this->partiedienst->uebungSpeichern($max, 800, 'w', array('e2e4'), true, 1790000000000);
		$this->partiedienst->uebungSpeichern($max, 900, 'b', array('d2d4'), false, 1790000100000);

		$export = new PgnExport($this->db, $this->partiedienst);
		$alle = $export->mitglied($max, 'example.org');

		$this->assertSame(2, substr_count($alle, '[Event '));
		$this->assertStringContainsString('[White "Mustermann, Max"]', $alle);
		$this->assertLessThan(strpos($alle, '1. d4'), strpos($alle, '1. e4'));
		$this->assertSame(2, $this->partiedienst->anzahlEigenePartien($max));
		$this->assertCount(1, $this->partiedienst->eigenePartien($max, 1, 1));

		$backend = new PartiePgn($this->partiedienst, $export, new RequestStack());
		$this->assertStringContainsString('1. e4', (string) $backend->pgn(1, 'example.org'));
		$this->assertNull($backend->pgn(99, 'example.org'));
	}

	/**
	 * Baut das Narrenmatt mit dem Spieler als Schwarz.
	 *
	 * @return Partie Die beendete Partie
	 */
	private function narrenmatt(): Partie
	{
		$t0 = 1790000000000;
		$partie = Ablauf::starten(7, '', array('id' => 1, 'minuten' => 3, 'inkrement' => 2, 'klasse' => 'blitz'), 1500, 'b', $t0);

		foreach (array('f2f3', 'e7e5', 'g2g4', 'd8h4') as $index => $zug) {
			Ablauf::zug($partie, $zug, $index, null, $t0 + ($index + 1) * 1000);
		}

		return $partie;
	}
}
