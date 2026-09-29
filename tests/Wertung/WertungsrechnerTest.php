<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Wertung;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Glicko2;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Spielerstand;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertung;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsrechner;

/**
 * Prüft das Verrechnen einer Partie samt Ruhezeit und Höchstwert.
 */
class WertungsrechnerTest extends TestCase
{
	private const TAG = 86400;

	/**
	 * Zähler und Zeitpunkt der letzten Partie werden fortgeschrieben.
	 */
	public function testZaehler(): void
	{
		$rechner = new Wertungsrechner(new Glicko2());
		$stand = $rechner->verrechnen(new Spielerstand(), 1500, 1.0, 1000);
		$stand = $rechner->verrechnen($stand, 1500, 0.5, 2000);
		$stand = $rechner->verrechnen($stand, 1500, 0.0, 3000);

		$this->assertSame(3, $stand->partien);
		$this->assertSame(1, $stand->siege);
		$this->assertSame(1, $stand->remis);
		$this->assertSame(1, $stand->niederlagen);
		$this->assertSame(3000, $stand->letztePartie);
	}

	/**
	 * Ein vorläufiger Spieler bekommt keinen Höchstwert, auch nicht nach
	 * einem großen Sprung.
	 */
	public function testKeinHoechstwertMitVorlaeufigerWertung(): void
	{
		$stand = (new Wertungsrechner(new Glicko2()))->verrechnen(new Spielerstand(), 1500, 1.0, 1000);

		$this->assertGreaterThan(1600, $stand->wertung->getWertung());
		$this->assertTrue(Wertungsrechner::vorlaeufig($stand->wertung->getAbweichung()));
		$this->assertSame(0.0, $stand->hoechstwert);
		$this->assertSame(0, $stand->hoechstwertDatum);
	}

	/**
	 * Mit gesicherter Wertung steigt der Höchstwert, fällt aber nie.
	 */
	public function testHoechstwertNurNachOben(): void
	{
		$rechner = new Wertungsrechner(new Glicko2());
		$sicher = new Spielerstand(new Wertung(1500, 60, 0.06), 30, 15, 0, 15, 1500.0, 500, 900);

		$gewonnen = $rechner->verrechnen($sicher, 1600, 1.0, 1000);
		$this->assertSame($gewonnen->wertung->getWertung(), $gewonnen->hoechstwert);
		$this->assertSame(1000, $gewonnen->hoechstwertDatum);

		$verloren = $rechner->verrechnen($gewonnen, 1400, 0.0, 2000);
		$this->assertLessThan($gewonnen->wertung->getWertung(), $verloren->wertung->getWertung());
		$this->assertSame($gewonnen->hoechstwert, $verloren->hoechstwert);
		$this->assertSame(1000, $verloren->hoechstwertDatum);
	}

	/**
	 * Vor dem Verrechnen wächst die Abweichung um die Ruhetage.
	 */
	public function testRuhezeitVorDemVerrechnen(): void
	{
		$rechner = new Wertungsrechner(new Glicko2());
		$stand = new Spielerstand(new Wertung(1500, 60, 0.06), 30, 15, 0, 15, 0.0, 0, 1000);

		$gleich = $rechner->verrechnen($stand, 1500, 0.5, 1000 + self::TAG - 1);
		$spaeter = $rechner->verrechnen($stand, 1500, 0.5, 1000 + 200 * self::TAG);

		$this->assertGreaterThan($gleich->wertung->getAbweichung(), $spaeter->wertung->getAbweichung());
		$this->assertEqualsWithDelta(60.0, $rechner->aktuell($stand, 1000 + self::TAG - 1)->getAbweichung(), 0.0001);
		$this->assertGreaterThan(110.0, $rechner->aktuell($stand, 1000 + 200 * self::TAG)->getAbweichung());
	}

	/**
	 * Ohne bisherige Partie gibt es keine Ruhezeit.
	 */
	public function testNeuerSpielerOhneRuhezeit(): void
	{
		$this->assertSame(350.0, (new Wertungsrechner(new Glicko2()))->aktuell(new Spielerstand(), 1790000000)->getAbweichung());
	}

	/**
	 * Stand aus Zeile und zurück bleibt gleich.
	 */
	public function testZeileHinUndZurueck(): void
	{
		$stand = new Spielerstand(new Wertung(1612.5, 88.1, 0.0601), 12, 7, 2, 3, 1640.0, 1790000000, 1790001000);

		$this->assertEquals($stand, Spielerstand::ausZeile(array_map('strval', $stand->alsZeile())));
	}
}
