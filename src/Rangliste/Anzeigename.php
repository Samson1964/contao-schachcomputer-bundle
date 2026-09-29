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
	 * bleibt nur der Vorname stehen.
	 *
	 * @param string|null $vorname  Vorname aus tl_member
	 * @param string|null $nachname Nachname aus tl_member
	 *
	 * @return string Der gekürzte Name, oder „–" wenn beides leer ist
	 */
	public static function kurz(?string $vorname, ?string $nachname): string
	{
		$vorname = trim((string) $vorname);
		$nachname = trim((string) $nachname);
		$name = trim($vorname.('' !== $nachname ? ' '.mb_substr($nachname, 0, 1).'.' : ''));

		return '' !== $name ? $name : '–';
	}
}
