<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Statistik;

/**
 * Zeiträume der Backend-Statistik: Tag, Monat und Jahr.
 *
 * Die Rechenregeln der Statistikseite des Schachaufgaben-Bundles, hier als
 * eigene Klasse ohne Contao, damit sie sich testen lassen. Zeitpunkte liegen
 * immer auf 12 Uhr, damit Zeitumstellungen keinen Tag verschieben. Maßgeblich
 * ist die Zeitzone des Servers.
 */
final class Zeitraum
{
	public const EBENEN = array('tag', 'monat', 'jahr');

	/**
	 * Macht aus einer Angabe JJJJ-MM-TT einen Zeitpunkt.
	 *
	 * @param string $datum Datum aus der Adresse, darf leer oder ungültig sein
	 * @param int    $jetzt Aktueller Zeitpunkt
	 *
	 * @return int 12 Uhr des Tags; heute, wenn die Angabe fehlt, ungültig ist
	 *             oder in der Zukunft liegt
	 */
	public static function zeitpunkt(string $datum, int $jetzt): int
	{
		$heute = (int) mktime(12, 0, 0, (int) date('n', $jetzt), (int) date('j', $jetzt), (int) date('Y', $jetzt));

		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datum, $teile) && checkdate((int) $teile[2], (int) $teile[3], (int) $teile[1])) {
			return min($heute, (int) mktime(12, 0, 0, (int) $teile[2], (int) $teile[3], (int) $teile[1]));
		}

		return $heute;
	}

	/**
	 * Grenzen des Zeitraums als JJJJMMTT und als Zeitstempel.
	 *
	 * @param string $ebene     tag, monat oder jahr
	 * @param int    $zeitpunkt Ein Zeitpunkt im Zeitraum
	 *
	 * @return array{0: int, 1: int, 2: int, 3: int} von, bis (JJJJMMTT, einschließlich),
	 *                                               Beginn (einschließlich) und Ende (ausschließlich) als Zeitstempel
	 */
	public static function grenzen(string $ebene, int $zeitpunkt): array
	{
		$j = (int) date('Y', $zeitpunkt);
		$m = (int) date('n', $zeitpunkt);
		$t = (int) date('j', $zeitpunkt);

		switch ($ebene) {
			case 'tag':
				$tag = (int) date('Ymd', $zeitpunkt);

				return array($tag, $tag, (int) mktime(0, 0, 0, $m, $t, $j), (int) mktime(0, 0, 0, $m, $t + 1, $j));

			case 'jahr':
				return array($j * 10000 + 101, $j * 10000 + 1231, (int) mktime(0, 0, 0, 1, 1, $j), (int) mktime(0, 0, 0, 1, 1, $j + 1));

			default:
				return array($j * 10000 + $m * 100 + 1, $j * 10000 + $m * 100 + 31, (int) mktime(0, 0, 0, $m, 1, $j), (int) mktime(0, 0, 0, $m + 1, 1, $j));
		}
	}

	/**
	 * Die Einheit der Diagramme: eine Ebene feiner als der Zeitraum.
	 *
	 * @param string $ebene tag, monat oder jahr
	 *
	 * @return string stunde, tag oder monat
	 */
	public static function einheit(string $ebene): string
	{
		return array('tag' => 'stunde', 'monat' => 'tag', 'jahr' => 'monat')[$ebene] ?? 'tag';
	}

	/**
	 * Die vollständige Achse eines Diagramms, damit leere Zeitpunkte als Lücke erscheinen.
	 *
	 * @param string             $ebene     tag, monat oder jahr
	 * @param int                $zeitpunkt Ein Zeitpunkt im Zeitraum
	 * @param array<int, string> $monate    Monatsnamen (Index 0 = Januar), etwa $GLOBALS['TL_LANG']['MONTHS']
	 *
	 * @return array<int, string> Stunde, Tag bzw. Monat => Beschriftung
	 */
	public static function achse(string $ebene, int $zeitpunkt, array $monate): array
	{
		$achse = array();

		if ('tag' === $ebene) {
			for ($stunde = 0; $stunde < 24; ++$stunde) {
				$achse[$stunde] = (string) $stunde;
			}
		} elseif ('jahr' === $ebene) {
			for ($monat = 1; $monat <= 12; ++$monat) {
				$achse[$monat] = mb_substr((string) ($monate[$monat - 1] ?? $monat), 0, 3);
			}
		} else {
			$tage = (int) date('t', $zeitpunkt);

			for ($tag = 1; $tag <= $tage; ++$tag) {
				$achse[$tag] = sprintf('%02d.%s', $tag, date('m.', $zeitpunkt));
			}
		}

		return $achse;
	}

	/**
	 * Verschiebt den Zeitpunkt um einen Zeitraum der Ebene.
	 *
	 * @param string $ebene     tag, monat oder jahr
	 * @param int    $zeitpunkt Ausgangspunkt
	 * @param int    $richtung  -1 zurück, 1 vor
	 *
	 * @return int Neuer Zeitpunkt, 12 Uhr; beim Monat der Erste, damit es
	 *             keinen Überlauf gibt (31. Januar plus ein Monat)
	 */
	public static function verschieben(string $ebene, int $zeitpunkt, int $richtung): int
	{
		$j = (int) date('Y', $zeitpunkt);
		$m = (int) date('n', $zeitpunkt);
		$t = (int) date('j', $zeitpunkt);

		switch ($ebene) {
			case 'tag':
				return (int) mktime(12, 0, 0, $m, $t + $richtung, $j);

			case 'jahr':
				return (int) mktime(12, 0, 0, 1, 1, $j + $richtung);

			default:
				return (int) mktime(12, 0, 0, $m + $richtung, 1, $j);
		}
	}

	/**
	 * Lesbare Bezeichnung des Zeitraums, etwa „28.09.2026", „September 2026" oder „2026".
	 *
	 * @param string             $ebene     tag, monat oder jahr
	 * @param int                $zeitpunkt Ein Zeitpunkt im Zeitraum
	 * @param array<int, string> $monate    Monatsnamen (Index 0 = Januar)
	 *
	 * @return string Die Bezeichnung
	 */
	public static function bezeichnung(string $ebene, int $zeitpunkt, array $monate): string
	{
		switch ($ebene) {
			case 'tag':
				return date('d.m.Y', $zeitpunkt);

			case 'jahr':
				return date('Y', $zeitpunkt);

			default:
				return ($monate[(int) date('n', $zeitpunkt) - 1] ?? date('m', $zeitpunkt)).' '.date('Y', $zeitpunkt);
		}
	}
}
