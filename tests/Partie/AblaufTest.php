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
use Schachbulle\ContaoSchachcomputerBundle\Partie\Ablauf;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partie;
use Schachbulle\ContaoSchachcomputerBundle\Partie\PartieFehler;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Schiedsrichter;

/**
 * Prüft die Regeln einer Partie mit festen Zeitpunkten.
 */
class AblaufTest extends TestCase
{
	/**
	 * Startzeitpunkt aller Testpartien in Millisekunden.
	 */
	private const T0 = 1790000000000;

	/**
	 * Der Start übernimmt die Bedenkzeit und stellt die Uhr.
	 */
	public function testStarten(): void
	{
		$partie = $this->partie('b');

		$this->assertSame(Partie::LAEUFT, $partie->status);
		$this->assertSame(7, $partie->memberId);
		$this->assertSame(3, $partie->bedenkzeit);
		$this->assertSame('blitz', $partie->klasse);
		$this->assertSame(180000, $partie->restzeit);
		$this->assertSame(self::T0, $partie->uhrSeit);
		$this->assertSame(1790000000, $partie->beginn);
		$this->assertFalse($partie->spielerAmZug());
	}

	/**
	 * Unbekannte Stufen und Farben werden abgewiesen.
	 */
	public function testStartenMitUngueltigerStufe(): void
	{
		$this->expectExceptionObject(new PartieFehler(PartieFehler::UNGUELTIG));

		Ablauf::starten(7, '', $this->bedenkzeit(), 1550, 'w', self::T0);
	}

	/**
	 * Der erste eigene Zug kostet keine Zeit und bringt keine Gutschrift,
	 * ab dem zweiten wird abgezogen und gutgeschrieben.
	 */
	public function testUhrLaeuftAbDemZweitenZug(): void
	{
		$partie = $this->partie('w');

		Ablauf::zug($partie, 'e2e4', 0, 5000, self::T0 + 5000);
		$this->assertSame(array(0), $partie->zeiten);
		$this->assertSame(180000, $partie->restzeit);
		$this->assertFalse($partie->spielerAmZug());

		Ablauf::zug($partie, 'e7e5', 1, null, self::T0 + 7000);
		$this->assertTrue($partie->spielerAmZug());
		$this->assertSame(self::T0 + 7000, $partie->uhrSeit);

		// Server misst 10 s, der Browser meldet 9,5 s: angerechnet werden 9,5 s
		Ablauf::zug($partie, 'g1f3', 2, 9500, self::T0 + 17000);
		$this->assertSame(array(0, 9500), $partie->zeiten);
		$this->assertSame(180000 - 9500 + 2000, $partie->restzeit);
		$this->assertSame(array('e2e4', 'e7e5', 'g1f3'), $partie->zuege);
	}

	/**
	 * Eine veraltete Zugnummer wird abgewiesen, ohne etwas zu ändern.
	 */
	public function testVeralteteZugnummer(): void
	{
		$partie = $this->partie('w');
		Ablauf::zug($partie, 'e2e4', 0, null, self::T0 + 1000);

		try {
			Ablauf::zug($partie, 'e7e5', 0, null, self::T0 + 2000);
			$this->fail('Erwartet: PartieFehler');
		} catch (PartieFehler $fehler) {
			$this->assertSame(PartieFehler::VERALTET, $fehler->kennung());
			$this->assertSame(409, $fehler->status());
		}

		$this->assertSame(array('e2e4'), $partie->zuege);
	}

	/**
	 * Ein regelwidriger Zug wird abgewiesen.
	 */
	public function testRegelwidrigerZug(): void
	{
		$partie = $this->partie('w');

		try {
			Ablauf::zug($partie, 'e2e5', 0, null, self::T0 + 1000);
			$this->fail('Erwartet: PartieFehler');
		} catch (PartieFehler $fehler) {
			$this->assertSame(PartieFehler::UNGUELTIG, $fehler->kennung());
			$this->assertSame(422, $fehler->status());
		}

		$this->assertSame(array(), $partie->zuege);
		$this->assertSame(Partie::LAEUFT, $partie->status);
	}

	/**
	 * Kommt der zweite Zug nach Ablauf der Zeit, ist die Partie verloren.
	 */
	public function testZeitueberschreitungBeimZug(): void
	{
		$partie = $this->partie('w');
		Ablauf::zug($partie, 'e2e4', 0, null, self::T0 + 1000);
		Ablauf::zug($partie, 'e7e5', 1, null, self::T0 + 2000);

		Ablauf::zug($partie, 'g1f3', 2, 200000, self::T0 + 2000 + 200000);

		$this->assertSame(Partie::BEENDET, $partie->status);
		$this->assertSame('zeit', $partie->grund);
		$this->assertSame(Partie::SIEG_SCHWARZ, $partie->ergebnis);
		$this->assertSame(0, $partie->restzeit);
		$this->assertSame(array('e2e4', 'e7e5'), $partie->zuege);
	}

	/**
	 * Ohne Mattmaterial der Engine endet die Zeitüberschreitung remis.
	 */
	public function testZeitueberschreitungOhneMattmaterialDerEngine(): void
	{
		$partie = $this->partie('w');

		$this->assertSame(0.5, Ablauf::punkteBeiZeitablauf($partie, new Schiedsrichter('kn6/8/8/8/8/8/8/K6Q w - - 0 1')));
		$this->assertSame(0.0, Ablauf::punkteBeiZeitablauf($partie, new Schiedsrichter('kr6/8/8/8/8/8/8/K6Q w - - 0 1')));
	}

	/**
	 * Setzt der Spieler matt, gewinnt er.
	 */
	public function testSpielerSetztMatt(): void
	{
		$partie = $this->partie('b');
		Ablauf::zug($partie, 'f2f3', 0, null, self::T0 + 1000);
		Ablauf::zug($partie, 'e7e5', 1, null, self::T0 + 3000);
		Ablauf::zug($partie, 'g2g4', 2, null, self::T0 + 4000);
		Ablauf::zug($partie, 'd8h4', 3, 1000, self::T0 + 5000);

		$this->assertSame(Partie::BEENDET, $partie->status);
		$this->assertSame('matt', $partie->grund);
		$this->assertSame(Partie::SIEG_SCHWARZ, $partie->ergebnis);
		$this->assertSame(1.0, $partie->punkte());
	}

	/**
	 * Setzt die Engine matt, verliert der Spieler.
	 */
	public function testEngineSetztMatt(): void
	{
		$partie = $this->partie('w');
		Ablauf::zug($partie, 'f2f3', 0, null, self::T0 + 1000);
		Ablauf::zug($partie, 'e7e5', 1, null, self::T0 + 2000);
		Ablauf::zug($partie, 'g2g4', 2, 1000, self::T0 + 3000);
		Ablauf::zug($partie, 'd8h4', 3, null, self::T0 + 4000);

		$this->assertSame('matt', $partie->grund);
		$this->assertSame(0.0, $partie->punkte());
	}

	/**
	 * Bleibt der Engine-Zug nach dem ersten eigenen Zug aus, ist die Partie verloren.
	 */
	public function testEngineZugBleibtAus(): void
	{
		$partie = $this->partie('w');
		Ablauf::zug($partie, 'e2e4', 0, null, self::T0 + 1000);

		$this->assertFalse(Ablauf::pruefen($partie, self::T0 + 1000 + 60000));
		$this->assertTrue(Ablauf::pruefen($partie, self::T0 + 1000 + 60001));
		$this->assertSame(Partie::BEENDET, $partie->status);
		$this->assertSame('verlassen', $partie->grund);
		$this->assertSame(0.0, $partie->punkte());
	}

	/**
	 * Bleibt schon der erste Engine-Zug aus, wird ungewertet abgebrochen.
	 */
	public function testErsterEngineZugBleibtAus(): void
	{
		$partie = $this->partie('b');

		$this->assertTrue(Ablauf::pruefen($partie, self::T0 + 60001));
		$this->assertSame(Partie::ABGEBROCHEN, $partie->status);
		$this->assertSame('verlassen', $partie->grund);
		$this->assertNull($partie->punkte());
	}

	/**
	 * Wer nicht innerhalb der Frist den ersten Zug macht, dessen Partie wird
	 * ungewertet abgebrochen – beim Prüfen wie beim verspäteten Zug.
	 */
	public function testErsterZugZuSpaet(): void
	{
		$geprueft = $this->partie('w');
		$this->assertFalse(Ablauf::pruefen($geprueft, self::T0 + 61000));
		$this->assertTrue(Ablauf::pruefen($geprueft, self::T0 + 61001));
		$this->assertSame('erster_zug', $geprueft->grund);

		$gezogen = $this->partie('w');
		Ablauf::zug($gezogen, 'e2e4', 0, null, self::T0 + 61001);
		$this->assertSame(Partie::ABGEBROCHEN, $gezogen->status);
		$this->assertSame(array(), $gezogen->zuege);
	}

	/**
	 * Die Uhr läuft beim Prüfen erst nach Restzeit plus Ausgleich ab.
	 */
	public function testPruefenMitLaufenderUhr(): void
	{
		$partie = $this->partie('w');
		Ablauf::zug($partie, 'e2e4', 0, null, self::T0 + 1000);
		Ablauf::zug($partie, 'e7e5', 1, null, self::T0 + 2000);

		$this->assertFalse(Ablauf::pruefen($partie, self::T0 + 2000 + 181000));
		$this->assertTrue(Ablauf::pruefen($partie, self::T0 + 2000 + 181001));
		$this->assertSame('zeit', $partie->grund);
		$this->assertFalse(Ablauf::pruefen($partie, self::T0 + 999999));
	}

	/**
	 * Aufgeben ist jederzeit möglich und zählt als Niederlage.
	 */
	public function testAufgeben(): void
	{
		$partie = $this->partie('w');
		Ablauf::aufgeben($partie, self::T0 + 1000);

		$this->assertSame('aufgabe', $partie->grund);
		$this->assertSame(Partie::SIEG_SCHWARZ, $partie->ergebnis);

		$this->expectExceptionObject(new PartieFehler(PartieFehler::BEENDET));
		Ablauf::aufgeben($partie, self::T0 + 2000);
	}

	/**
	 * Abbrechen geht nur vor dem ersten eigenen Zug.
	 */
	public function testAbbrechen(): void
	{
		$vorher = $this->partie('b');
		Ablauf::zug($vorher, 'e2e4', 0, null, self::T0 + 1000);
		Ablauf::abbrechen($vorher, self::T0 + 2000);
		$this->assertSame(Partie::ABGEBROCHEN, $vorher->status);
		$this->assertSame(Partie::OFFEN, $vorher->ergebnis);

		$nachher = $this->partie('w');
		Ablauf::zug($nachher, 'e2e4', 0, null, self::T0 + 1000);
		$this->expectExceptionObject(new PartieFehler(PartieFehler::NICHT_ERLAUBT));
		Ablauf::abbrechen($nachher, self::T0 + 2000);
	}

	/**
	 * Die Übungspartie bestimmt ihr Ergebnis aus der Stellung.
	 */
	public function testUebungMitMatt(): void
	{
		$partie = Ablauf::uebung(7, 800, 'b', array('f2f3', 'e7e5', 'g2g4', 'd8h4'), false, self::T0);

		$this->assertFalse($partie->gewertet);
		$this->assertTrue($partie->verrechnet);
		$this->assertSame(Partie::BEENDET, $partie->status);
		$this->assertSame('matt', $partie->grund);
		$this->assertSame(Partie::SIEG_SCHWARZ, $partie->ergebnis);
	}

	/**
	 * Ohne Ende nach den Regeln zählt die Aufgabe, sonst bleibt das Ergebnis offen.
	 */
	public function testUebungAufgegebenOderUnbeendet(): void
	{
		$aufgegeben = Ablauf::uebung(7, 800, 'w', array('e2e4', 'e7e5'), true, self::T0);
		$this->assertSame('aufgabe', $aufgegeben->grund);
		$this->assertSame(Partie::SIEG_SCHWARZ, $aufgegeben->ergebnis);

		$offen = Ablauf::uebung(7, 800, 'w', array('e2e4', 'e7e5'), false, self::T0);
		$this->assertSame('unbeendet', $offen->grund);
		$this->assertSame(Partie::OFFEN, $offen->ergebnis);
	}

	/**
	 * Unsinnige Zuglisten werden abgewiesen.
	 *
	 * @dataProvider ungueltigeUebungen
	 *
	 * @param array<int, mixed> $zuege
	 */
	public function testUngueltigeUebung(array $zuege): void
	{
		$this->expectExceptionObject(new PartieFehler(PartieFehler::UNGUELTIG));

		Ablauf::uebung(7, 800, 'w', $zuege, false, self::T0);
	}

	/**
	 * Liefert ungültige Zuglisten.
	 *
	 * @return array<string, array{array<int, mixed>}>
	 */
	public function ungueltigeUebungen(): array
	{
		return array(
			'leer'          => array(array()),
			'regelwidrig'   => array(array('e2e4', 'e2e4')),
			'keine Zeichen' => array(array(42)),
		);
	}

	/**
	 * Eine Partie aus Datenbankzeile und zurück bleibt gleich.
	 */
	public function testZeileHinUndZurueck(): void
	{
		$partie = $this->partie('w');
		Ablauf::zug($partie, 'e2e4', 0, 1200, self::T0 + 1500);

		$zeile = array('id' => '5') + array_map('strval', $partie->alsZeile());
		$zurueck = Partie::ausZeile($zeile);

		$this->assertSame(5, $zurueck->id);
		$this->assertSame(array('e2e4'), $zurueck->zuege);
		$this->assertSame(array(0), $zurueck->zeiten);
		$this->assertSame($partie->uhrSeit, $zurueck->uhrSeit);
		$this->assertTrue($zurueck->gewertet);
		$this->assertSame(1, $partie->alsZeile()['zugnummer']);
	}

	/**
	 * Legt eine Blitzpartie 3+2 gegen Stufe 1500 an.
	 *
	 * @param string $farbe Farbe des Spielers
	 *
	 * @return Partie Die laufende Partie
	 */
	private function partie(string $farbe): Partie
	{
		return Ablauf::starten(7, '', $this->bedenkzeit(), 1500, $farbe, self::T0);
	}

	/**
	 * Liefert eine Bedenkzeit 3+2 im Blitz.
	 *
	 * @return array{id: int, minuten: int, inkrement: int, klasse: string}
	 */
	private function bedenkzeit(): array
	{
		return array('id' => 3, 'minuten' => 3, 'inkrement' => 2, 'klasse' => 'blitz');
	}
}
