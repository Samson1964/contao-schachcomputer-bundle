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
 * Schreibt eine Partie als PGN.
 *
 * Die Züge werden mit dem Schiedsrichter nachgespielt, um die Kurzschreibweise
 * (SAN) zu erhalten. Kopf nach dem „Seven Tag Roster", ergänzt um
 * TimeControl und Termination. Zeilen werden nach PGN-Norm bei 80 Zeichen
 * umbrochen.
 */
final class Pgn
{
	private const ZEILENLAENGE = 80;

	/**
	 * Übersetzt den Grund des Endes in den PGN-Wert von Termination.
	 */
	private const TERMINATION = array(
		'zeit'       => 'time forfeit',
		'verlassen'  => 'abandoned',
		'abbruch'    => 'unterminated',
		'erster_zug' => 'unterminated',
		'unbeendet'  => 'unterminated',
	);

	/**
	 * Erzeugt die PGN einer Partie.
	 *
	 * @param Partie $partie      Die Partie
	 * @param string $spielerName Name des Spielers, etwa „Mustermann, Max"
	 * @param string $seite       Wert für Site, etwa der Hostname
	 *
	 * @return string Die PGN mit abschließendem Zeilenumbruch
	 */
	public static function erzeugen(Partie $partie, string $spielerName, string $seite): string
	{
		$engine = sprintf('Stockfish 19 (Stufe %d)', $partie->stufe);
		$ergebnis = '' === $partie->ergebnis ? Partie::OFFEN : $partie->ergebnis;

		$kopf = array(
			'Event'       => $partie->gewertet ? 'Partie gegen Stockfish' : 'Übungspartie gegen Stockfish',
			'Site'        => $seite,
			'Date'        => $partie->beginn > 0 ? date('Y.m.d', $partie->beginn) : '????.??.??',
			'Round'       => '-',
			'White'       => 'w' === $partie->farbe ? $spielerName : $engine,
			'Black'       => 'w' === $partie->farbe ? $engine : $spielerName,
			'Result'      => $ergebnis,
			'TimeControl' => $partie->gewertet ? ($partie->minuten * 60).'+'.$partie->inkrement : '-',
			'Termination' => self::TERMINATION[$partie->grund] ?? 'normal',
		);

		$zeilen = array();

		foreach ($kopf as $name => $wert) {
			$zeilen[] = sprintf('[%s "%s"]', $name, str_replace(array('\\', '"'), array('\\\\', '\\"'), $wert));
		}

		return implode("\n", $zeilen)."\n\n".self::zugtext(Schiedsrichter::nachspielen($partie->zuege)->san(), $ergebnis)."\n";
	}

	/**
	 * Setzt die Züge mit Zugnummern zusammen und bricht bei 80 Zeichen um.
	 *
	 * @param array<int, string> $san      Züge in Kurzschreibweise
	 * @param string             $ergebnis Ergebnis am Ende des Zugtexts
	 *
	 * @return string Der Zugtext
	 */
	private static function zugtext(array $san, string $ergebnis): string
	{
		$teile = array();

		foreach ($san as $index => $zug) {
			$teile[] = 0 === $index % 2 ? (intdiv($index, 2) + 1).'. '.$zug : $zug;
		}

		$teile[] = $ergebnis;
		$zeilen = array();
		$zeile = '';

		foreach ($teile as $teil) {
			if ('' !== $zeile && \strlen($zeile) + 1 + \strlen($teil) > self::ZEILENLAENGE) {
				$zeilen[] = $zeile;
				$zeile = $teil;
			} else {
				$zeile = '' === $zeile ? $teil : $zeile.' '.$teil;
			}
		}

		$zeilen[] = $zeile;

		return implode("\n", $zeilen);
	}
}
