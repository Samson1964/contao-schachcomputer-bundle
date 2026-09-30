<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Partie;

use Schachbulle\ContaoSchachcomputerBundle\Engine\Stufen;

/**
 * Die Regeln einer Partie als reine Zustandsänderungen, ohne Datenbank.
 *
 * Jede Methode bekommt die Partie und den aktuellen Zeitpunkt in
 * Millisekunden und ändert die Partie an Ort und Stelle. Speichern und
 * Verrechnen übernimmt der Partiedienst. So lassen sich alle Regeln mit
 * festen Zeitpunkten testen.
 *
 * Zeitregeln (siehe Uhr): Die Uhr des Spielers läuft ab seinem zweiten Zug;
 * für den ersten gibt es weder Abzug noch Gutschrift, aber eine Frist von
 * 60 s, danach wird ungewertet abgebrochen. Die Uhr des Computers (seit
 * Fassung 1.1.0) läuft ab dem ersten Computerzug mit Abzug und Gutschrift;
 * ob sie abgelaufen ist, zählt nur beim Eintreffen seines Zuges. Ein
 * Engine-Zug muss außerdem innerhalb von 60 s eintreffen, sonst gilt die
 * Partie als verlassen und ist verloren – vor dem ersten eigenen Zug wird
 * stattdessen abgebrochen.
 *
 * Remisangebot (seit Fassung 1.1.0): Ob Stockfish annimmt, entscheidet der
 * Browser; der Server prüft nur die Rahmenregeln (siehe remisErlaubt()).
 */
final class Ablauf
{
	/**
	 * Höchstzahl an Halbzügen einer Übungspartie, die angenommen wird.
	 */
	public const MAX_UEBUNG_HALBZUEGE = 600;

	/**
	 * Eigene Züge, die der Spieler mindestens gemacht haben muss, bevor er
	 * Remis anbieten darf: Das erste Angebot ist vor seinem 20. Zug möglich.
	 */
	public const REMIS_AB_EIGENEN_ZUEGEN = 19;

	/**
	 * Eigene Züge, die nach einem Remisangebot vergehen müssen, bevor der
	 * Spieler erneut anbieten darf.
	 */
	public const REMIS_ABSTAND = 5;

	/**
	 * Legt eine neue gewertete Partie an.
	 *
	 * Beide Uhren bekommen die Grundbedenkzeit. Hat der Computer Weiß, läuft
	 * seine Uhr ab jetzt (uhrSeit).
	 *
	 * @param int                                                    $memberId   ID aus tl_member, 0 für Gäste
	 * @param string                                                  $gast       Gastkennung, leer bei Mitgliedern
	 * @param array{id: int, minuten: int, inkrement: int, klasse: string} $bedenkzeit Zeile aus tl_schachcomputer_bedenkzeit;
	 *                                                                                  die Werte werden in die Partie kopiert
	 * @param int                                                     $stufe      Spielstufe der Engine
	 * @param string                                                  $farbe      Farbe des Spielers, „w" oder „b"
	 * @param int                                                     $jetztMs    Aktueller Zeitpunkt
	 *
	 * @throws PartieFehler UNGUELTIG bei unbekannter Stufe oder Farbe
	 *
	 * @return Partie Die laufende Partie, noch ohne ID
	 */
	public static function starten(int $memberId, string $gast, array $bedenkzeit, int $stufe, string $farbe, int $jetztMs): Partie
	{
		if (!Stufen::gueltig($stufe) || !\in_array($farbe, array('w', 'b'), true)) {
			throw new PartieFehler(PartieFehler::UNGUELTIG);
		}

		$partie = new Partie();
		$partie->memberId = $memberId;
		$partie->gast = $gast;
		$partie->gewertet = true;
		$partie->bedenkzeit = (int) $bedenkzeit['id'];
		$partie->minuten = (int) $bedenkzeit['minuten'];
		$partie->inkrement = (int) $bedenkzeit['inkrement'];
		$partie->klasse = (string) $bedenkzeit['klasse'];
		$partie->stufe = $stufe;
		$partie->farbe = $farbe;
		$partie->status = Partie::LAEUFT;
		$partie->restzeit = $partie->minuten * 60000;
		$partie->restzeitEngine = $partie->minuten * 60000;
		$partie->uhrSeit = $jetztMs;
		$partie->beginn = intdiv($jetztMs, 1000);

		return $partie;
	}

	/**
	 * Nimmt einen Zug des Spielers oder der Engine an.
	 *
	 * Wer zieht, ergibt sich aus der Zahl der Halbzüge. Kommt der Zug zu
	 * spät, wird er nicht ausgeführt, sondern die Partie wegen Zeit, Verlassen
	 * oder verpasster Frist des ersten Zugs beendet; die Methode kehrt dann
	 * ohne Fehler zurück, der Aufrufer erkennt es am Status.
	 *
	 * Beim Computerzug zählt zuerst die Engine-Frist (verlassen), dann die Uhr
	 * des Computers: Der Server zieht die selbst gemessene Zeit seit uhrSeit
	 * abzüglich bis zu 1 s Ausgleich für die Übertragung ab und schreibt die
	 * Gutschrift gut; war die Uhr schon abgelaufen, verliert der Computer auf
	 * Zeit. Partien ohne Uhr des Computers
	 * (restzeitEngine -1, vor Fassung 1.1.0) bleiben davon unberührt.
	 *
	 * @param Partie   $partie     Die laufende Partie
	 * @param string   $zug        Der Zug in UCI-Schreibweise
	 * @param int      $zugnummer  Zahl der Halbzüge, die der Browser kennt
	 * @param int|null $denkzeitMs Im Browser gemessene Denkzeit bei Spielerzügen
	 * @param int      $jetztMs    Aktueller Zeitpunkt
	 *
	 * @throws PartieFehler BEENDET, wenn die Partie nicht mehr läuft;
	 *                      VERALTET bei abweichender Zugnummer;
	 *                      UNGUELTIG bei einem regelwidrigen Zug
	 */
	public static function zug(Partie $partie, string $zug, int $zugnummer, ?int $denkzeitMs, int $jetztMs): void
	{
		if (Partie::LAEUFT !== $partie->status) {
			throw new PartieFehler(PartieFehler::BEENDET);
		}

		if ($zugnummer !== $partie->zugnummer()) {
			throw new PartieFehler(PartieFehler::VERALTET);
		}

		$schiedsrichter = Schiedsrichter::nachspielen($partie->zuege);
		$spielerZieht = $partie->spielerAmZug();
		$vergangen = $jetztMs - $partie->uhrSeit;
		$abzug = 0;
		$restzeit = $partie->restzeit;
		$restzeitEngine = $partie->restzeitEngine;

		if ($spielerZieht && 0 === $partie->eigeneZuege()) {
			if ($vergangen > Uhr::ERSTER_ZUG_MS + Uhr::AUSGLEICH_MS) {
				self::abbruchSetzen($partie, 'erster_zug', $jetztMs);

				return;
			}
		} elseif ($spielerZieht) {
			$abzug = Uhr::abzug($vergangen, $denkzeitMs);
			$restzeit = Uhr::nachZug($partie->restzeit, $abzug, $partie->inkrement * 1000);

			if (null === $restzeit) {
				self::zeitAbgelaufen($partie, $schiedsrichter, $jetztMs);

				return;
			}
		} elseif ($vergangen > Uhr::ENGINE_FRIST_MS) {
			self::verlassen($partie, $jetztMs);

			return;
		} elseif ($partie->restzeitEngine >= 0) {
			// Abgezogen wird die Servermessung abzüglich bis zu 1 s Ausgleich
			// für die Übertragung, wie beim Spieler. Eine Browsermessung gibt
			// es für den Computer nicht; 0 an ihrer Stelle lässt abzug() den
			// vollen Ausgleich (AUSGLEICH_MS) gewähren, nie unter 0. Die
			// Gutschrift gibt es schon ab dem ersten Computerzug.
			$restzeitEngine = Uhr::nachZug($partie->restzeitEngine, Uhr::abzug($vergangen, 0), $partie->inkrement * 1000);

			if (null === $restzeitEngine) {
				self::engineZeitAbgelaufen($partie, $schiedsrichter, $jetztMs);

				return;
			}
		}

		if (!$schiedsrichter->ziehen($zug)) {
			throw new PartieFehler(PartieFehler::UNGUELTIG);
		}

		$partie->zuege[] = $zug;

		if ($spielerZieht) {
			$partie->zeiten[] = $abzug;
			$partie->restzeit = $restzeit;
		} else {
			$partie->restzeitEngine = $restzeitEngine;
		}

		$partie->uhrSeit = $jetztMs;
		$grund = $schiedsrichter->ende();

		if (null !== $grund) {
			// Bei Matt hat gewonnen, wer den Mattzug gemacht hat
			$punkte = 'matt' === $grund ? ($spielerZieht ? 1.0 : 0.0) : 0.5;
			self::beenden($partie, $grund, $punkte, $jetztMs);
		}
	}

	/**
	 * Prüft die Fristen einer laufenden Partie und beendet sie bei Bedarf.
	 *
	 * Aufgerufen beim Abruf des Stands und minütlich vom Cronjob, damit auch
	 * verlassene Partien in die Wertung eingehen.
	 *
	 * Die Uhr des Computers wird hier absichtlich nicht geprüft: Stockfish
	 * rechnet im Browser des Spielers. Verlöre der Computer schon ohne
	 * eingetroffenen Zug auf Zeit, gewönne, wer nach dem eigenen Zug den Tab
	 * schließt. Ein ausbleibender Computerzug zählt deshalb nur über die
	 * Engine-Frist als verlassen.
	 *
	 * @param Partie $partie  Die Partie
	 * @param int    $jetztMs Aktueller Zeitpunkt
	 *
	 * @return bool true, wenn die Partie dabei beendet oder abgebrochen wurde
	 */
	public static function pruefen(Partie $partie, int $jetztMs): bool
	{
		if (Partie::LAEUFT !== $partie->status) {
			return false;
		}

		$vergangen = $jetztMs - $partie->uhrSeit;

		if ($partie->spielerAmZug()) {
			if (0 === $partie->eigeneZuege()) {
				if ($vergangen > Uhr::ERSTER_ZUG_MS + Uhr::AUSGLEICH_MS) {
					self::abbruchSetzen($partie, 'erster_zug', $jetztMs);

					return true;
				}

				return false;
			}

			if (Uhr::abgelaufen($partie->restzeit, $partie->uhrSeit, $jetztMs)) {
				self::zeitAbgelaufen($partie, Schiedsrichter::nachspielen($partie->zuege), $jetztMs);

				return true;
			}

			return false;
		}

		if ($vergangen > Uhr::ENGINE_FRIST_MS) {
			self::verlassen($partie, $jetztMs);

			return true;
		}

		return false;
	}

	/**
	 * Der Spieler gibt auf; die Partie ist verloren.
	 *
	 * @param Partie $partie  Die Partie
	 * @param int    $jetztMs Aktueller Zeitpunkt
	 *
	 * @throws PartieFehler BEENDET, wenn die Partie nicht mehr läuft
	 */
	public static function aufgeben(Partie $partie, int $jetztMs): void
	{
		if (Partie::LAEUFT !== $partie->status) {
			throw new PartieFehler(PartieFehler::BEENDET);
		}

		self::beenden($partie, 'aufgabe', 0.0, $jetztMs);
	}

	/**
	 * Bricht die Partie ungewertet ab – nur vor dem ersten eigenen Zug.
	 *
	 * @param Partie $partie  Die Partie
	 * @param int    $jetztMs Aktueller Zeitpunkt
	 *
	 * @throws PartieFehler BEENDET, wenn die Partie nicht mehr läuft;
	 *                      NICHT_ERLAUBT, wenn der Spieler schon gezogen hat
	 */
	public static function abbrechen(Partie $partie, int $jetztMs): void
	{
		if (Partie::LAEUFT !== $partie->status) {
			throw new PartieFehler(PartieFehler::BEENDET);
		}

		if ($partie->eigeneZuege() > 0) {
			throw new PartieFehler(PartieFehler::NICHT_ERLAUBT);
		}

		self::abbruchSetzen($partie, 'abbruch', $jetztMs);
	}

	/**
	 * Prüft, ob der Spieler jetzt Remis anbieten darf.
	 *
	 * Erlaubt nur in einer laufenden gewerteten Partie, wenn der Spieler am
	 * Zug ist und schon mindestens REMIS_AB_EIGENEN_ZUEGEN Züge gemacht hat;
	 * nach einem Angebot erst wieder REMIS_ABSTAND eigene Züge später. Ob
	 * seine Uhr inzwischen abgelaufen ist, prüft diese Methode nicht – das
	 * übernimmt pruefen(), bevor remis() die Regeln anwendet.
	 *
	 * @param Partie $partie Die Partie
	 *
	 * @return bool true, wenn ein Angebot jetzt zulässig ist
	 */
	public static function remisErlaubt(Partie $partie): bool
	{
		if (!$partie->gewertet || Partie::LAEUFT !== $partie->status || !$partie->spielerAmZug()) {
			return false;
		}

		// remisAngebot 0 (noch kein Angebot) macht die zweite Grenze wirkungslos
		return $partie->eigeneZuege() >= max(self::REMIS_AB_EIGENEN_ZUEGEN, $partie->remisAngebot + self::REMIS_ABSTAND);
	}

	/**
	 * Nimmt die Entscheidung von Stockfish über ein Remisangebot des Spielers an.
	 *
	 * Stockfish rechnet im Browser; der Server vertraut seiner Entscheidung
	 * wie den Engine-Zügen und prüft nur die Rahmenregeln. Zuerst zählen die
	 * Fristen wie in pruefen(): Ist die Uhr des Spielers samt Ausgleich
	 * abgelaufen – die Prüfung des Angebots kostet ihn Bedenkzeit –, endet die
	 * Partie auf Zeit, und die Methode kehrt ohne Fehler zurück; der Aufrufer
	 * erkennt es am Status. Sonst gilt:
	 *
	 * - angenommen: Die Partie endet remis, Grund „einigung“.
	 * - abgelehnt: Die Partie läuft weiter. uhrSeit und restzeit bleiben
	 *   unverändert, die Uhr des Spielers läuft also ohne Unterbrechung weiter.
	 *
	 * In beiden Fällen merkt sich remisAngebot die Zahl der eigenen Züge.
	 *
	 * @param Partie $partie     Die laufende Partie
	 * @param int    $zugnummer  Zahl der Halbzüge, die der Browser kennt
	 * @param bool   $angenommen Ob Stockfish das Remis annimmt
	 * @param int    $jetztMs    Aktueller Zeitpunkt
	 *
	 * @throws PartieFehler BEENDET, wenn die Partie nicht mehr läuft;
	 *                      VERALTET bei abweichender Zugnummer;
	 *                      NICHT_ERLAUBT, wenn remisErlaubt() das Angebot nicht zulässt
	 */
	public static function remis(Partie $partie, int $zugnummer, bool $angenommen, int $jetztMs): void
	{
		if (Partie::LAEUFT !== $partie->status) {
			throw new PartieFehler(PartieFehler::BEENDET);
		}

		if ($zugnummer !== $partie->zugnummer()) {
			throw new PartieFehler(PartieFehler::VERALTET);
		}

		if (self::pruefen($partie, $jetztMs)) {
			return;
		}

		if (!self::remisErlaubt($partie)) {
			throw new PartieFehler(PartieFehler::NICHT_ERLAUBT);
		}

		$partie->remisAngebot = $partie->eigeneZuege();

		if ($angenommen) {
			self::beenden($partie, 'einigung', 0.5, $jetztMs);
		}
	}

	/**
	 * Baut eine beendete, ungewertete Übungspartie aus der Zugliste des Browsers.
	 *
	 * Die Übungspartie läuft ganz im Browser. Der Server spielt die Züge nach,
	 * um Unsinn abzuweisen, und bestimmt das Ergebnis selbst: Matt oder eine
	 * Remisregel aus der Stellung, sonst Aufgabe, sonst „*" (unbeendet).
	 *
	 * @param int               $memberId   ID aus tl_member
	 * @param int               $stufe      Gespielte Stufe
	 * @param string            $farbe      Farbe des Spielers
	 * @param array<int, mixed> $zuege      Züge in UCI-Schreibweise
	 * @param bool              $aufgegeben Ob der Spieler aufgegeben hat
	 * @param int               $jetztMs    Aktueller Zeitpunkt
	 *
	 * @throws PartieFehler UNGUELTIG bei unbekannter Stufe oder Farbe, leerer
	 *                      oder zu langer Zugliste und regelwidrigen Zügen
	 *
	 * @return Partie Die beendete Übungspartie, noch ohne ID; gilt als
	 *                verrechnet, weil sie nicht gewertet wird
	 */
	public static function uebung(int $memberId, int $stufe, string $farbe, array $zuege, bool $aufgegeben, int $jetztMs): Partie
	{
		if (!Stufen::gueltig($stufe) || !\in_array($farbe, array('w', 'b'), true)) {
			throw new PartieFehler(PartieFehler::UNGUELTIG);
		}

		if (array() === $zuege || \count($zuege) > self::MAX_UEBUNG_HALBZUEGE) {
			throw new PartieFehler(PartieFehler::UNGUELTIG);
		}

		foreach ($zuege as $zug) {
			if (!\is_string($zug)) {
				throw new PartieFehler(PartieFehler::UNGUELTIG);
			}
		}

		try {
			$schiedsrichter = Schiedsrichter::nachspielen($zuege);
		} catch (\InvalidArgumentException $e) {
			throw new PartieFehler(PartieFehler::UNGUELTIG);
		}

		$partie = new Partie();
		$partie->memberId = $memberId;
		$partie->gewertet = false;
		$partie->stufe = $stufe;
		$partie->farbe = $farbe;
		$partie->zuege = $schiedsrichter->uci();
		$partie->status = Partie::BEENDET;
		$partie->verrechnet = true;
		$partie->beginn = intdiv($jetztMs, 1000);
		$partie->ende = $partie->beginn;
		$partie->uhrSeit = $jetztMs;

		$grund = $schiedsrichter->ende();

		if ('matt' === $grund) {
			// Matt ist, wer am Zug ist
			$punkte = $schiedsrichter->amZug() === $farbe ? 0.0 : 1.0;
		} elseif (null !== $grund) {
			$punkte = 0.5;
		} elseif ($aufgegeben) {
			$grund = 'aufgabe';
			$punkte = 0.0;
		} else {
			$grund = 'unbeendet';
			$punkte = null;
		}

		$partie->grund = $grund;
		$partie->ergebnis = null === $punkte ? Partie::OFFEN : $partie->ergebnisFuer($punkte);

		return $partie;
	}

	/**
	 * Punkte des Spielers, wenn seine Zeit abgelaufen ist.
	 *
	 * @param Partie         $partie         Die Partie
	 * @param Schiedsrichter $schiedsrichter Die aktuelle Stellung
	 *
	 * @return float 0, wenn die Engine mattsetzen könnte; sonst 0,5 (Remis)
	 */
	public static function punkteBeiZeitablauf(Partie $partie, Schiedsrichter $schiedsrichter): float
	{
		return $schiedsrichter->mattmaterial($partie->engineFarbe()) ? 0.0 : 0.5;
	}

	/**
	 * Punkte des Spielers, wenn die Zeit des Computers abgelaufen ist.
	 *
	 * Das Gegenstück zu punkteBeiZeitablauf(): Entscheidend ist hier, ob der
	 * Spieler selbst noch mattsetzen könnte.
	 *
	 * @param Partie         $partie         Die Partie
	 * @param Schiedsrichter $schiedsrichter Die aktuelle Stellung (vor dem
	 *                                       verspäteten Computerzug)
	 *
	 * @return float 1, wenn der Spieler mattsetzen könnte; sonst 0,5 (Remis)
	 */
	public static function punkteBeiEngineZeitablauf(Partie $partie, Schiedsrichter $schiedsrichter): float
	{
		return $schiedsrichter->mattmaterial($partie->farbe) ? 1.0 : 0.5;
	}

	/**
	 * Beendet die Partie wegen abgelaufener Zeit des Spielers.
	 *
	 * @param Partie         $partie         Die Partie
	 * @param Schiedsrichter $schiedsrichter Die aktuelle Stellung
	 * @param int            $jetztMs        Aktueller Zeitpunkt
	 */
	private static function zeitAbgelaufen(Partie $partie, Schiedsrichter $schiedsrichter, int $jetztMs): void
	{
		$partie->restzeit = 0;
		self::beenden($partie, 'zeit', self::punkteBeiZeitablauf($partie, $schiedsrichter), $jetztMs);
	}

	/**
	 * Beendet die Partie, weil der Zug des Computers nach Ablauf seiner Uhr
	 * eingetroffen ist.
	 *
	 * Der verspätete Zug wird nicht ausgeführt; die Uhr des Computers steht
	 * danach auf 0, die des Spielers bleibt, wie sie war.
	 *
	 * @param Partie         $partie         Die Partie
	 * @param Schiedsrichter $schiedsrichter Die Stellung vor dem verspäteten Zug
	 * @param int            $jetztMs        Aktueller Zeitpunkt
	 */
	private static function engineZeitAbgelaufen(Partie $partie, Schiedsrichter $schiedsrichter, int $jetztMs): void
	{
		$partie->restzeitEngine = 0;
		self::beenden($partie, 'zeit', self::punkteBeiEngineZeitablauf($partie, $schiedsrichter), $jetztMs);
	}

	/**
	 * Behandelt einen ausgebliebenen Engine-Zug.
	 *
	 * @param Partie $partie  Die Partie
	 * @param int    $jetztMs Aktueller Zeitpunkt
	 */
	private static function verlassen(Partie $partie, int $jetztMs): void
	{
		if (0 === $partie->eigeneZuege()) {
			self::abbruchSetzen($partie, 'verlassen', $jetztMs);

			return;
		}

		self::beenden($partie, 'verlassen', 0.0, $jetztMs);
	}

	/**
	 * Setzt eine Partie auf beendet.
	 *
	 * @param Partie $partie  Die Partie
	 * @param string $grund   Grund des Endes
	 * @param float  $punkte  Punkte des Spielers (1, 0,5 oder 0)
	 * @param int    $jetztMs Aktueller Zeitpunkt
	 */
	private static function beenden(Partie $partie, string $grund, float $punkte, int $jetztMs): void
	{
		$partie->status = Partie::BEENDET;
		$partie->grund = $grund;
		$partie->ergebnis = $partie->ergebnisFuer($punkte);
		$partie->ende = intdiv($jetztMs, 1000);
	}

	/**
	 * Setzt eine Partie auf ungewertet abgebrochen.
	 *
	 * @param Partie $partie  Die Partie
	 * @param string $grund   abbruch, erster_zug oder verlassen
	 * @param int    $jetztMs Aktueller Zeitpunkt
	 */
	private static function abbruchSetzen(Partie $partie, string $grund, int $jetztMs): void
	{
		$partie->status = Partie::ABGEBROCHEN;
		$partie->grund = $grund;
		$partie->ergebnis = Partie::OFFEN;
		$partie->ende = intdiv($jetztMs, 1000);
	}
}
