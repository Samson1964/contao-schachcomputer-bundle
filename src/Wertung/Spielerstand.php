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
 * Wertung und Zähler eines Spielers in einer Wertungsklasse.
 *
 * Für Mitglieder eine Zeile aus tl_schachcomputer_spieler, für Gäste ein
 * Eintrag in der Sitzung. Unveränderlich: Der Wertungsrechner liefert
 * immer einen neuen Stand.
 */
final class Spielerstand
{
	/**
	 * Legt alle Werte fest.
	 *
	 * @param Wertung $wertung          Glicko-2-Wertung nach der letzten Partie
	 * @param int     $partien          Zahl der gewerteten Partien
	 * @param int     $siege            Davon gewonnen
	 * @param int     $remis            Davon remis
	 * @param int     $niederlagen      Davon verloren
	 * @param float   $hoechstwert      Höchste gesicherte Wertung, 0 solange es keine gab
	 * @param int     $hoechstwertDatum Zeitpunkt des Höchstwerts in Sekunden, 0 ohne
	 * @param int     $letztePartie     Ende der letzten gewerteten Partie in Sekunden, 0 ohne
	 */
	public function __construct(
		public readonly Wertung $wertung = new Wertung(),
		public readonly int $partien = 0,
		public readonly int $siege = 0,
		public readonly int $remis = 0,
		public readonly int $niederlagen = 0,
		public readonly float $hoechstwert = 0.0,
		public readonly int $hoechstwertDatum = 0,
		public readonly int $letztePartie = 0,
	) {
	}

	/**
	 * Baut den Stand aus einer Zeile der Datenbank oder der Sitzung.
	 *
	 * @param array<string, mixed> $zeile Spalten wie in tl_schachcomputer_spieler
	 *
	 * @return self Der Stand
	 */
	public static function ausZeile(array $zeile): self
	{
		return new self(
			new Wertung((float) $zeile['wertung'], (float) $zeile['abweichung'], (float) $zeile['volatilitaet']),
			(int) $zeile['partien'],
			(int) $zeile['siege'],
			(int) $zeile['remis'],
			(int) $zeile['niederlagen'],
			(float) $zeile['hoechstwert'],
			(int) $zeile['hoechstwertDatum'],
			(int) $zeile['letztePartie'],
		);
	}

	/**
	 * Wandelt den Stand in Spaltenwerte für Datenbank oder Sitzung.
	 *
	 * @return array<string, int|float> Spalte => Wert, ohne id, memberId und klasse
	 */
	public function alsZeile(): array
	{
		return array(
			'wertung'          => $this->wertung->getWertung(),
			'abweichung'       => $this->wertung->getAbweichung(),
			'volatilitaet'     => $this->wertung->getVolatilitaet(),
			'partien'          => $this->partien,
			'siege'            => $this->siege,
			'remis'            => $this->remis,
			'niederlagen'      => $this->niederlagen,
			'hoechstwert'      => $this->hoechstwert,
			'hoechstwertDatum' => $this->hoechstwertDatum,
			'letztePartie'     => $this->letztePartie,
		);
	}
}
