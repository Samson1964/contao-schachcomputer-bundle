<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Wertung;

use Doctrine\DBAL\Connection;
use Schachbulle\ContaoSchachcomputerBundle\Engine\Stufen;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partie;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Liest und speichert Wertungen und verrechnet beendete Partien.
 *
 * Mitglieder: tl_schachcomputer_spieler plus eine Verlaufszeile je Partie.
 * Gäste: nur die Sitzung, je Klasse ein Eintrag, kein Verlauf.
 *
 * Jede Partie wird genau einmal verrechnet. Den Ausschlag gibt ein bedingtes
 * UPDATE auf die Spalte verrechnet: Nur die Anfrage, die den Schalter von 0
 * auf 1 umlegt, rechnet. So zählt eine Partie auch dann nur einmal, wenn
 * Zug und Cronjob sie gleichzeitig beenden.
 */
class Wertungsdienst
{
	/**
	 * Schlüssel der Gastwertungen in der Sitzung.
	 */
	public const SITZUNG = 'schachcomputer_wertung';

	private Connection $connection;

	private Wertungsrechner $rechner;

	/**
	 * Übernimmt die benötigten Dienste.
	 *
	 * @param Connection      $connection Die Datenbankverbindung von Contao
	 * @param Wertungsrechner $rechner    Rechnet die neue Wertung aus
	 */
	public function __construct(Connection $connection, Wertungsrechner $rechner)
	{
		$this->connection = $connection;
		$this->rechner = $rechner;
	}

	/**
	 * Liefert den gespeicherten Stand eines Spielers in einer Klasse.
	 *
	 * @param int|null              $memberId ID aus tl_member, null für Gäste
	 * @param SessionInterface|null $session  Sitzung des Gastes; ohne Sitzung
	 *                                        gilt ein Gast als neu
	 * @param string                $klasse   blitz, schnell oder lang
	 *
	 * @return Spielerstand Der Stand, oder der Startstand (1500/350/0,06),
	 *                      wenn noch nichts gespeichert ist
	 */
	public function stand(?int $memberId, ?SessionInterface $session, string $klasse): Spielerstand
	{
		if (null === $memberId) {
			$staende = null === $session ? array() : $session->get(self::SITZUNG, array());

			return \is_array($staende) && isset($staende[$klasse]) && \is_array($staende[$klasse])
				? Spielerstand::ausZeile($staende[$klasse])
				: new Spielerstand();
		}

		$zeile = $this->connection->fetchAssociative(
			'SELECT * FROM tl_schachcomputer_spieler WHERE memberId=? AND klasse=?',
			array($memberId, $klasse)
		);

		return false === $zeile ? new Spielerstand() : Spielerstand::ausZeile($zeile);
	}

	/**
	 * Fasst die Wertungen eines Spielers für den Browser zusammen.
	 *
	 * Die Abweichung wird bis heute fortgeschrieben, damit „vorläufig" auch
	 * nach langer Pause stimmt. Der Vorschlag ist die Stufe, die der Wertung
	 * am nächsten liegt.
	 *
	 * @param int|null              $memberId ID aus tl_member, null für Gäste
	 * @param SessionInterface|null $session  Sitzung des Gastes
	 * @param int                   $jetzt    Aktueller Zeitpunkt in Sekunden
	 *
	 * @return array<string, array{wertung: int, vorlaeufig: bool, partien: int, vorschlag: int}> Je Klasse ein Eintrag
	 */
	public function uebersicht(?int $memberId, ?SessionInterface $session, int $jetzt): array
	{
		$uebersicht = array();

		foreach (Klassen::ALLE as $klasse) {
			$stand = $this->stand($memberId, $session, $klasse);
			$aktuell = $this->rechner->aktuell($stand, $jetzt);

			$uebersicht[$klasse] = array(
				'wertung'    => (int) round($aktuell->getWertung()),
				'vorlaeufig' => Wertungsrechner::vorlaeufig($aktuell->getAbweichung()),
				'partien'    => $stand->partien,
				'vorschlag'  => Stufen::naechste($aktuell->getWertung()),
			);
		}

		return $uebersicht;
	}

	/**
	 * Liefert den Wertungsverlauf eines Mitglieds in einer Klasse.
	 *
	 * @param int    $memberId ID aus tl_member
	 * @param string $klasse   Wertungsklasse
	 *
	 * @return array<int, array{zeit: int, wertung: float, abweichung: float}> Älteste zuerst
	 */
	public function verlauf(int $memberId, string $klasse): array
	{
		$zeilen = $this->connection->fetchAllAssociative(
			'SELECT v.zeit, v.wertung, v.abweichung
			 FROM tl_schachcomputer_verlauf v
			 INNER JOIN tl_schachcomputer_spieler s ON s.id = v.pid
			 WHERE s.memberId = ? AND s.klasse = ?
			 ORDER BY v.zeit, v.id',
			array($memberId, $klasse)
		);

		return array_map(static fn (array $zeile): array => array(
			'zeit'       => (int) $zeile['zeit'],
			'wertung'    => (float) $zeile['wertung'],
			'abweichung' => (float) $zeile['abweichung'],
		), $zeilen);
	}

	/**
	 * Verrechnet eine beendete gewertete Partie – höchstens einmal.
	 *
	 * Seiteneffekte bei Erfolg: Spalte verrechnet sowie wertungVorher und
	 * wertungNachher der Partie; bei Mitgliedern Spielerzeile und
	 * Verlaufszeile, bei Gästen die Sitzung. Das Partie-Objekt wird
	 * entsprechend nachgeführt.
	 *
	 * Die Sitzung eines Gastes wird erst nach dem erfolgreichen Commit
	 * geschrieben: Scheitert etwas davor, rollt die Datenbank zurück
	 * (verrechnet bleibt 0) – eine schon fortgeschriebene Sitzung würde die
	 * Partie beim nächsten Versuch ein zweites Mal zählen.
	 *
	 * @param Partie                $partie  Die gespeicherte Partie (mit ID)
	 * @param SessionInterface|null $session Sitzung des Gastes; ohne sie
	 *                                       bleibt eine Gastpartie liegen und
	 *                                       wird später mit gastNachtragen()
	 *                                       verrechnet
	 *
	 * @return bool true, wenn diese Anfrage verrechnet hat; false bei
	 *              ungewerteten, laufenden, abgebrochenen oder schon
	 *              verrechneten Partien und bei Gästen ohne Sitzung
	 */
	public function verrechnen(Partie $partie, ?SessionInterface $session): bool
	{
		$punkte = $partie->punkte();

		if ($partie->id <= 0 || !$partie->gewertet || Partie::BEENDET !== $partie->status || $partie->verrechnet || null === $punkte) {
			return false;
		}

		$mitglied = $partie->memberId > 0;

		if (!$mitglied && null === $session) {
			return false;
		}

		// Neue Gaststände; in die Sitzung erst nach dem Commit
		$gastStaende = null;

		$this->connection->beginTransaction();

		try {
			$umgelegt = (int) $this->connection->executeStatement(
				'UPDATE tl_schachcomputer_partie SET verrechnet=1 WHERE id=? AND verrechnet=0',
				array($partie->id)
			);

			if (1 !== $umgelegt) {
				$this->connection->commit();

				return false;
			}

			$vorher = $this->stand($mitglied ? $partie->memberId : null, $session, $partie->klasse);
			$nachher = $this->rechner->verrechnen($vorher, $partie->stufe, $punkte, $partie->ende);

			if ($mitglied) {
				$spielerId = $this->speichern($partie->memberId, $partie->klasse, $nachher);
				$this->connection->insert('tl_schachcomputer_verlauf', array(
					'pid'        => $spielerId,
					'tstamp'     => time(),
					'partie'     => $partie->id,
					'zeit'       => $partie->ende,
					'wertung'    => $nachher->wertung->getWertung(),
					'abweichung' => $nachher->wertung->getAbweichung(),
				));
			} else {
				$gastStaende = $session->get(self::SITZUNG, array());
				$gastStaende = \is_array($gastStaende) ? $gastStaende : array();
				$gastStaende[$partie->klasse] = $nachher->alsZeile();
			}

			$this->connection->update(
				'tl_schachcomputer_partie',
				array('wertungVorher' => $vorher->wertung->getWertung(), 'wertungNachher' => $nachher->wertung->getWertung()),
				array('id' => $partie->id)
			);

			$this->connection->commit();
		} catch (\Throwable $e) {
			$this->connection->rollBack();

			throw $e;
		}

		if (null !== $gastStaende) {
			$session->set(self::SITZUNG, $gastStaende);
		}

		$partie->verrechnet = true;
		$partie->wertungVorher = $vorher->wertung->getWertung();
		$partie->wertungNachher = $nachher->wertung->getWertung();

		return true;
	}

	/**
	 * Verrechnet beendete Gastpartien, die der Cronjob ohne Sitzung beendet hat.
	 *
	 * Aufgerufen bei jeder Anfrage eines Gastes, bevor sein Stand gezeigt wird.
	 *
	 * @param string           $gast    Gastkennung aus der Sitzung
	 * @param SessionInterface $session Die Sitzung dieses Gastes
	 *
	 * @return int Zahl der nachgetragenen Partien
	 */
	public function gastNachtragen(string $gast, SessionInterface $session): int
	{
		if ('' === $gast) {
			return 0;
		}

		$zeilen = $this->connection->fetchAllAssociative(
			'SELECT * FROM tl_schachcomputer_partie
			 WHERE memberId=0 AND gast=? AND gewertet=1 AND status=? AND verrechnet=0
			 ORDER BY ende, id',
			array($gast, Partie::BEENDET)
		);

		$anzahl = 0;

		foreach ($zeilen as $zeile) {
			$anzahl += $this->verrechnen(Partie::ausZeile($zeile), $session) ? 1 : 0;
		}

		return $anzahl;
	}

	/**
	 * Schreibt den Stand eines Mitglieds, ohne ON DUPLICATE KEY (SQLite-tauglich).
	 *
	 * @param int          $memberId ID aus tl_member
	 * @param string       $klasse   Wertungsklasse
	 * @param Spielerstand $stand    Der neue Stand
	 *
	 * @return int ID der Zeile in tl_schachcomputer_spieler
	 */
	private function speichern(int $memberId, string $klasse, Spielerstand $stand): int
	{
		$werte = $stand->alsZeile() + array('tstamp' => time());
		$id = $this->connection->fetchOne(
			'SELECT id FROM tl_schachcomputer_spieler WHERE memberId=? AND klasse=?',
			array($memberId, $klasse)
		);

		if (false !== $id) {
			$this->connection->update('tl_schachcomputer_spieler', $werte, array('id' => (int) $id));

			return (int) $id;
		}

		$this->connection->insert('tl_schachcomputer_spieler', $werte + array('memberId' => $memberId, 'klasse' => $klasse));

		return (int) $this->connection->lastInsertId();
	}
}
