<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Verlauf;

/**
 * Zeichnet einen Wertungsverlauf als SVG, ohne Bibliothek und ohne Skript.
 *
 * Waagerecht steht jede Partie im gleichen Abstand (nicht die Zeit), damit
 * lange Pausen die Kurve nicht zerreißen. Die senkrechte Achse reicht von
 * der nächstniedrigeren bis zur nächsthöheren Fünfzigerzahl. Mehr als
 * MAX_PUNKTE Punkte werden gleichmäßig ausgedünnt.
 */
final class Kurve
{
	public const MAX_PUNKTE = 300;

	private const LINKS = 48;

	private const RECHTS = 12;

	private const OBEN = 12;

	private const UNTEN = 12;

	/**
	 * Zeichnet die Kurve.
	 *
	 * @param array<int, array{zeit: int, wertung: float}> $punkte Nach Zeit sortiert
	 * @param string                                      $titel  Beschriftung für Bildschirmleser
	 * @param int                                         $breite Breite der Zeichenfläche
	 * @param int                                         $hoehe  Höhe der Zeichenfläche
	 *
	 * @return string Das SVG-Element; leer, wenn es keine Punkte gibt
	 */
	public static function svg(array $punkte, string $titel, int $breite = 600, int $hoehe = 240): string
	{
		if (array() === $punkte) {
			return '';
		}

		$punkte = self::ausduennen($punkte, self::MAX_PUNKTE);
		$werte = array_map(static fn (array $punkt): float => (float) $punkt['wertung'], $punkte);
		$unten = floor(min($werte) / 50) * 50;
		$oben = ceil(max($werte) / 50) * 50;

		if ($oben - $unten < 100) {
			$unten -= 50;
			$oben += 50;
		}

		$flaecheBreite = $breite - self::LINKS - self::RECHTS;
		$flaecheHoehe = $hoehe - self::OBEN - self::UNTEN;
		$anzahl = \count($punkte);
		$koordinaten = array();

		foreach ($werte as $index => $wert) {
			$x = self::LINKS + ($anzahl > 1 ? $index * $flaecheBreite / ($anzahl - 1) : $flaecheBreite / 2);
			$y = self::OBEN + ($oben - $wert) / ($oben - $unten) * $flaecheHoehe;
			$koordinaten[] = sprintf('%.1f,%.1f', $x, $y);
		}

		if (1 === $anzahl) {
			[$x, $y] = explode(',', $koordinaten[0]);
			$linie = sprintf('<circle class="kurve" cx="%s" cy="%s" r="3"/>', $x, $y);
		} else {
			$linie = sprintf('<polyline class="kurve" points="%s"/>', implode(' ', $koordinaten));
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %2$d" role="img" aria-label="%3$s">'
			.'<line class="achse" x1="%4$d" y1="%5$d" x2="%4$d" y2="%6$d"/>'
			.'<line class="achse" x1="%4$d" y1="%6$d" x2="%7$d" y2="%6$d"/>'
			.'<text x="%8$d" y="%9$d" text-anchor="end">%10$d</text>'
			.'<text x="%8$d" y="%6$d" text-anchor="end">%11$d</text>'
			.'%12$s</svg>',
			$breite,
			$hoehe,
			htmlspecialchars($titel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
			self::LINKS,
			self::OBEN,
			$hoehe - self::UNTEN,
			$breite - self::RECHTS,
			self::LINKS - 6,
			self::OBEN + 10,
			(int) $oben,
			(int) $unten,
			$linie
		);
	}

	/**
	 * Dünnt eine Punktliste gleichmäßig aus; erster und letzter Punkt bleiben.
	 *
	 * @param array<int, array{zeit: int, wertung: float}> $punkte     Die Punkte
	 * @param int                                         $hoechstens Höchstzahl, mindestens 2
	 *
	 * @return array<int, array{zeit: int, wertung: float}> Höchstens $hoechstens Punkte
	 */
	public static function ausduennen(array $punkte, int $hoechstens): array
	{
		$punkte = array_values($punkte);
		$anzahl = \count($punkte);

		if ($anzahl <= $hoechstens) {
			return $punkte;
		}

		$ergebnis = array();

		for ($index = 0; $index < $hoechstens; ++$index) {
			$ergebnis[] = $punkte[(int) round($index * ($anzahl - 1) / ($hoechstens - 1))];
		}

		return $ergebnis;
	}
}
