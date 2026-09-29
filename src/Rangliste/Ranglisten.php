<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Rangliste;

use Doctrine\DBAL\Connection;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Spielerstand;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsrechner;

/**
 * Liest die drei Ranglisten einer Wertungsklasse.
 *
 * Alle Listen zeigen nur nicht gesperrte Mitglieder, als „Vorname N.". Die
 * Listen kommen vollständig zurück; das Modul kürzt sie auf die gewünschte
 * Länge und sucht darin den eigenen Platz.
 */
class Ranglisten
{
	private Connection $connection;

	private Wertungsrechner $rechner;

	/**
	 * Übernimmt die benötigten Dienste.
	 *
	 * @param Connection      $connection Die Datenbankverbindung von Contao
	 * @param Wertungsrechner $rechner    Schreibt die Abweichung bis heute fort
	 */
	public function __construct(Connection $connection, Wertungsrechner $rechner)
	{
		$this->connection = $connection;
		$this->rechner = $rechner;
	}

	/**
	 * Aktuelle Rangliste: gesicherte Wertungen, Abweichung bis heute fortgeschrieben.
	 *
	 * Das Fortschreiben geschieht in PHP, weil SQLite (Tests) keine
	 * Wurzelfunktion kennt. Die Zahl der Spieler je Klasse ist dafür klein genug.
	 *
	 * @param string $klasse Wertungsklasse
	 * @param int    $jetzt  Aktueller Zeitpunkt in Sekunden
	 *
	 * @return array<int, array{platz: int, memberId: int, username: string, name: string, wertung: int, partien: int}>
	 */
	public function aktuell(string $klasse, int $jetzt): array
	{
		$liste = array();

		foreach ($this->mitglieder('s.partien > 0', array($klasse, (string) $jetzt)) as $zeile) {
			$wertung = $this->rechner->aktuell(Spielerstand::ausZeile($zeile), $jetzt);

			if (Wertungsrechner::vorlaeufig($wertung->getAbweichung())) {
				continue;
			}

			$liste[] = $this->eintrag($zeile, (int) round($wertung->getWertung())) + array('partien' => (int) $zeile['partien']);
		}

		usort($liste, static fn (array $a, array $b): int => array($b['wertung'], $b['partien'], $a['memberId']) <=> array($a['wertung'], $a['partien'], $b['memberId']));

		return Platzierung::vergeben($liste, 'wertung');
	}

	/**
	 * Ewige Bestenliste: höchste je gesicherte Wertung mit Datum.
	 *
	 * @param string $klasse Wertungsklasse
	 * @param int    $jetzt  Aktueller Zeitpunkt in Sekunden (für abgelaufene Mitgliedschaften)
	 *
	 * @return array<int, array{platz: int, memberId: int, username: string, name: string, wertung: int, datum: int}>
	 */
	public function ewig(string $klasse, int $jetzt): array
	{
		$liste = array();

		foreach ($this->mitglieder('s.hoechstwert > 0', array($klasse, (string) $jetzt)) as $zeile) {
			$liste[] = $this->eintrag($zeile, (int) round((float) $zeile['hoechstwert'])) + array('datum' => (int) $zeile['hoechstwertDatum']);
		}

		usort($liste, static fn (array $a, array $b): int => array($b['wertung'], $a['datum'], $a['memberId']) <=> array($a['wertung'], $b['datum'], $b['memberId']));

		return Platzierung::vergeben($liste, 'wertung');
	}

	/**
	 * Gespeicherte Monatsrangliste.
	 *
	 * @param string $klasse Wertungsklasse
	 * @param string $monat  JJJJ-MM
	 *
	 * @return array<int, array{platz: int, memberId: int, username: string, name: string, wertung: int, partien: int, platzVormonat: int, wertungVormonat: int}>
	 */
	public function stichtag(string $klasse, string $monat): array
	{
		$zeilen = $this->connection->fetchAllAssociative(
			"SELECT st.*, m.firstname, m.lastname, m.username
			 FROM tl_schachcomputer_stichtag st
			 INNER JOIN tl_member m ON m.id = st.memberId
			 WHERE st.klasse = ? AND st.monat = ?
			 ORDER BY st.platz, st.memberId",
			array($klasse, $monat)
		);

		return array_map(fn (array $zeile): array => array('platz' => (int) $zeile['platz']) + $this->eintrag($zeile, (int) $zeile['wertung']) + array(
			'partien'         => (int) $zeile['partien'],
			'platzVormonat'   => (int) $zeile['platzVormonat'],
			'wertungVormonat' => (int) $zeile['wertungVormonat'],
		), $zeilen);
	}

	/**
	 * Liefert die Monate, für die es eine gespeicherte Liste gibt.
	 *
	 * @param string $klasse Wertungsklasse
	 *
	 * @return array<int, string> JJJJ-MM, neuester zuerst
	 */
	public function monate(string $klasse): array
	{
		return array_map('strval', $this->connection->fetchFirstColumn(
			'SELECT DISTINCT monat FROM tl_schachcomputer_stichtag WHERE klasse=? ORDER BY monat DESC',
			array($klasse)
		));
	}

	/**
	 * Liest die Spielerzeilen aktiver Mitglieder einer Klasse.
	 *
	 * @param string            $bedingung  Zusätzliche SQL-Bedingung auf s.*
	 * @param array<int, mixed> $parameter  Klasse und Zeitpunkt als Zeichenkette
	 *
	 * @return array<int, array<string, mixed>> Zeilen mit Spieler- und Mitgliedsspalten
	 */
	private function mitglieder(string $bedingung, array $parameter): array
	{
		return $this->connection->fetchAllAssociative(
			"SELECT s.*, m.firstname, m.lastname, m.username
			 FROM tl_schachcomputer_spieler s
			 INNER JOIN tl_member m ON m.id = s.memberId
			 WHERE s.klasse = ? AND m.disable = '' AND (m.stop = '' OR m.stop > ?) AND ".$bedingung,
			$parameter
		);
	}

	/**
	 * Gemeinsame Spalten eines Listeneintrags.
	 *
	 * @param array<string, mixed> $zeile   Zeile mit memberId, username, firstname, lastname
	 * @param int                  $wertung Die anzuzeigende Wertung
	 *
	 * @return array{memberId: int, username: string, name: string, wertung: int}
	 */
	private function eintrag(array $zeile, int $wertung): array
	{
		return array(
			'memberId' => (int) $zeile['memberId'],
			'username' => (string) $zeile['username'],
			'name'     => Anzeigename::kurz($zeile['firstname'], $zeile['lastname']),
			'wertung'  => $wertung,
		);
	}
}
