<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Engine;

/**
 * Die Spielstufen der Engine und ihre Stockfish-Einstellungen.
 *
 * Diese Klasse ist die einzige Quelle für die Stufen: Der Server prüft damit
 * die gewählte Stufe und schickt die Einstellungen beim Partiestart an den
 * Browser, der sie an Stockfish weitergibt.
 *
 * Ab 1400 regelt Stockfish die Stärke selbst über UCI_Elo; Stockfish hat
 * diese Werte an der CCRL-Blitzliste (120 s + 1 s) geeicht, Bereich 1320 bis
 * 3190. Schwächer als 1320 spielt Stockfish nicht. Die Stufen 600 bis 1300
 * sind deshalb nachgebaut: Skill Level 0, begrenzte Suchtiefe und ein Anteil
 * zufälliger legaler Züge, den der Browser auswürfelt. Ihre Zahlen sind
 * Schätzwerte.
 */
final class Stufen
{
	public const MIN = 600;

	public const MAX = 2500;

	public const SCHRITT = 100;

	/**
	 * Ab dieser Stufe gilt UCI_Elo. 1320 selbst ist kein Hunderter, die
	 * erste Stufe mit Stockfish-Eichung ist deshalb 1400.
	 */
	public const UCI_ELO_AB = 1400;

	/**
	 * Abweichung, mit der eine Stufe als Gegner in Glicko-2 eingeht. Klein,
	 * weil die Stärke der Stufe fest ist; nicht 0, weil sie nur geschätzt ist.
	 */
	public const GEGNER_ABWEICHUNG = 50.0;

	/**
	 * Rechenzeit je Zug in Millisekunden, in allen Wertungsklassen gleich,
	 * damit eine Stufe im Blitz so stark ist wie in der Langpartie.
	 */
	public const ZEIT_MIN_MS = 1000;

	public const ZEIT_MAX_MS = 2000;

	/**
	 * Suchtiefe und Anteil zufälliger Züge der nachgebauten Stufen.
	 */
	private const SCHWACH = array(
		600  => array(1, 0.40),
		700  => array(1, 0.30),
		800  => array(2, 0.25),
		900  => array(2, 0.20),
		1000 => array(3, 0.15),
		1100 => array(3, 0.10),
		1200 => array(4, 0.07),
		1300 => array(5, 0.05),
	);

	/**
	 * Liefert alle Stufen aufsteigend.
	 *
	 * @return array<int, int> 600, 700, … 2500
	 */
	public static function alle(): array
	{
		return range(self::MIN, self::MAX, self::SCHRITT);
	}

	/**
	 * Prüft, ob eine Zahl eine der angebotenen Stufen ist.
	 *
	 * @param int $stufe Die zu prüfende Zahl, etwa aus einer Anfrage des Browsers
	 *
	 * @return bool true für 600, 700, … 2500, sonst false
	 */
	public static function gueltig(int $stufe): bool
	{
		return $stufe >= self::MIN && $stufe <= self::MAX && 0 === ($stufe - self::MIN) % self::SCHRITT;
	}

	/**
	 * Schlägt die Stufe vor, die einer Wertung am nächsten liegt.
	 *
	 * Gerundet wird kaufmännisch auf volle Hundert (1550 → 1600); Wertungen
	 * außerhalb des Bereichs landen auf der schwächsten bzw. stärksten Stufe.
	 *
	 * @param float $wertung Die Wertung des Spielers in der gewählten Klasse
	 *
	 * @return int Eine gültige Stufe
	 */
	public static function naechste(float $wertung): int
	{
		$stufe = (int) (round($wertung / self::SCHRITT) * self::SCHRITT);

		return max(self::MIN, min(self::MAX, $stufe));
	}

	/**
	 * Liefert die Engine-Einstellungen einer Stufe für den Browser.
	 *
	 * uciElo ist bei den nachgebauten Stufen null, skill und tiefe sind es bei
	 * den geeichten. zufall ist der Anteil der Züge, die der Browser statt des
	 * Engine-Zugs zufällig wählt.
	 *
	 * @param int $stufe Eine gültige Stufe
	 *
	 * @throws \InvalidArgumentException Bei einer Zahl, die keine Stufe ist
	 *
	 * @return array{stufe: int, uciElo: int|null, skill: int|null, tiefe: int|null, zufall: float, zeitMin: int, zeitMax: int}
	 */
	public static function einstellungen(int $stufe): array
	{
		if (!self::gueltig($stufe)) {
			throw new \InvalidArgumentException(sprintf('%d ist keine Spielstufe.', $stufe));
		}

		if ($stufe >= self::UCI_ELO_AB) {
			return array(
				'stufe'   => $stufe,
				'uciElo'  => $stufe,
				'skill'   => null,
				'tiefe'   => null,
				'zufall'  => 0.0,
				'zeitMin' => self::ZEIT_MIN_MS,
				'zeitMax' => self::ZEIT_MAX_MS,
			);
		}

		[$tiefe, $zufall] = self::SCHWACH[$stufe];

		return array(
			'stufe'   => $stufe,
			'uciElo'  => null,
			'skill'   => 0,
			'tiefe'   => $tiefe,
			'zufall'  => $zufall,
			'zeitMin' => self::ZEIT_MIN_MS,
			'zeitMax' => self::ZEIT_MAX_MS,
		);
	}
}
