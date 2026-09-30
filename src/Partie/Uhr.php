<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Partie;

/**
 * Rechenregeln der Schachuhr. Alle Zeiten in Millisekunden.
 *
 * Spieler und Computer haben je eine Uhr (die des Computers seit Fassung
 * 1.1.0). Ob die Uhr des Computers abgelaufen ist, zählt nur beim Eintreffen
 * seines Zuges; bleibt der Zug ganz aus, greift allein die feste Frist
 * ENGINE_FRIST_MS. Sonst gewönne, wer nach dem eigenen Zug den Tab schließt,
 * denn Stockfish rechnet im Browser des Spielers.
 *
 * Der Server misst die Denkzeit des Spielers vom Annehmen des Engine-Zugs
 * bis zum Eintreffen des Spielerzugs. Darin steckt die Übertragungszeit.
 * Deshalb zählt die im Browser gemessene Denkzeit, solange sie höchstens
 * AUSGLEICH_MS unter der Servermessung liegt. Die Zeit des Computers misst
 * der Server allein, vom Speichern des Spielerzugs bis zum Eintreffen des
 * Computerzugs; angerechnet wird sie abzüglich des vollen Ausgleichs
 * (bis zu AUSGLEICH_MS für die Übertragung).
 */
final class Uhr
{
	/**
	 * Höchster Ausgleich für die Übertragungszeit je Zug.
	 */
	public const AUSGLEICH_MS = 1000;

	/**
	 * Frist für den ersten eigenen Zug; danach wird ungewertet abgebrochen.
	 */
	public const ERSTER_ZUG_MS = 60000;

	/**
	 * Frist für einen Engine-Zug; danach gilt die Partie als verlassen.
	 */
	public const ENGINE_FRIST_MS = 60000;

	/**
	 * Bestimmt die Denkzeit, die von der Uhr abgezogen wird.
	 *
	 * @param int      $serverMs  Vom Server gemessene Zeit seit Beginn des Zuges
	 * @param int|null $browserMs Vom Browser gemessene Denkzeit; null oder
	 *                            negativ, wenn der Browser nichts meldet
	 *
	 * @return int Die Browsermessung, aber mindestens Servermessung minus
	 *             AUSGLEICH_MS und höchstens die Servermessung; nie negativ
	 */
	public static function abzug(int $serverMs, ?int $browserMs): int
	{
		$serverMs = max(0, $serverMs);

		if (null === $browserMs || $browserMs < 0) {
			return $serverMs;
		}

		return max(0, min($serverMs, max($browserMs, $serverMs - self::AUSGLEICH_MS)));
	}

	/**
	 * Berechnet die Restzeit nach einem Zug mit Zeitgutschrift.
	 *
	 * @param int $restzeitMs  Restzeit vor dem Zug
	 * @param int $abzugMs     Angerechnete Denkzeit aus abzug()
	 * @param int $inkrementMs Zeitgutschrift je Zug
	 *
	 * @return int|null Die neue Restzeit, oder null, wenn die Zeit schon vor
	 *                  dem Zug abgelaufen war (dann gibt es keine Gutschrift)
	 */
	public static function nachZug(int $restzeitMs, int $abzugMs, int $inkrementMs): ?int
	{
		if ($abzugMs > $restzeitMs) {
			return null;
		}

		return $restzeitMs - $abzugMs + $inkrementMs;
	}

	/**
	 * Berechnet die angezeigte Restzeit einer laufenden Uhr.
	 *
	 * @param int $restzeitMs Restzeit beim Start der Uhr
	 * @param int $seitMs     Zeitpunkt, zu dem die Uhr gestartet wurde
	 * @param int $jetztMs    Aktueller Zeitpunkt
	 *
	 * @return int Die verbleibende Zeit, nie unter 0
	 */
	public static function verbleibend(int $restzeitMs, int $seitMs, int $jetztMs): int
	{
		return max(0, $restzeitMs - max(0, $jetztMs - $seitMs));
	}

	/**
	 * Prüft, ob die Zeit endgültig abgelaufen ist.
	 *
	 * Erst wenn auch der Ausgleich für die Übertragungszeit verbraucht ist:
	 * Ein Zug, der kurz nach Ablauf eintrifft, könnte laut Browsermessung noch
	 * rechtzeitig gewesen sein und würde von abzug() angenommen.
	 *
	 * @param int $restzeitMs Restzeit beim Start der Uhr
	 * @param int $seitMs     Zeitpunkt, zu dem die Uhr gestartet wurde
	 * @param int $jetztMs    Aktueller Zeitpunkt
	 *
	 * @return bool true, wenn kein Zug mehr rechtzeitig sein kann
	 */
	public static function abgelaufen(int $restzeitMs, int $seitMs, int $jetztMs): bool
	{
		return $jetztMs - $seitMs > $restzeitMs + self::AUSGLEICH_MS;
	}
}
