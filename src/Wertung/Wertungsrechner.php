<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Wertung;

use Schachbulle\ContaoSchachcomputerBundle\Engine\Stufen;

/**
 * Verrechnet eine Partie gegen eine Engine-Stufe, ohne Datenbank.
 *
 * Reihenfolge: Erst wächst die Abweichung für die Tage seit der letzten
 * Partie (Ruhezeit), dann wird die Partie nach Glicko-2 verrechnet, zuletzt
 * wird der Höchstwert fortgeschrieben – aber nur mit gesicherter Wertung,
 * sonst beherrschten die großen Ausschläge der ersten Partien die ewige Liste.
 */
class Wertungsrechner
{
	/**
	 * Bis zu dieser Abweichung gilt eine Wertung als gesichert; darüber ist
	 * sie vorläufig (wie bei Lichess und im Schachaufgaben-Bundle).
	 */
	public const GESICHERT = 110.0;

	private Glicko2 $glicko;

	/**
	 * Übernimmt den Glicko-2-Rechner.
	 *
	 * @param Glicko2 $glicko Rechner mit den Standardkonstanten (τ 0,5, Abweichung 45–350)
	 */
	public function __construct(Glicko2 $glicko)
	{
		$this->glicko = $glicko;
	}

	/**
	 * Berechnet den Stand nach einer gewerteten Partie.
	 *
	 * Der Cronjob holt gescheiterte Verrechnungen nach (Partiedienst::allePruefen);
	 * dabei kann eine ältere Partie erst nach einer schon verrechneten neueren
	 * an die Reihe kommen. letztePartie wird deshalb nie zurückdatiert, sondern
	 * bleibt beim jüngeren der beiden Zeitpunkte – sonst zählte die Ruhezeit der
	 * nächsten echten Partie fälschlich ab dem älteren Datum.
	 *
	 * @param Spielerstand $stand  Der Stand vor der Partie
	 * @param int          $stufe  Die Engine-Stufe, gegen die gespielt wurde
	 * @param float        $punkte 1, 0,5 oder 0 aus Sicht des Spielers
	 * @param int          $ende   Ende der Partie in Sekunden
	 *
	 * @return Spielerstand Der neue Stand
	 */
	public function verrechnen(Spielerstand $stand, int $stufe, float $punkte, int $ende): Spielerstand
	{
		$vorher = $this->aktuell($stand, $ende);
		$neu = $this->glicko->versuch($vorher, new Wertung((float) $stufe, Stufen::GEGNER_ABWEICHUNG), $punkte);

		$hoechstwert = $stand->hoechstwert;
		$hoechstwertDatum = $stand->hoechstwertDatum;

		if ($neu->getAbweichung() <= self::GESICHERT && $neu->getWertung() > $hoechstwert) {
			$hoechstwert = $neu->getWertung();
			$hoechstwertDatum = $ende;
		}

		return new Spielerstand(
			$neu,
			$stand->partien + 1,
			$stand->siege + (1.0 === $punkte ? 1 : 0),
			$stand->remis + (0.5 === $punkte ? 1 : 0),
			$stand->niederlagen + (0.0 === $punkte ? 1 : 0),
			$hoechstwert,
			$hoechstwertDatum,
			max($stand->letztePartie, $ende),
		);
	}

	/**
	 * Liefert die Wertung zu einem Zeitpunkt, mit gewachsener Abweichung.
	 *
	 * @param Spielerstand $stand     Der gespeicherte Stand
	 * @param int          $zeitpunkt Zeitpunkt in Sekunden, etwa jetzt oder ein Stichtag
	 *
	 * @return Wertung Wertungszahl unverändert, Abweichung um die vollen Tage
	 *                 seit der letzten Partie gewachsen
	 */
	public function aktuell(Spielerstand $stand, int $zeitpunkt): Wertung
	{
		if ($stand->letztePartie <= 0) {
			return $stand->wertung;
		}

		return $this->glicko->ruhen($stand->wertung, intdiv(max(0, $zeitpunkt - $stand->letztePartie), 86400));
	}

	/**
	 * Prüft, ob eine Wertung vorläufig ist.
	 *
	 * @param float $abweichung Die (fortgeschriebene) Abweichung
	 *
	 * @return bool true über GESICHERT
	 */
	public static function vorlaeufig(float $abweichung): bool
	{
		return $abweichung > self::GESICHERT;
	}
}
