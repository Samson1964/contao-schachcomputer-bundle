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
 * Name eines Mitglieds, wie er in Ranglisten öffentlich erscheint.
 */
final class Anzeigename
{
	/**
	 * Bildet „Vorname N." aus Vor- und Nachname.
	 *
	 * Ranglisten veröffentlichen damit keine vollen Namen. Fehlt der Nachname,
	 * bleibt nur der Vorname stehen. Hat ein Mitglied weder Vor- noch
	 * Nachnamen (die Felder sind in Contao nicht zwingend), tritt der
	 * Benutzername an die Stelle; er ist ohnehin der Anmeldename und steht
	 * für das Mitglied in den Ranglisten.
	 *
	 * @param string|null $vorname      Vorname aus tl_member
	 * @param string|null $nachname     Nachname aus tl_member
	 * @param string|null $benutzername Benutzername aus tl_member, Ersatz bei leerem Namen
	 *
	 * @return string Der gekürzte Name, sonst der Benutzername, und nur wenn auch
	 *                der fehlt „–"
	 */
	public static function kurz(?string $vorname, ?string $nachname, ?string $benutzername = null): string
	{
		$vorname = trim((string) $vorname);
		$nachname = trim((string) $nachname);
		$name = trim($vorname.('' !== $nachname ? ' '.mb_substr($nachname, 0, 1).'.' : ''));

		if ('' === $name) {
			$name = trim((string) $benutzername);
		}

		return '' !== $name ? $name : '–';
	}
}
