<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Partie;

use PChess\Chess\Chess;
use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Schiedsrichter;

/**
 * Prüft Zugprüfung und Partieende.
 */
class SchiedsrichterTest extends TestCase
{
	/**
	 * Ein erlaubter Zug wird ausgeführt und in beiden Schreibweisen gemerkt.
	 */
	public function testErlaubterZug(): void
	{
		$schiedsrichter = new Schiedsrichter();

		$this->assertTrue($schiedsrichter->ziehen('e2e4'));
		$this->assertSame('b', $schiedsrichter->amZug());
		$this->assertSame(array('e2e4'), $schiedsrichter->uci());
		$this->assertSame(array('e4'), $schiedsrichter->san());
	}

	/**
	 * Regelwidrige Züge, falsche Seite und Unsinn ändern nichts.
	 */
	public function testAbgelehnteZuegeAendernNichts(): void
	{
		$schiedsrichter = new Schiedsrichter();

		$this->assertFalse($schiedsrichter->ziehen('e2e5'));
		$this->assertFalse($schiedsrichter->ziehen('e7e5'));
		$this->assertFalse($schiedsrichter->ziehen('xyz'));
		$this->assertFalse($schiedsrichter->ziehen('e2e4q'));
		$this->assertSame(array(), $schiedsrichter->uci());
		$this->assertSame('w', $schiedsrichter->amZug());
	}

	/**
	 * Eine Umwandlung braucht die Figur; mit Figur steht sie in der PGN.
	 */
	public function testUmwandlung(): void
	{
		$schiedsrichter = Schiedsrichter::nachspielen(explode(' ', 'a2a4 b7b5 a4b5 a7a6 b5a6 c8b7 a6b7 b8c6'));

		$this->assertFalse($schiedsrichter->ziehen('b7a8'));
		$this->assertTrue($schiedsrichter->ziehen('b7a8q'));
		$this->assertSame('bxa8=Q', $schiedsrichter->san()[8]);
	}

	/**
	 * Die kurze Rochade ist ein Königszug e1g1 und steht als O-O in der PGN.
	 */
	public function testRochade(): void
	{
		$schiedsrichter = Schiedsrichter::nachspielen(explode(' ', 'e2e4 e7e5 g1f3 b8c6 f1c4 f8c5'));

		$this->assertTrue($schiedsrichter->ziehen('e1g1'));
		$this->assertSame('O-O', $schiedsrichter->san()[6]);
		$this->assertStringStartsWith('r1bqk1nr/pppp1ppp/2n5/2b1p3/2B1P3/5N2/PPPP1PPP/RNBQ1RK1 b kq', $schiedsrichter->fen());
	}

	/**
	 * Schlagen en passant entfernt den vorbeigezogenen Bauern.
	 */
	public function testEnPassant(): void
	{
		$schiedsrichter = Schiedsrichter::nachspielen(explode(' ', 'e2e4 a7a6 e4e5 d7d5'));

		$this->assertTrue($schiedsrichter->ziehen('e5d6'));
		$this->assertSame('exd6', $schiedsrichter->san()[4]);
		$this->assertStringStartsWith('rnbqkbnr/1pp1pppp/p2P4/8/8/8/PPPP1PPP/RNBQKBNR b KQkq', $schiedsrichter->fen());
	}

	/**
	 * Narrenmatt: Weiß ist matt und am Zug, weitere Züge gibt es nicht.
	 */
	public function testMatt(): void
	{
		$schiedsrichter = Schiedsrichter::nachspielen(array('f2f3', 'e7e5', 'g2g4', 'd8h4'));

		$this->assertSame('matt', $schiedsrichter->ende());
		$this->assertSame('w', $schiedsrichter->amZug());
		$this->assertFalse($schiedsrichter->ziehen('a2a3'));
	}

	/**
	 * Das kürzeste bekannte Patt (Sam Loyd) in zehn Zügen.
	 */
	public function testPatt(): void
	{
		$schiedsrichter = Schiedsrichter::nachspielen(explode(' ', 'e2e3 a7a5 d1h5 a8a6 h5a5 h7h5 h2h4 a6h6 a5c7 f7f6 c7d7 e8f7 d7b7 d8d3 b7b8 d3h7 b8c8 f7g6 c8e6'));

		$this->assertSame('patt', $schiedsrichter->ende());
	}

	/**
	 * König gegen König ist remis.
	 */
	public function testUngenuegendesMaterial(): void
	{
		$this->assertSame('material', (new Schiedsrichter('8/8/8/8/8/8/8/k6K w - - 0 1'))->ende());
	}

	/**
	 * Springer hin und her: Die Stellung nach 1. Sf3 steht nach dem ersten,
	 * fünften und neunten Halbzug auf dem Brett. Nach dem achten Halbzug ist
	 * die Grundstellung zum dritten Mal da, zählt bei p-chess aber erst
	 * zweimal (siehe Klassenkommentar des Schiedsrichters).
	 */
	public function testDreifacheWiederholung(): void
	{
		$zuege = explode(' ', 'g1f3 g8f6 f3g1 f6g8 g1f3 g8f6 f3g1 f6g8');

		$this->assertNull(Schiedsrichter::nachspielen($zuege)->ende());
		$this->assertSame('wiederholung', Schiedsrichter::nachspielen(array_merge($zuege, array('g1f3')))->ende());
	}

	/**
	 * Nach 100 Halbzügen ohne Bauernzug und Schlagen ist Schluss.
	 */
	public function testFuenfzigZuegeRegel(): void
	{
		$schiedsrichter = new Schiedsrichter('k7/8/8/8/8/8/8/K6R w - - 99 80');

		$this->assertNull($schiedsrichter->ende());
		$this->assertTrue($schiedsrichter->ziehen('h1h2'));
		$this->assertSame('fuenfzig', $schiedsrichter->ende());
	}

	/**
	 * Ein ungültiger Zug in der Liste wird mit seiner Nummer gemeldet.
	 */
	public function testNachspielenMeldetUngueltigenZug(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('als 2. Halbzug');

		Schiedsrichter::nachspielen(array('e2e4', 'e2e4'));
	}

	/**
	 * Mattmaterial nach der vereinfachten Regel.
	 *
	 * @dataProvider materialstellungen
	 */
	public function testMattmaterial(string $fen, bool $erwartet): void
	{
		$this->assertSame($erwartet, (new Schiedsrichter($fen))->mattmaterial('b'));
	}

	/**
	 * Liefert Stellungen und ob Schwarz dort Mattmaterial hat.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public function materialstellungen(): array
	{
		return array(
			'nur König'         => array('k7/8/8/8/8/8/8/K6Q w - - 0 1', false),
			'König und Springer' => array('kn6/8/8/8/8/8/8/K6Q w - - 0 1', false),
			'König und Läufer'  => array('kb6/8/8/8/8/8/8/K6Q w - - 0 1', false),
			'König und Bauer'   => array('k7/p7/8/8/8/8/8/K6Q w - - 0 1', true),
			'König und Turm'    => array('kr6/8/8/8/8/8/8/K6Q w - - 0 1', true),
			'zwei Springer'     => array('knn5/8/8/8/8/8/8/K6Q w - - 0 1', true),
		);
	}

	/**
	 * Eine lange Partie ist schnell genug nachgespielt, um es bei jedem Zug
	 * zu tun. Gemessen wurden knapp 80 ms; die Grenze lässt Luft für
	 * langsamere Rechner.
	 */
	public function testNachspielenIstSchnellGenug(): void
	{
		mt_srand(7);
		$chess = new Chess();
		$zuege = array();

		while (\count($zuege) < 200 && !$chess->gameOver()) {
			$moeglich = $chess->moves();
			$zug = $moeglich[array_rand($moeglich)];
			$chess->move(array('from' => $zug->from, 'to' => $zug->to, 'promotion' => $zug->promotion));
			$zuege[] = $zug->from.$zug->to.($zug->promotion ?? '');
		}

		$start = microtime(true);
		Schiedsrichter::nachspielen($zuege);
		$dauer = (microtime(true) - $start) * 1000;

		$this->assertLessThan(250, $dauer, sprintf('%d Halbzüge in %.0f ms', \count($zuege), $dauer));
	}
}
