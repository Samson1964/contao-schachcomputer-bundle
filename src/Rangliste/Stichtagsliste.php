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
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Glicko2;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertung;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsrechner;

/**
 * Friert die Rangliste einer Klasse zum Monatsersten ein.
 *
 * Grundlage ist der Wertungsverlauf, nicht der aktuelle Stand: Je Spieler
 * zählt der letzte Eintrag vor dem Stichtag, die Abweichung wird bis zum
 * Stichtag fortgeschrieben. Dadurch hängt die Liste nicht davon ab, wann der
 * Cronjob läuft – ein später Lauf oder ein Neuaufbau ergibt dieselbe Liste.
 *
 * Für das Fortschreiben dient die Volatilität aus der aktuellen Spielerzeile;
 * der Verlauf speichert sie nicht. Sie ändert sich nur langsam, der Fehler
 * ist vernachlässigbar.
 */
class Stichtagsliste
{
	private Connection $connection;

	private Glicko2 $glicko;

	/**
	 * Übernimmt die benötigten Dienste.
	 *
	 * @param Connection $connection Die Datenbankverbindung von Contao
	 * @param Glicko2    $glicko     Schreibt die Abweichung fort
	 */
	public function __construct(Connection $connection, Glicko2 $glicko)
	{
		$this->connection = $connection;
		$this->glicko = $glicko;
	}

	/**
	 * Berechnet den Stichtag zu einem Zeitpunkt: den Ersten des Monats, 0 Uhr,
	 * in der Zeitzone des Servers (wie überall in Contao).
	 *
	 * @param int $zeitpunkt Unix-Zeitstempel
	 *
	 * @return int Unix-Zeitstempel des Monatsersten, 0 Uhr
	 */
	public static function stichtag(int $zeitpunkt): int
	{
		return (int) mktime(0, 0, 0, (int) date('n', $zeitpunkt), 1, (int) date('Y', $zeitpunkt));
	}

	/**
	 * Liefert die Monatskennung eines Stichtags.
	 *
	 * @param int $stichtag Monatserster aus stichtag()
	 *
	 * @return string JJJJ-MM
	 */
	public static function monat(int $stichtag): string
	{
		return date('Y-m', $stichtag);
	}

	/**
	 * Liefert die Kennung des Vormonats.
	 *
	 * @param string $monat JJJJ-MM
	 *
	 * @return string JJJJ-MM des Monats davor
	 */
	public static function vormonat(string $monat): string
	{
		[$jahr, $zahl] = array_map('intval', explode('-', $monat));

		return date('Y-m', (int) mktime(0, 0, 0, $zahl - 1, 1, $jahr));
	}

	/**
	 * Prüft, ob die Liste eines Monats schon gespeichert ist.
	 *
	 * @param string $klasse Wertungsklasse
	 * @param string $monat  JJJJ-MM
	 *
	 * @return bool true, wenn mindestens eine Zeile existiert
	 */
	public function vorhanden(string $klasse, string $monat): bool
	{
		return false !== $this->connection->fetchOne(
			'SELECT 1 FROM tl_schachcomputer_stichtag WHERE klasse=? AND monat=? LIMIT 1',
			array($klasse, $monat)
		);
	}

	/**
	 * Baut die Liste einer Klasse zum Stichtag und speichert sie.
	 *
	 * Existiert sie schon, geschieht nichts; der eindeutige Schlüssel
	 * (monat, klasse, memberId) schützt zusätzlich vor zwei gleichzeitigen
	 * Läufen. Aufgenommen werden nur nicht gesperrte Mitglieder mit gesicherter
	 * Wertung.
	 *
	 * @param string $klasse   Wertungsklasse
	 * @param int    $stichtag Monatserster aus stichtag()
	 *
	 * @return int Zahl der gespeicherten Zeilen; 0, wenn die Liste schon
	 *             existierte oder niemand die Bedingungen erfüllt
	 */
	public function erstellen(string $klasse, int $stichtag): int
	{
		$monat = self::monat($stichtag);

		if ($this->vorhanden($klasse, $monat)) {
			return 0;
		}

		$eintraege = $this->connection->fetchAllAssociative(
			"SELECT s.memberId, s.volatilitaet, v.wertung, v.abweichung, v.zeit, c.anzahl
			 FROM tl_schachcomputer_verlauf v
			 INNER JOIN (SELECT pid, MAX(id) AS id, COUNT(*) AS anzahl FROM tl_schachcomputer_verlauf WHERE zeit < ? GROUP BY pid) c ON c.id = v.id
			 INNER JOIN tl_schachcomputer_spieler s ON s.id = v.pid
			 INNER JOIN tl_member m ON m.id = s.memberId
			 WHERE s.klasse = ? AND m.disable = ''",
			array($stichtag, $klasse)
		);

		$vormonat = array();

		foreach ($this->connection->fetchAllAssociative(
			'SELECT memberId, platz, wertung FROM tl_schachcomputer_stichtag WHERE klasse=? AND monat=?',
			array($klasse, self::vormonat($monat))
		) as $zeile) {
			$vormonat[(int) $zeile['memberId']] = array('platz' => (int) $zeile['platz'], 'wertung' => (int) $zeile['wertung']);
		}

		$zeilen = $this->berechnen($eintraege, $vormonat, $stichtag);
		$jetzt = time();
		$gespeichert = 0;

		$this->connection->beginTransaction();

		try {
			foreach ($zeilen as $zeile) {
				$gespeichert += (int) $this->connection->insert('tl_schachcomputer_stichtag', array(
					'tstamp'          => $jetzt,
					'monat'           => $monat,
					'klasse'          => $klasse,
					'memberId'        => $zeile['memberId'],
					'platz'           => $zeile['platz'],
					'wertung'         => $zeile['wertung'],
					'partien'         => $zeile['partien'],
					'platzVormonat'   => $zeile['platzVormonat'],
					'wertungVormonat' => $zeile['wertungVormonat'],
				));
			}

			$this->connection->commit();
		} catch (\Throwable $e) {
			$this->connection->rollBack();

			throw $e;
		}

		return $gespeichert;
	}

	/**
	 * Berechnet die Liste aus den Verlaufseinträgen, ohne Datenbank.
	 *
	 * Jeder Eintrag enthält memberId, volatilitaet, wertung, abweichung, zeit
	 * (des letzten Verlaufseintrags vor dem Stichtag) und anzahl (Partien bis
	 * zum Stichtag). Die Rückgabe ist nach Platz sortiert.
	 *
	 * @param array<int, array<string, mixed>>             $eintraege Je Spieler ein Eintrag
	 * @param array<int, array{platz: int, wertung: int}> $vormonat  Liste des Vormonats nach memberId
	 * @param int                                         $stichtag  Monatserster
	 *
	 * @return array<int, array{platz: int, memberId: int, wertung: int, partien: int, platzVormonat: int, wertungVormonat: int}>
	 */
	public function berechnen(array $eintraege, array $vormonat, int $stichtag): array
	{
		$liste = array();

		foreach ($eintraege as $eintrag) {
			$wertung = new Wertung((float) $eintrag['wertung'], (float) $eintrag['abweichung'], (float) $eintrag['volatilitaet']);
			$amStichtag = $this->glicko->ruhen($wertung, intdiv(max(0, $stichtag - (int) $eintrag['zeit']), 86400));

			if (Wertungsrechner::vorlaeufig($amStichtag->getAbweichung())) {
				continue;
			}

			$memberId = (int) $eintrag['memberId'];
			$liste[] = array(
				'memberId'        => $memberId,
				'wertung'         => max(0, (int) round($amStichtag->getWertung())),
				'partien'         => (int) $eintrag['anzahl'],
				'platzVormonat'   => $vormonat[$memberId]['platz'] ?? 0,
				'wertungVormonat' => $vormonat[$memberId]['wertung'] ?? 0,
			);
		}

		usort($liste, static fn (array $a, array $b): int => array($b['wertung'], $b['partien'], $a['memberId']) <=> array($a['wertung'], $a['partien'], $b['memberId']));

		return Platzierung::vergeben($liste, 'wertung');
	}
}
