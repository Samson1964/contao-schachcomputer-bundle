<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Backend;

use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Date;

/**
 * Spalten der Backend-Liste „Schachcomputer → Partien".
 *
 * Der Beginn einer Partie liegt als Unix-Zeitstempel in der Datenbank. Contao
 * 5 setzt ihn in der Liste anhand von eval.rgxp = datim selbst in Datum und
 * Uhrzeit um, Contao 4.13 tut das in Listenspalten nur bei den Sortier-Flags
 * für Tag, Monat und Jahr – und die würden die Liste zusätzlich in Gruppen
 * mit Zwischenüberschriften teilen. Deshalb formatiert dieser Callback die
 * Spalte selbst, in beiden Fassungen gleich.
 */
class PartieBeschriftung
{
	/**
	 * Felder der Liste, die als Datum mit Uhrzeit erscheinen.
	 */
	private const ZEITSTEMPEL = array('beginn', 'ende');

	/**
	 * Setzt die Zeitstempel-Spalten eines Datensatzes in Datum und Uhrzeit um
	 * (label_callback bei showColumns).
	 *
	 * Contao übergibt je Feld aus list.label.fields einen Text, in derselben
	 * Reihenfolge; ersetzt werden nur die Spalten aus ZEITSTEMPEL, und zwar aus
	 * dem Rohwert der Zeile. Das Format ist das Datums- und Zeitformat aus den
	 * Contao-Einstellungen („datimFormat").
	 *
	 * @param array<string, mixed> $row   Der Datensatz aus tl_schachcomputer_partie
	 * @param string               $label Die fertige Beschriftung (bei Spalten ungenutzt)
	 * @param DataContainer        $dc    Der Data Container
	 * @param array<int, string>   $args  Die Spalten in der Reihenfolge von list.label.fields
	 *
	 * @return array<int, string> Die Spalten; ein Zeitstempel 0 oder fehlend
	 *                            ergibt „-" wie bei Contao selbst, andere Felder
	 *                            bleiben unverändert
	 */
	#[AsCallback(table: 'tl_schachcomputer_partie', target: 'list.label.label')]
	public function beschriften(array $row, string $label, DataContainer $dc, array $args): array
	{
		$felder = $GLOBALS['TL_DCA']['tl_schachcomputer_partie']['list']['label']['fields'] ?? array();

		foreach ($felder as $index => $feld) {
			if (\in_array($feld, self::ZEITSTEMPEL, true) && array_key_exists($feld, $row)) {
				$zeitpunkt = (int) $row[$feld];
				$args[$index] = $zeitpunkt > 0 ? Date::parse((string) Config::get('datimFormat'), $zeitpunkt) : '-';
			}
		}

		return $args;
	}
}
