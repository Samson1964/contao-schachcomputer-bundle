<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Statistik;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use Schachbulle\ContaoSchachcomputerBundle\Rangliste\Anzeigename;

/**
 * Zählt Aufrufe und Partien und wertet sie für die Backend-Statistik aus.
 *
 * Aufbau nach der Statistik des Schachaufgaben-Bundles: gezählt wird
 * stundenweise in tl_schachcomputer_statistik, eine Zeile je Tag, Stunde,
 * Art und Spielergruppe. Anders als dort ohne ON DUPLICATE KEY (erst
 * erhöhen, sonst anlegen), damit die Tests mit SQLite laufen. Die Tabellen
 * „Bedenkzeiten" und „aktivste Mitglieder" kommen aus
 * tl_schachcomputer_partie und erfassen nur Mitglieder, weil Gastpartien
 * nach einem Tag gelöscht werden.
 */
class Statistik
{
	public const AUFRUF = 'aufruf';

	public const GESTARTET = 'gestartet';

	public const GEWONNEN = 'gewonnen';

	public const REMIS = 'remis';

	public const VERLOREN = 'verloren';

	public const ABGEBROCHEN = 'abgebrochen';

	public const UEBUNG = 'uebung';

	public const ARTEN = array(self::AUFRUF, self::GESTARTET, self::GEWONNEN, self::REMIS, self::VERLOREN, self::ABGEBROCHEN, self::UEBUNG);

	/**
	 * Die Arten, die zusammen „beendet" ergeben.
	 */
	public const BEENDET = array(self::GEWONNEN, self::REMIS, self::VERLOREN);

	/**
	 * Punkte des Spielers als SQL-Ausdruck über ergebnis und farbe.
	 */
	private const PUNKTE_SQL = "CASE WHEN p.ergebnis = '1/2-1/2' THEN 0.5 WHEN (p.ergebnis = '1-0' AND p.farbe = 'w') OR (p.ergebnis = '0-1' AND p.farbe = 'b') THEN 1 ELSE 0 END";

	private Connection $connection;

	private LoggerInterface $logger;

	/**
	 * Übernimmt die benötigten Dienste.
	 *
	 * @param Connection      $connection Die Datenbankverbindung von Contao
	 * @param LoggerInterface $logger     Nimmt Fehler beim Zählen auf
	 */
	public function __construct(Connection $connection, LoggerInterface $logger)
	{
		$this->connection = $connection;
		$this->logger = $logger;
	}

	/**
	 * Erhöht den Zähler einer Art in der Stunde des Zeitpunkts um eins.
	 *
	 * Ein Fehler beim Zählen darf das Spiel nicht stören: Er wird als Warnung
	 * protokolliert und nicht weitergereicht. Legen zwei Anfragen die Zeile
	 * einer Stunde gleichzeitig an, scheitert die zweite am eindeutigen
	 * Schlüssel und erhöht stattdessen.
	 *
	 * @param string   $art       Eine der Konstanten aus ARTEN
	 * @param bool     $gast      true für Gäste, false für Mitglieder
	 * @param int|null $zeitpunkt Unix-Zeitstempel; null für jetzt
	 */
	public function zaehlen(string $art, bool $gast, ?int $zeitpunkt = null): void
	{
		$zeitpunkt ??= time();
		$schluessel = array((int) date('Ymd', $zeitpunkt), (int) date('G', $zeitpunkt), $art, $gast ? '1' : '');

		try {
			if ($this->erhoehen($schluessel)) {
				return;
			}

			try {
				$this->connection->insert('tl_schachcomputer_statistik', array(
					'datum'  => $schluessel[0],
					'stunde' => $schluessel[1],
					'art'    => $art,
					'gast'   => $schluessel[3],
					'anzahl' => 1,
				));
			} catch (UniqueConstraintViolationException $e) {
				$this->erhoehen($schluessel);
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Schachcomputer: Statistik nicht gezählt ('.$art.'): '.$e->getMessage(), array('exception' => $e));
		}
	}

	/**
	 * Summiert die Zähler eines Zeitraums nach Art und Spielergruppe.
	 *
	 * @param int $von Erster Tag als JJJJMMTT
	 * @param int $bis Letzter Tag als JJJJMMTT (einschließlich)
	 *
	 * @return array<string, array{mitglieder: int, gaeste: int, gesamt: int}> Je Art aus ARTEN
	 */
	public function summen(int $von, int $bis): array
	{
		$summen = array();

		foreach (self::ARTEN as $art) {
			$summen[$art] = array('mitglieder' => 0, 'gaeste' => 0, 'gesamt' => 0);
		}

		$zeilen = $this->connection->fetchAllAssociative(
			'SELECT art, gast, SUM(anzahl) AS anzahl FROM tl_schachcomputer_statistik WHERE datum BETWEEN ? AND ? GROUP BY art, gast',
			array($von, $bis)
		);

		foreach ($zeilen as $zeile) {
			if (!isset($summen[$zeile['art']])) {
				continue;
			}

			$summen[$zeile['art']]['1' === (string) $zeile['gast'] ? 'gaeste' : 'mitglieder'] += (int) $zeile['anzahl'];
			$summen[$zeile['art']]['gesamt'] += (int) $zeile['anzahl'];
		}

		return $summen;
	}

	/**
	 * Liefert den Verlauf einer oder mehrerer Arten nach Stunden, Tagen oder Monaten.
	 *
	 * Zusammengefasst wird in PHP, nicht in SQL: Die Ganzzahldivision
	 * verhält sich in MySQL und SQLite verschieden.
	 *
	 * @param array<int, string> $arten   Arten, die zusammengezählt werden
	 * @param int                $von     Erster Tag als JJJJMMTT
	 * @param int                $bis     Letzter Tag als JJJJMMTT (einschließlich)
	 * @param string             $einheit „stunde", „tag" oder „monat"
	 *
	 * @return array<int, int> Stunde 0–23, Tag 1–31 bzw. Monat 1–12 => Summe
	 */
	public function verlauf(array $arten, int $von, int $bis, string $einheit): array
	{
		if (array() === $arten) {
			return array();
		}

		$zeilen = $this->connection->fetchAllAssociative(
			'SELECT datum, stunde, SUM(anzahl) AS anzahl FROM tl_schachcomputer_statistik
			 WHERE art IN ('.implode(', ', array_fill(0, \count($arten), '?')).') AND datum BETWEEN ? AND ?
			 GROUP BY datum, stunde',
			array_merge(array_values($arten), array($von, $bis))
		);

		$verlauf = array();

		foreach ($zeilen as $zeile) {
			$datum = (int) $zeile['datum'];

			switch ($einheit) {
				case 'stunde':
					$schluessel = (int) $zeile['stunde'];
					break;

				case 'tag':
					$schluessel = $datum % 100;
					break;

				default:
					$schluessel = intdiv($datum, 100) % 100;
			}

			$verlauf[$schluessel] = ($verlauf[$schluessel] ?? 0) + (int) $zeile['anzahl'];
		}

		return $verlauf;
	}

	/**
	 * Die im Zeitraum meistgespielten Bedenkzeiten gewerteter Partien von Mitgliedern.
	 *
	 * quote ist die Punktquote der Spieler in Prozent, stufe die gerundete
	 * durchschnittliche Engine-Stufe.
	 *
	 * @param int $beginn Unix-Zeitstempel, einschließlich (Ende der Partie)
	 * @param int $ende   Unix-Zeitstempel, ausschließlich
	 * @param int $anzahl Höchstzahl der Zeilen
	 *
	 * @return array<int, array{klasse: string, minuten: int, inkrement: int, partien: int, quote: int, stufe: int}>
	 */
	public function bedenkzeiten(int $beginn, int $ende, int $anzahl): array
	{
		$zeilen = $this->connection->fetchAllAssociative(
			sprintf(
				"SELECT p.klasse, p.minuten, p.inkrement, COUNT(*) AS partien, SUM(%s) AS punkte, AVG(p.stufe) AS stufe
				 FROM tl_schachcomputer_partie p
				 WHERE p.gewertet = 1 AND p.status = 'beendet' AND p.memberId > 0 AND p.ende >= ? AND p.ende < ?
				 GROUP BY p.klasse, p.minuten, p.inkrement
				 ORDER BY partien DESC, p.minuten, p.inkrement
				 LIMIT %d",
				self::PUNKTE_SQL,
				max(1, $anzahl)
			),
			array($beginn, $ende)
		);

		return array_map(static fn (array $zeile): array => array(
			'klasse'    => (string) $zeile['klasse'],
			'minuten'   => (int) $zeile['minuten'],
			'inkrement' => (int) $zeile['inkrement'],
			'partien'   => (int) $zeile['partien'],
			'quote'     => (int) round(100 * (float) $zeile['punkte'] / max(1, (int) $zeile['partien'])),
			'stufe'     => (int) round((float) $zeile['stufe']),
		), $zeilen);
	}

	/**
	 * Die im Zeitraum aktivsten Mitglieder nach gewerteten Partien.
	 *
	 * wertung ist die beste aktuelle Wertung über alle Klassen, null ohne
	 * Spielerzeile.
	 *
	 * @param int $beginn Unix-Zeitstempel, einschließlich (Ende der Partie)
	 * @param int $ende   Unix-Zeitstempel, ausschließlich
	 * @param int $anzahl Höchstzahl der Zeilen
	 *
	 * @return array<int, array{name: string, partien: int, punkte: float, wertung: int|null}>
	 */
	public function aktivsteMitglieder(int $beginn, int $ende, int $anzahl): array
	{
		$zeilen = $this->connection->fetchAllAssociative(
			sprintf(
				"SELECT p.memberId, m.firstname, m.lastname, COUNT(*) AS partien, SUM(%s) AS punkte,
				 (SELECT MAX(s.wertung) FROM tl_schachcomputer_spieler s WHERE s.memberId = p.memberId) AS wertung
				 FROM tl_schachcomputer_partie p
				 INNER JOIN tl_member m ON m.id = p.memberId
				 WHERE p.gewertet = 1 AND p.status = 'beendet' AND p.ende >= ? AND p.ende < ?
				 GROUP BY p.memberId, m.firstname, m.lastname
				 ORDER BY partien DESC, p.memberId
				 LIMIT %d",
				self::PUNKTE_SQL,
				max(1, $anzahl)
			),
			array($beginn, $ende)
		);

		return array_map(static fn (array $zeile): array => array(
			'name'    => Anzeigename::kurz($zeile['firstname'], $zeile['lastname']),
			'partien' => (int) $zeile['partien'],
			'punkte'  => (float) $zeile['punkte'],
			'wertung' => null === $zeile['wertung'] ? null : (int) round((float) $zeile['wertung']),
		), $zeilen);
	}

	/**
	 * Zahlen, die nicht vom Zeitraum abhängen, und neue Spieler im Zeitraum.
	 *
	 * Neu ist ein Mitglied, dessen erste Partie (gewertet oder Übung) im
	 * Zeitraum begann. Die Grenzen werden ausdrücklich als Ganzzahl gebunden:
	 * SQLite vergleicht das MIN() der abgeleiteten Tabelle sonst mit einem
	 * Text, und eine Zahl gilt dort immer als kleiner als ein Text.
	 *
	 * @param int $beginn Unix-Zeitstempel, einschließlich
	 * @param int $ende   Unix-Zeitstempel, ausschließlich
	 *
	 * @return array{bedenkzeiten: int, spieler: int, neueSpieler: int}
	 */
	public function bestand(int $beginn, int $ende): array
	{
		return array(
			'bedenkzeiten' => (int) $this->connection->fetchOne("SELECT COUNT(*) FROM tl_schachcomputer_bedenkzeit WHERE published = '1'"),
			'spieler'      => (int) $this->connection->fetchOne('SELECT COUNT(DISTINCT memberId) FROM tl_schachcomputer_spieler WHERE partien > 0'),
			'neueSpieler'  => (int) $this->connection->fetchOne(
				'SELECT COUNT(*) FROM (SELECT memberId, MIN(beginn) AS erste FROM tl_schachcomputer_partie WHERE memberId > 0 GROUP BY memberId) e
				 WHERE e.erste >= ? AND e.erste < ?',
				array($beginn, $ende),
				array(ParameterType::INTEGER, ParameterType::INTEGER)
			),
		);
	}

	/**
	 * Erhöht einen vorhandenen Zähler.
	 *
	 * @param array{0: int, 1: int, 2: string, 3: string} $schluessel datum, stunde, art, gast
	 *
	 * @return bool true, wenn es die Zeile gab und sie erhöht wurde
	 */
	private function erhoehen(array $schluessel): bool
	{
		return 1 === (int) $this->connection->executeStatement(
			'UPDATE tl_schachcomputer_statistik SET anzahl = anzahl + 1 WHERE datum=? AND stunde=? AND art=? AND gast=?',
			$schluessel
		);
	}
}
