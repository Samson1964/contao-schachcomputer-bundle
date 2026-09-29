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
 * Die Wertungsklassen. Jede hat eigene Wertungen und eigene Ranglisten.
 *
 * Die Kennungen stehen in der Datenbank und in Adressen; die Anzeigenamen
 * kommen aus den Sprachdateien (MSC.schachcomputer.klassen).
 */
final class Klassen
{
	public const BLITZ = 'blitz';

	public const SCHNELL = 'schnell';

	public const LANG = 'lang';

	public const ALLE = array(self::BLITZ, self::SCHNELL, self::LANG);

	/**
	 * Prüft eine Klassenkennung.
	 *
	 * @param string $klasse Die zu prüfende Kennung
	 *
	 * @return bool true für blitz, schnell und lang
	 */
	public static function gueltig(string $klasse): bool
	{
		return \in_array($klasse, self::ALLE, true);
	}
}
