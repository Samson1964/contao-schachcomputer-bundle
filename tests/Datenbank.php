<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * Eine SQLite-Datenbank im Arbeitsspeicher mit den Tabellen des Bundles.
 *
 * Die Spalten entsprechen den DCA-Dateien (DatenbankSchemaTest prüft das),
 * die Typen sind vereinfacht. Deshalb schreibt das Bundle nur SQL, das
 * MySQL und SQLite gleichermaßen verstehen: kein ON DUPLICATE KEY, kein
 * GREATEST, keine MySQL-Funktionen.
 */
final class Datenbank
{
	private const SCHEMA = array(
		"CREATE TABLE tl_member (id INTEGER PRIMARY KEY AUTOINCREMENT, firstname TEXT NOT NULL DEFAULT '', lastname TEXT NOT NULL DEFAULT '', username TEXT NOT NULL DEFAULT '', disable TEXT NOT NULL DEFAULT '', stop TEXT NOT NULL DEFAULT '')",
		"CREATE TABLE tl_schachcomputer_bedenkzeit (id INTEGER PRIMARY KEY AUTOINCREMENT, tstamp INTEGER NOT NULL DEFAULT 0, name TEXT NOT NULL DEFAULT '', klasse TEXT NOT NULL DEFAULT '', minuten INTEGER NOT NULL DEFAULT 0, inkrement INTEGER NOT NULL DEFAULT 0, published TEXT NOT NULL DEFAULT '')",
		"CREATE TABLE tl_schachcomputer_spieler (id INTEGER PRIMARY KEY AUTOINCREMENT, tstamp INTEGER NOT NULL DEFAULT 0, memberId INTEGER NOT NULL DEFAULT 0, klasse TEXT NOT NULL DEFAULT '', wertung REAL NOT NULL DEFAULT 1500, abweichung REAL NOT NULL DEFAULT 350, volatilitaet REAL NOT NULL DEFAULT 0.06, partien INTEGER NOT NULL DEFAULT 0, siege INTEGER NOT NULL DEFAULT 0, remis INTEGER NOT NULL DEFAULT 0, niederlagen INTEGER NOT NULL DEFAULT 0, hoechstwert REAL NOT NULL DEFAULT 0, hoechstwertDatum INTEGER NOT NULL DEFAULT 0, letztePartie INTEGER NOT NULL DEFAULT 0, UNIQUE (memberId, klasse))",
		'CREATE TABLE tl_schachcomputer_verlauf (id INTEGER PRIMARY KEY AUTOINCREMENT, pid INTEGER NOT NULL DEFAULT 0, tstamp INTEGER NOT NULL DEFAULT 0, partie INTEGER NOT NULL DEFAULT 0, zeit INTEGER NOT NULL DEFAULT 0, wertung REAL NOT NULL DEFAULT 0, abweichung REAL NOT NULL DEFAULT 0)',
		"CREATE TABLE tl_schachcomputer_partie (id INTEGER PRIMARY KEY AUTOINCREMENT, tstamp INTEGER NOT NULL DEFAULT 0, memberId INTEGER NOT NULL DEFAULT 0, gast TEXT NOT NULL DEFAULT '', gewertet INTEGER NOT NULL DEFAULT 0, bedenkzeit INTEGER NOT NULL DEFAULT 0, minuten INTEGER NOT NULL DEFAULT 0, inkrement INTEGER NOT NULL DEFAULT 0, klasse TEXT NOT NULL DEFAULT '', stufe INTEGER NOT NULL DEFAULT 0, farbe TEXT NOT NULL DEFAULT 'w', status TEXT NOT NULL DEFAULT 'laeuft', ergebnis TEXT NOT NULL DEFAULT '', grund TEXT NOT NULL DEFAULT '', zuege TEXT NULL, zeiten TEXT NULL, zugnummer INTEGER NOT NULL DEFAULT 0, restzeit INTEGER NOT NULL DEFAULT 0, restzeitEngine INTEGER NOT NULL DEFAULT -1, uhrSeit INTEGER NOT NULL DEFAULT 0, beginn INTEGER NOT NULL DEFAULT 0, ende INTEGER NOT NULL DEFAULT 0, verrechnet INTEGER NOT NULL DEFAULT 0, wertungVorher REAL NOT NULL DEFAULT 0, wertungNachher REAL NOT NULL DEFAULT 0)",
		"CREATE TABLE tl_schachcomputer_stichtag (id INTEGER PRIMARY KEY AUTOINCREMENT, tstamp INTEGER NOT NULL DEFAULT 0, monat TEXT NOT NULL DEFAULT '', klasse TEXT NOT NULL DEFAULT '', memberId INTEGER NOT NULL DEFAULT 0, platz INTEGER NOT NULL DEFAULT 0, wertung INTEGER NOT NULL DEFAULT 0, partien INTEGER NOT NULL DEFAULT 0, platzVormonat INTEGER NOT NULL DEFAULT 0, wertungVormonat INTEGER NOT NULL DEFAULT 0, UNIQUE (monat, klasse, memberId))",
		"CREATE TABLE tl_schachcomputer_statistik (id INTEGER PRIMARY KEY AUTOINCREMENT, datum INTEGER NOT NULL DEFAULT 0, stunde INTEGER NOT NULL DEFAULT 0, art TEXT NOT NULL DEFAULT '', gast TEXT NOT NULL DEFAULT '', anzahl INTEGER NOT NULL DEFAULT 0, UNIQUE (datum, stunde, art, gast))",
	);

	/**
	 * Legt eine frische Datenbank mit allen Tabellen an.
	 *
	 * @return Connection Die Verbindung; jede Verbindung hat ihre eigene,
	 *                    leere Datenbank
	 */
	public static function verbindung(): Connection
	{
		$verbindung = DriverManager::getConnection(array('driver' => 'pdo_sqlite', 'memory' => true));

		foreach (self::SCHEMA as $anweisung) {
			$verbindung->executeStatement($anweisung);
		}

		return $verbindung;
	}

	/**
	 * Legt ein Mitglied an.
	 *
	 * @param Connection $verbindung Die Testdatenbank
	 * @param string     $vorname    Vorname
	 * @param string     $nachname   Nachname
	 * @param string     $disable    '1' für gesperrt
	 *
	 * @return int Die ID des Mitglieds
	 */
	public static function mitglied(Connection $verbindung, string $vorname, string $nachname, string $disable = ''): int
	{
		$verbindung->insert('tl_member', array(
			'firstname' => $vorname,
			'lastname'  => $nachname,
			'username'  => strtolower($vorname.'.'.$nachname),
			'disable'   => $disable,
		));

		return (int) $verbindung->lastInsertId();
	}

	/**
	 * Legt eine veröffentlichte Bedenkzeit an.
	 *
	 * @param Connection $verbindung Die Testdatenbank
	 * @param string     $klasse     blitz, schnell oder lang
	 * @param int        $minuten    Grundzeit
	 * @param int        $inkrement  Zeitgutschrift in Sekunden
	 * @param string     $published  '1' für veröffentlicht
	 *
	 * @return int Die ID der Bedenkzeit
	 */
	public static function bedenkzeit(Connection $verbindung, string $klasse = 'blitz', int $minuten = 3, int $inkrement = 2, string $published = '1'): int
	{
		$verbindung->insert('tl_schachcomputer_bedenkzeit', array(
			'name'      => $minuten.'+'.$inkrement,
			'klasse'    => $klasse,
			'minuten'   => $minuten,
			'inkrement' => $inkrement,
			'published' => $published,
		));

		return (int) $verbindung->lastInsertId();
	}
}
