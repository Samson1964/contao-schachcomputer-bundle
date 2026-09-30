<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests;

/**
 * Eine feste, regelgerechte Zugfolge für Tests, die eine Partie bis zu einem
 * bestimmten Punkt bringen müssen, ohne sie Zug für Zug zu spielen.
 *
 * Gebraucht wird sie vor allem fürs Remisangebot: Die Regeln verlangen
 * mindestens 19 eigene Züge, nach einer Ablehnung 24. AblaufTest,
 * PartiedienstTest, PartieControllerTest und PgnTest kommen damit dorthin.
 */
final class Musterpartie
{
	/**
	 * 50 regelgerechte Halbzüge (Spanische Partie, Breyer-Verteidigung, dann
	 * ruhige Figurenzüge) ohne Partieende und ohne Stellungswiederholung,
	 * in UCI-Schreibweise, durch Leerzeichen getrennt.
	 */
	public const ZUEGE = 'e2e4 e7e5 g1f3 b8c6 f1b5 a7a6 b5a4 g8f6 e1g1 f8e7 f1e1 b7b5 a4b3 d7d6 c2c3 e8g8 h2h3 c6b8 d2d4 b8d7 b1d2 c8b7 b3c2 f8e8 d2f1 e7f8 f1g3 g7g6 a2a4 c7c5 d4d5 c5c4 c1g5 h7h6 g5e3 d7c5 d1d2 h6h5 e3g5 f8e7 g1h2 g8g7 e1f1 e8f8 a1e1 a8c8 d2e2 d8d7 e2d1 d7c7';

	/**
	 * Liefert die Halbzüge der Zugfolge, auf Wunsch nur den Anfang.
	 *
	 * @param int|null $halbzuege Zahl der Halbzüge ab Anfang (0 bis 50);
	 *                            null liefert alle 50
	 *
	 * @return array<int, string> Die Züge in UCI-Schreibweise; bei mehr
	 *                            Halbzügen als vorhanden alle vorhandenen
	 */
	public static function zuege(?int $halbzuege = null): array
	{
		$zuege = explode(' ', self::ZUEGE);

		return null === $halbzuege ? $zuege : \array_slice($zuege, 0, $halbzuege);
	}
}
