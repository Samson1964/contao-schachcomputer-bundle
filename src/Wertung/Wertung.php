<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Wertung;

/**
 * Eine Glicko-2-Wertung: Wertungszahl, Abweichung und Volatilität.
 *
 * Die Werte stehen in der gewohnten Elo-ähnlichen Skala (Start 1500 / 350 /
 * 0,06). Die Umrechnung in die interne Glicko-2-Skala übernimmt Glicko2.
 * Die Klasse ist unveränderlich; eine Berechnung liefert immer ein neues Objekt.
 */
final class Wertung
{
	private float $wertung;

	private float $abweichung;

	private float $volatilitaet;

	/**
	 * Legt die drei Werte fest.
	 *
	 * @param float $wertung      Wertungszahl, z. B. 1500
	 * @param float $abweichung   Abweichung (RD), je kleiner, desto sicherer
	 * @param float $volatilitaet Wie stark die Leistung schwankt, üblich 0,06
	 */
	public function __construct(float $wertung = 1500.0, float $abweichung = 350.0, float $volatilitaet = 0.06)
	{
		$this->wertung = $wertung;
		$this->abweichung = $abweichung;
		$this->volatilitaet = $volatilitaet;
	}

	/**
	 * Liefert die Wertungszahl.
	 *
	 * @return float Die ungerundete Wertungszahl
	 */
	public function getWertung(): float
	{
		return $this->wertung;
	}

	/**
	 * Liefert die Abweichung (RD).
	 *
	 * @return float Die ungerundete Abweichung
	 */
	public function getAbweichung(): float
	{
		return $this->abweichung;
	}

	/**
	 * Liefert die Volatilität.
	 *
	 * @return float Die Volatilität, üblicherweise um 0,06
	 */
	public function getVolatilitaet(): float
	{
		return $this->volatilitaet;
	}
}
