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
use Schachbulle\ContaoSchachcomputerBundle\Statistik\Statistik;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Klassen;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsdienst;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Führt Partien in der Datenbank: laden, prüfen, ändern, speichern, verrechnen.
 *
 * Die Regeln selbst stehen in Ablauf. Gespeichert wird bedingt: Das UPDATE
 * greift nur, wenn Zugnummer und Status in der Datenbank noch dem Stand vor
 * der Änderung entsprechen. Kommen zwei Anfragen gleichzeitig (zwei Tabs,
 * Zug und Cronjob), gewinnt die erste; die zweite bekommt VERALTET und der
 * Browser lädt den Stand neu.
 */
class Partiedienst
{
	private Connection $connection;

	private Wertungsdienst $wertungsdienst;

	private Statistik $statistik;

	/**
	 * Übernimmt die benötigten Dienste.
	 *
	 * @param Connection     $connection     Die Datenbankverbindung von Contao
	 * @param Wertungsdienst $wertungsdienst Verrechnet beendete Partien
	 * @param Statistik      $statistik      Zählt Starts, Ergebnisse und Übungen
	 */
	public function __construct(Connection $connection, Wertungsdienst $wertungsdienst, Statistik $statistik)
	{
		$this->connection = $connection;
		$this->wertungsdienst = $wertungsdienst;
		$this->statistik = $statistik;
	}

	/**
	 * Liefert die veröffentlichten Bedenkzeiten für die Auswahl.
	 *
	 * Sortiert nach Klasse (Blitz, Schnell, Lang), dann nach Minuten und
	 * Gutschrift; die Reihenfolge der Klassen wird in PHP hergestellt, weil
	 * FIELD() nur MySQL kennt.
	 *
	 * @return array<int, array{id: int, name: string, minuten: int, inkrement: int, klasse: string}>
	 */
	public function bedenkzeiten(): array
	{
		$zeilen = $this->connection->fetchAllAssociative(
			"SELECT id, name, minuten, inkrement, klasse FROM tl_schachcomputer_bedenkzeit WHERE published='1'"
		);

		$liste = array();

		foreach ($zeilen as $zeile) {
			if (!Klassen::gueltig((string) $zeile['klasse'])) {
				continue;
			}

			$liste[] = array(
				'id'        => (int) $zeile['id'],
				'name'      => (string) $zeile['name'],
				'minuten'   => (int) $zeile['minuten'],
				'inkrement' => (int) $zeile['inkrement'],
				'klasse'    => (string) $zeile['klasse'],
			);
		}

		usort($liste, static function (array $a, array $b): int {
			return array(array_search($a['klasse'], Klassen::ALLE, true), $a['minuten'], $a['inkrement'])
				<=> array(array_search($b['klasse'], Klassen::ALLE, true), $b['minuten'], $b['inkrement']);
		});

		return $liste;
	}

	/**
	 * Lädt eine Partie.
	 *
	 * @param int $id ID aus tl_schachcomputer_partie
	 *
	 * @return Partie|null Die Partie, oder null, wenn es sie nicht gibt
	 */
	public function laden(int $id): ?Partie
	{
		$zeile = $this->connection->fetchAssociative('SELECT * FROM tl_schachcomputer_partie WHERE id=?', array($id));

		return false === $zeile ? null : Partie::ausZeile($zeile);
	}

	/**
	 * Liefert beendete Partien eines Mitglieds, neueste zuerst.
	 *
	 * LIMIT und OFFSET stehen als Zahlen im SQL statt als Parameter: MySQL
	 * lehnt bei emulierten Prepared Statements ein LIMIT '20' in Anführungszeichen ab.
	 *
	 * @param int $memberId ID des Mitglieds
	 * @param int $anzahl   Höchstzahl der Partien
	 * @param int $versatz  Wie viele der neuesten übersprungen werden (Blättern)
	 *
	 * @return array<int, Partie> Die Partien, gewertete und Übungspartien
	 */
	public function eigenePartien(int $memberId, int $anzahl, int $versatz): array
	{
		$zeilen = $this->connection->fetchAllAssociative(
			sprintf("SELECT * FROM tl_schachcomputer_partie WHERE memberId=? AND status='beendet' ORDER BY ende DESC, id DESC LIMIT %d OFFSET %d", max(0, $anzahl), max(0, $versatz)),
			array($memberId)
		);

		return array_map(array(Partie::class, 'ausZeile'), $zeilen);
	}

	/**
	 * Zählt die beendeten Partien eines Mitglieds.
	 *
	 * @param int $memberId ID des Mitglieds
	 *
	 * @return int Die Anzahl
	 */
	public function anzahlEigenePartien(int $memberId): int
	{
		return (int) $this->connection->fetchOne(
			"SELECT COUNT(*) FROM tl_schachcomputer_partie WHERE memberId=? AND status='beendet'",
			array($memberId)
		);
	}

	/**
	 * Liefert die laufende gewertete Partie eines Spielers.
	 *
	 * Vorher werden die Fristen geprüft; eine inzwischen abgelaufene Partie
	 * wird dabei beendet, gespeichert und verrechnet.
	 *
	 * @param Spieler               $spieler Mitglied oder Gast
	 * @param SessionInterface|null $session Sitzung (für Gastwertungen)
	 * @param int                   $jetztMs Aktueller Zeitpunkt
	 *
	 * @return Partie|null Die laufende Partie, oder null, wenn keine läuft
	 */
	public function laufende(Spieler $spieler, ?SessionInterface $session, int $jetztMs): ?Partie
	{
		if ($spieler->istGast()) {
			$zeile = $this->connection->fetchAssociative(
				"SELECT * FROM tl_schachcomputer_partie WHERE memberId=0 AND gast=? AND gewertet=1 AND status='laeuft' ORDER BY id DESC LIMIT 1",
				array($spieler->gastkennung())
			);
		} else {
			$zeile = $this->connection->fetchAssociative(
				"SELECT * FROM tl_schachcomputer_partie WHERE memberId=? AND gewertet=1 AND status='laeuft' ORDER BY id DESC LIMIT 1",
				array($spieler->memberId())
			);
		}

		if (false === $zeile) {
			return null;
		}

		$partie = $this->pruefen(Partie::ausZeile($zeile), $session, $jetztMs);

		return Partie::LAEUFT === $partie->status ? $partie : null;
	}

	/**
	 * Liefert eine bestimmte eigene Partie, auch wenn sie schon beendet ist.
	 *
	 * Gebraucht, wenn im Browser die Uhr abläuft oder ein Zug mit 409
	 * abgewiesen wird: Der Browser braucht dann den Stand dieser Partie,
	 * samt Ergebnis, falls sie inzwischen geendet hat.
	 *
	 * @param Spieler               $spieler  Mitglied oder Gast
	 * @param SessionInterface|null $session  Sitzung (für Gastwertungen)
	 * @param int                   $partieId ID der Partie
	 * @param int                   $jetztMs  Aktueller Zeitpunkt
	 *
	 * @return Partie|null Die geprüfte Partie, oder null, wenn es sie nicht
	 *                     gibt oder sie einem anderen gehört
	 */
	public function eigenePartie(Spieler $spieler, ?SessionInterface $session, int $partieId, int $jetztMs): ?Partie
	{
		$partie = $this->laden($partieId);

		if (null === $partie || !$spieler->besitzt($partie)) {
			return null;
		}

		return $this->pruefen($partie, $session, $jetztMs);
	}

	/**
	 * Startet eine gewertete Partie.
	 *
	 * @param Spieler               $spieler      Mitglied oder Gast
	 * @param SessionInterface|null $session      Sitzung (für Gastwertungen)
	 * @param int                   $bedenkzeitId ID einer veröffentlichten Bedenkzeit
	 * @param int                   $stufe        Spielstufe der Engine
	 * @param string                $farbe        „w", „b" oder „zufall"
	 * @param int                   $jetztMs      Aktueller Zeitpunkt
	 *
	 * @throws PartieFehler LAEUFT_SCHON, wenn der Spieler schon eine Partie hat;
	 *                      NICHT_GEFUNDEN bei unbekannter Bedenkzeit;
	 *                      UNGUELTIG bei unbekannter Stufe oder Farbe
	 *
	 * @return Partie Die gespeicherte Partie mit ID
	 */
	public function starten(Spieler $spieler, ?SessionInterface $session, int $bedenkzeitId, int $stufe, string $farbe, int $jetztMs): Partie
	{
		if (null !== $this->laufende($spieler, $session, $jetztMs)) {
			throw new PartieFehler(PartieFehler::LAEUFT_SCHON);
		}

		$bedenkzeit = $this->connection->fetchAssociative(
			"SELECT id, minuten, inkrement, klasse FROM tl_schachcomputer_bedenkzeit WHERE id=? AND published='1'",
			array($bedenkzeitId)
		);

		if (false === $bedenkzeit || !Klassen::gueltig((string) $bedenkzeit['klasse'])) {
			throw new PartieFehler(PartieFehler::NICHT_GEFUNDEN);
		}

		if ('zufall' === $farbe) {
			$farbe = 0 === random_int(0, 1) ? 'w' : 'b';
		}

		$partie = Ablauf::starten(
			$spieler->memberId() ?? 0,
			$spieler->gastkennung(),
			array(
				'id'        => (int) $bedenkzeit['id'],
				'minuten'   => (int) $bedenkzeit['minuten'],
				'inkrement' => (int) $bedenkzeit['inkrement'],
				'klasse'    => (string) $bedenkzeit['klasse'],
			),
			$stufe,
			$farbe,
			$jetztMs
		);

		$this->einfuegen($partie);
		$this->statistik->zaehlen(Statistik::GESTARTET, $spieler->istGast(), intdiv($jetztMs, 1000));

		return $partie;
	}

	/**
	 * Nimmt einen Zug des Spielers oder der Engine an.
	 *
	 * @param Spieler               $spieler    Mitglied oder Gast
	 * @param SessionInterface|null $session    Sitzung (für Gastwertungen)
	 * @param int                   $partieId   ID der Partie
	 * @param int                   $zugnummer  Zahl der Halbzüge, die der Browser kennt
	 * @param string                $zug        Zug in UCI-Schreibweise
	 * @param int|null              $denkzeitMs Im Browser gemessene Denkzeit
	 * @param int                   $jetztMs    Aktueller Zeitpunkt
	 *
	 * @throws PartieFehler Siehe Ablauf::zug(), dazu NICHT_GEFUNDEN für fremde Partien
	 *
	 * @return Partie Die Partie nach dem Zug (oder beendet, wenn eine Frist ablief)
	 */
	public function ziehen(Spieler $spieler, ?SessionInterface $session, int $partieId, int $zugnummer, string $zug, ?int $denkzeitMs, int $jetztMs): Partie
	{
		return $this->ausfuehren($spieler, $session, $partieId, $jetztMs, static function (Partie $partie) use ($zug, $zugnummer, $denkzeitMs, $jetztMs): void {
			Ablauf::zug($partie, $zug, $zugnummer, $denkzeitMs, $jetztMs);
		});
	}

	/**
	 * Der Spieler gibt auf.
	 *
	 * @param Spieler               $spieler  Mitglied oder Gast
	 * @param SessionInterface|null $session  Sitzung (für Gastwertungen)
	 * @param int                   $partieId ID der Partie
	 * @param int                   $jetztMs  Aktueller Zeitpunkt
	 *
	 * @throws PartieFehler NICHT_GEFUNDEN oder BEENDET
	 *
	 * @return Partie Die beendete Partie
	 */
	public function aufgeben(Spieler $spieler, ?SessionInterface $session, int $partieId, int $jetztMs): Partie
	{
		return $this->ausfuehren($spieler, $session, $partieId, $jetztMs, static function (Partie $partie) use ($jetztMs): void {
			Ablauf::aufgeben($partie, $jetztMs);
		});
	}

	/**
	 * Bricht eine Partie vor dem ersten eigenen Zug ungewertet ab.
	 *
	 * @param Spieler               $spieler  Mitglied oder Gast
	 * @param SessionInterface|null $session  Sitzung (für Gastwertungen)
	 * @param int                   $partieId ID der Partie
	 * @param int                   $jetztMs  Aktueller Zeitpunkt
	 *
	 * @throws PartieFehler NICHT_GEFUNDEN, BEENDET oder NICHT_ERLAUBT
	 *
	 * @return Partie Die abgebrochene Partie
	 */
	public function abbrechen(Spieler $spieler, ?SessionInterface $session, int $partieId, int $jetztMs): Partie
	{
		return $this->ausfuehren($spieler, $session, $partieId, $jetztMs, static function (Partie $partie) use ($jetztMs): void {
			Ablauf::abbrechen($partie, $jetztMs);
		});
	}

	/**
	 * Prüft die Fristen einer Partie und speichert, falls sie dabei endet.
	 *
	 * Ist die Partie inzwischen weitergegangen (das Speichern scheitert an
	 * der Zugnummer), gilt der neuere Stand aus der Datenbank.
	 *
	 * @param Partie                $partie  Die Partie
	 * @param SessionInterface|null $session Sitzung (für Gastwertungen), beim Cronjob null
	 * @param int                   $jetztMs Aktueller Zeitpunkt
	 *
	 * @return Partie Die geprüfte Partie
	 */
	public function pruefen(Partie $partie, ?SessionInterface $session, int $jetztMs): Partie
	{
		$alteZugnummer = $partie->zugnummer();

		if (!Ablauf::pruefen($partie, $jetztMs)) {
			return $partie;
		}

		try {
			$this->speichern($partie, $alteZugnummer);
		} catch (PartieFehler $fehler) {
			return $this->laden($partie->id) ?? $partie;
		}

		$this->endeZaehlen($partie, $jetztMs);
		$this->wertungsdienst->verrechnen($partie, $session);

		return $partie;
	}

	/**
	 * Prüft alle laufenden Partien; für den minütlichen Cronjob.
	 *
	 * @param int $jetztMs Aktueller Zeitpunkt
	 *
	 * @return int Zahl der dabei beendeten oder abgebrochenen Partien
	 */
	public function allePruefen(int $jetztMs): int
	{
		$zeilen = $this->connection->fetchAllAssociative("SELECT * FROM tl_schachcomputer_partie WHERE status='laeuft'");
		$beendet = 0;

		foreach ($zeilen as $zeile) {
			$partie = $this->pruefen(Partie::ausZeile($zeile), null, $jetztMs);
			$beendet += Partie::LAEUFT === $partie->status ? 0 : 1;
		}

		return $beendet;
	}

	/**
	 * Speichert eine Übungspartie eines Mitglieds.
	 *
	 * @param int               $memberId   ID aus tl_member
	 * @param int               $stufe      Gespielte Stufe
	 * @param string            $farbe      Farbe des Spielers
	 * @param array<int, mixed> $zuege      Züge in UCI-Schreibweise
	 * @param bool              $aufgegeben Ob der Spieler aufgegeben hat
	 * @param int               $jetztMs    Aktueller Zeitpunkt
	 *
	 * @throws PartieFehler UNGUELTIG bei unsinnigen Angaben
	 *
	 * @return Partie Die gespeicherte Übungspartie
	 */
	public function uebungSpeichern(int $memberId, int $stufe, string $farbe, array $zuege, bool $aufgegeben, int $jetztMs): Partie
	{
		$partie = Ablauf::uebung($memberId, $stufe, $farbe, $zuege, $aufgegeben, $jetztMs);
		$this->einfuegen($partie);
		$this->statistik->zaehlen(Statistik::UEBUNG, false, intdiv($jetztMs, 1000));

		return $partie;
	}

	/**
	 * Gemeinsamer Ablauf von Zug, Aufgabe und Abbruch.
	 *
	 * Erst werden die Fristen geprüft – ist eine abgelaufen, endet die
	 * Partie deswegen, und die gewünschte Aktion entfällt. Sonst läuft die
	 * Aktion. Danach wird bedingt gespeichert und bei Bedarf verrechnet.
	 *
	 * @param Spieler               $spieler  Mitglied oder Gast
	 * @param SessionInterface|null $session  Sitzung (für Gastwertungen)
	 * @param int                   $partieId ID der Partie
	 * @param int                   $jetztMs  Aktueller Zeitpunkt
	 * @param \Closure              $aktion   Ändert die Partie über Ablauf
	 *
	 * @throws PartieFehler Aus der Aktion, NICHT_GEFUNDEN für fremde Partien,
	 *                      VERALTET, wenn eine andere Anfrage schneller war
	 *
	 * @return Partie Die geänderte Partie
	 */
	private function ausfuehren(Spieler $spieler, ?SessionInterface $session, int $partieId, int $jetztMs, \Closure $aktion): Partie
	{
		$partie = $this->laden($partieId);

		if (null === $partie || !$spieler->besitzt($partie)) {
			throw new PartieFehler(PartieFehler::NICHT_GEFUNDEN);
		}

		$alteZugnummer = $partie->zugnummer();

		if (!Ablauf::pruefen($partie, $jetztMs)) {
			$aktion($partie);
		}

		$this->speichern($partie, $alteZugnummer);
		$this->endeZaehlen($partie, $jetztMs);
		$this->wertungsdienst->verrechnen($partie, $session);

		return $partie;
	}

	/**
	 * Zählt das Ende einer gewerteten Partie für die Statistik.
	 *
	 * Wird nur nach einem erfolgreichen bedingten Speichern aufgerufen; weil
	 * das den Wechsel von „läuft" auf „beendet" genau einmal zulässt, zählt
	 * auch jedes Ende genau einmal.
	 *
	 * @param Partie $partie  Die gespeicherte Partie
	 * @param int    $jetztMs Aktueller Zeitpunkt
	 */
	private function endeZaehlen(Partie $partie, int $jetztMs): void
	{
		if (Partie::LAEUFT === $partie->status) {
			return;
		}

		$punkte = $partie->punkte();

		if (Partie::ABGEBROCHEN === $partie->status || null === $punkte) {
			$art = Statistik::ABGEBROCHEN;
		} elseif (1.0 === $punkte) {
			$art = Statistik::GEWONNEN;
		} elseif (0.5 === $punkte) {
			$art = Statistik::REMIS;
		} else {
			$art = Statistik::VERLOREN;
		}

		$this->statistik->zaehlen($art, 0 === $partie->memberId, intdiv($jetztMs, 1000));
	}

	/**
	 * Schreibt eine laufende Partie zurück, aber nur, wenn sich in der
	 * Datenbank seit dem Laden nichts geändert hat.
	 *
	 * @param Partie $partie        Die geänderte Partie
	 * @param int    $alteZugnummer Zugnummer beim Laden
	 *
	 * @throws PartieFehler VERALTET, wenn eine andere Anfrage schneller war
	 */
	private function speichern(Partie $partie, int $alteZugnummer): void
	{
		$werte = $partie->alsZeile() + array('tstamp' => time());
		$zuweisungen = implode(', ', array_map(static fn (string $spalte): string => $spalte.'=?', array_keys($werte)));

		$betroffen = (int) $this->connection->executeStatement(
			'UPDATE tl_schachcomputer_partie SET '.$zuweisungen.' WHERE id=? AND zugnummer=? AND status=?',
			array_merge(array_values($werte), array($partie->id, $alteZugnummer, Partie::LAEUFT))
		);

		if (1 !== $betroffen) {
			throw new PartieFehler(PartieFehler::VERALTET);
		}
	}

	/**
	 * Legt eine neue Partie an und übernimmt die vergebene ID.
	 *
	 * @param Partie $partie Die Partie ohne ID
	 */
	private function einfuegen(Partie $partie): void
	{
		$this->connection->insert('tl_schachcomputer_partie', $partie->alsZeile() + array('tstamp' => time()));
		$partie->id = (int) $this->connection->lastInsertId();
	}
}
