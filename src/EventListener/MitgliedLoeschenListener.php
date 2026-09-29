<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * Löscht Partien, Wertungen, Verlauf und Monatsplätze eines gelöschten Mitglieds.
 *
 * Mitglieder verschwinden auf zwei Wegen: im Backend (ondelete_callback von
 * tl_member) und über das Frontend-Modul „Konto schließen" (Hook
 * closeAccount). Wie im Schachaufgaben-Bundle; die Ranglisten zeigen
 * verwaiste Zeilen ohnehin nicht (INNER JOIN auf tl_member).
 */
class MitgliedLoeschenListener
{
	private Connection $connection;

	/**
	 * Übernimmt die Datenbankverbindung.
	 *
	 * @param Connection $connection Die Datenbankverbindung von Contao
	 */
	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
	}

	/**
	 * Räumt beim Löschen eines Mitglieds im Backend auf.
	 *
	 * Contao ruft den Callback vor dem eigentlichen Löschen auf, auch beim
	 * Löschen mehrerer Mitglieder auf einmal (einmal je Mitglied).
	 *
	 * @param DataContainer $dc Der Data Container mit der ID des Mitglieds
	 */
	#[AsCallback(table: 'tl_member', target: 'config.ondelete')]
	public function imBackend(DataContainer $dc): void
	{
		if ($dc->id) {
			$this->loeschen((int) $dc->id);
		}
	}

	/**
	 * Räumt auf, wenn ein Mitglied sein Konto im Frontend schließt.
	 *
	 * Nur beim Löschen, nicht beim Deaktivieren: Ein deaktiviertes Mitglied
	 * kann wieder freigeschaltet werden und behält dann seine Wertungen.
	 *
	 * @param int    $memberId ID des Mitglieds
	 * @param string $modus    „close_delete" oder „close_deactivate"
	 */
	#[AsHook('closeAccount')]
	public function imFrontend(int $memberId, string $modus): void
	{
		if ('close_delete' === $modus) {
			$this->loeschen($memberId);
		}
	}

	/**
	 * Löscht alle Zeilen eines Mitglieds aus den Tabellen des Bundles.
	 *
	 * Der Verlauf hängt an der Spielerzeile (pid) und muss deshalb vor ihr fallen.
	 *
	 * @param int $memberId ID des Mitglieds aus tl_member
	 */
	public function loeschen(int $memberId): void
	{
		$this->connection->executeStatement(
			'DELETE FROM tl_schachcomputer_verlauf WHERE pid IN (SELECT id FROM tl_schachcomputer_spieler WHERE memberId=?)',
			array($memberId)
		);
		$this->connection->executeStatement('DELETE FROM tl_schachcomputer_spieler WHERE memberId=?', array($memberId));
		$this->connection->executeStatement('DELETE FROM tl_schachcomputer_partie WHERE memberId=?', array($memberId));
		$this->connection->executeStatement('DELETE FROM tl_schachcomputer_stichtag WHERE memberId=?', array($memberId));
	}
}
