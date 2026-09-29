<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Backend;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

/**
 * Spalten der Backend-Liste „Schachcomputer → Spieler".
 *
 * Wertung, Abweichung und Höchstwert werden ungerundet gespeichert (double),
 * damit sich keine Rundungsfehler über viele Partien ansammeln. In der Liste
 * erscheinen sie ganzzahlig, wie im Frontend.
 */
class SpielerBeschriftung
{
	/**
	 * Felder, deren Spalte ganzzahlig gerundet angezeigt wird.
	 */
	private const GERUNDET = array('wertung', 'abweichung', 'hoechstwert');

	/**
	 * Rundet die Wertungsspalten eines Datensatzes (label_callback bei showColumns).
	 *
	 * Contao übergibt je Feld aus list.label.fields einen Text, in derselben
	 * Reihenfolge; ersetzt werden nur die Spalten aus GERUNDET, und zwar aus
	 * dem Rohwert der Zeile, nicht aus dem schon formatierten Text.
	 *
	 * @param array<string, mixed> $row   Der Datensatz aus tl_schachcomputer_spieler
	 * @param string               $label Die fertige Beschriftung (bei Spalten ungenutzt)
	 * @param DataContainer        $dc    Der Data Container
	 * @param array<int, string>   $args  Die Spalten in der Reihenfolge von list.label.fields
	 *
	 * @return array<int, string> Die Spalten, Wertungen ganzzahlig; unbekannte
	 *                            oder fehlende Felder bleiben unverändert
	 */
	#[AsCallback(table: 'tl_schachcomputer_spieler', target: 'list.label.label')]
	public function beschriften(array $row, string $label, DataContainer $dc, array $args): array
	{
		$felder = $GLOBALS['TL_DCA']['tl_schachcomputer_spieler']['list']['label']['fields'] ?? array();

		foreach ($felder as $index => $feld) {
			if (\in_array($feld, self::GERUNDET, true) && isset($row[$feld]) && is_numeric($row[$feld])) {
				$args[$index] = (string) (int) round((float) $row[$feld]);
			}
		}

		return $args;
	}
}
