<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Rangliste;

/**
 * Vergibt Plätze in einer absteigend sortierten Liste.
 *
 * Gleiche Werte teilen sich den Platz, der nächste Platz wird übersprungen
 * (1, 1, 3), wie bei Sportranglisten üblich.
 */
final class Platzierung
{
	/**
	 * Ergänzt jede Zeile um „platz".
	 *
	 * @param array<int, array<string, mixed>> $zeilen Absteigend nach $feld sortiert
	 * @param string                           $feld   Spalte mit dem (gerundeten) Wert
	 *
	 * @return array<int, array<string, mixed>> Die Zeilen mit platz, fortlaufend indiziert
	 */
	public static function vergeben(array $zeilen, string $feld): array
	{
		$ergebnis = array();
		$vorher = null;
		$platz = 0;

		foreach (array_values($zeilen) as $index => $zeile) {
			if ($zeile[$feld] !== $vorher) {
				$platz = $index + 1;
				$vorher = $zeile[$feld];
			}

			$ergebnis[] = array('platz' => $platz) + $zeile;
		}

		return $ergebnis;
	}

	/**
	 * Beschreibt die Veränderung gegenüber dem Vormonat.
	 *
	 * @param int    $platz           Platz in diesem Monat
	 * @param int    $wertung         Wertung in diesem Monat
	 * @param int    $platzVormonat   Platz im Vormonat, 0 wenn nicht in der Liste
	 * @param int    $wertungVormonat Wertung im Vormonat
	 * @param string $neu             Text für Neueinsteiger, etwa „neu"
	 *
	 * @return string Etwa „+2 / +15", „±0 / −8" oder der Text für Neueinsteiger;
	 *                ein Aufstieg in der Liste zählt als Plus
	 */
	public static function veraenderung(int $platz, int $wertung, int $platzVormonat, int $wertungVormonat, string $neu): string
	{
		if ($platzVormonat <= 0) {
			return $neu;
		}

		return self::vorzeichen($platzVormonat - $platz).' / '.self::vorzeichen($wertung - $wertungVormonat);
	}

	/**
	 * Schreibt eine Zahl mit Vorzeichen; 0 als „±0", Minus als echtes Minuszeichen.
	 *
	 * @param int $zahl Die Zahl
	 *
	 * @return string Etwa „+3", „−2" oder „±0"
	 */
	private static function vorzeichen(int $zahl): string
	{
		if (0 === $zahl) {
			return '±0';
		}

		return ($zahl > 0 ? '+' : '−').abs($zahl);
	}
}
