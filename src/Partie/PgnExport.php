<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Partie;

use Doctrine\DBAL\Connection;

/**
 * Liefert PGN-Dateien für das Frontend (eigene Partien) und das Backend.
 *
 * Im PGN steht der volle Name „Nachname, Vorname": Die Datei bekommt nur das
 * Mitglied selbst bzw. ein Redakteur im Backend, anders als die öffentlichen
 * Ranglisten mit „Vorname N.".
 */
class PgnExport
{
	private Connection $connection;

	private Partiedienst $partiedienst;

	/**
	 * Übernimmt die benötigten Dienste.
	 *
	 * @param Connection   $connection   Die Datenbankverbindung von Contao
	 * @param Partiedienst $partiedienst Lädt die Partien
	 */
	public function __construct(Connection $connection, Partiedienst $partiedienst)
	{
		$this->connection = $connection;
		$this->partiedienst = $partiedienst;
	}

	/**
	 * PGN einer einzelnen Partie.
	 *
	 * @param Partie $partie Die Partie
	 * @param string $seite  Wert für Site
	 *
	 * @return string Die PGN
	 */
	public function partie(Partie $partie, string $seite): string
	{
		return Pgn::erzeugen($partie, $this->name($partie->memberId), $seite);
	}

	/**
	 * Alle beendeten Partien eines Mitglieds in einer Datei, älteste zuerst.
	 *
	 * @param int    $memberId ID des Mitglieds
	 * @param string $seite    Wert für Site
	 *
	 * @return string Die PGN-Datei; leer, wenn es keine Partien gibt
	 */
	public function mitglied(int $memberId, string $seite): string
	{
		$name = $this->name($memberId);
		$partien = array_reverse($this->partiedienst->eigenePartien($memberId, PHP_INT_MAX, 0));

		return implode("\n", array_map(static fn (Partie $partie): string => Pgn::erzeugen($partie, $name, $seite), $partien));
	}

	/**
	 * Name des Spielers für die PGN.
	 *
	 * @param int $memberId ID des Mitglieds, 0 für Gäste
	 *
	 * @return string „Nachname, Vorname", oder „Gast"
	 */
	private function name(int $memberId): string
	{
		$zeile = $memberId > 0
			? $this->connection->fetchAssociative('SELECT firstname, lastname FROM tl_member WHERE id=?', array($memberId))
			: false;

		if (false === $zeile) {
			return 'Gast';
		}

		$name = trim(trim((string) $zeile['lastname']).', '.trim((string) $zeile['firstname']), ', ');

		return '' === $name ? 'Gast' : $name;
	}
}
