<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Controller;

use Contao\FrontendUser;
use Schachbulle\ContaoSchachcomputerBundle\Engine\Stufen;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partie;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partiedienst;
use Schachbulle\ContaoSchachcomputerBundle\Partie\PartieFehler;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Spieler;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Uhr;
use Schachbulle\ContaoSchachcomputerBundle\Statistik\Statistik;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsdienst;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * JSON-Schnittstelle des Moduls „Spielen".
 *
 * Die Routen stehen in Resources/config/routes.yaml mit _scope: frontend,
 * damit die Firewall des Frontends greift und angemeldete Mitglieder erkannt
 * werden. Antworten werden nie zwischengespeichert.
 *
 * Schutz vor fremden Formularen (CSRF) wie im Schachaufgaben-Bundle:
 * POST-Anfragen werden nur als application/json angenommen. Ein Formular
 * einer fremden Seite kann diesen Typ nicht senden, ein fremdes Skript
 * bräuchte eine CORS-Freigabe, die es nicht gibt. Deshalb steht in den
 * Routen _token_check: false.
 */
class PartieController
{
	/**
	 * Schlüssel der Gastkennung in der Sitzung.
	 */
	public const SITZUNG_GAST = 'schachcomputer_gast';

	private Partiedienst $partiedienst;

	private Wertungsdienst $wertungsdienst;

	private TokenStorageInterface $tokenStorage;

	private Statistik $statistik;

	/**
	 * Übernimmt die benötigten Dienste.
	 *
	 * @param Partiedienst          $partiedienst   Führt die Partien
	 * @param Wertungsdienst        $wertungsdienst Liefert Wertungen, trägt Gastpartien nach
	 * @param TokenStorageInterface $tokenStorage   Liefert das angemeldete Mitglied
	 * @param Statistik             $statistik      Zählt Aufrufe des Moduls
	 */
	public function __construct(Partiedienst $partiedienst, Wertungsdienst $wertungsdienst, TokenStorageInterface $tokenStorage, Statistik $statistik)
	{
		$this->partiedienst = $partiedienst;
		$this->wertungsdienst = $wertungsdienst;
		$this->tokenStorage = $tokenStorage;
		$this->statistik = $statistik;
	}

	/**
	 * Liefert die laufende Partie und die Wertungen des Spielers (GET).
	 *
	 * Mit ?partie=ID kommt genau diese eigene Partie zurück, auch wenn sie
	 * inzwischen beendet ist – so erfährt der Browser nach Ablauf der Uhr das
	 * Ergebnis. Gästen werden Partien nachgetragen, die der Cronjob beendet hat.
	 * Mit ?aufruf=1 (nur beim ersten Laden der Seite) zählt die Anfrage als
	 * Aufruf für die Statistik; gezählt wird hier statt im Modul, weil Contao
	 * die Seite für Gäste aus dem Cache liefern kann.
	 *
	 * @param Request $request Die Anfrage; ihre Sitzung wird bei Gästen gestartet
	 *
	 * @return JsonResponse {partie: {...}|null, wertungen: {...}, gast: bool}
	 */
	public function stand(Request $request): JsonResponse
	{
		$session = $request->getSession();
		$spieler = $this->spieler($session);
		$jetzt = $this->jetztMs();

		if ($request->query->getBoolean('aufruf')) {
			$this->statistik->zaehlen(Statistik::AUFRUF, $spieler->istGast(), intdiv($jetzt, 1000));
		}

		if ($spieler->istGast()) {
			$this->wertungsdienst->gastNachtragen($spieler->gastkennung(), $session);
		}

		$partieId = $request->query->getInt('partie');
		$partie = $partieId > 0
			? $this->partiedienst->eigenePartie($spieler, $session, $partieId, $jetzt)
			: $this->partiedienst->laufende($spieler, $session, $jetzt);

		return $this->antwort(array(
			'partie'    => null === $partie ? null : $this->partieDaten($partie, $jetzt),
			'wertungen' => $this->wertungsdienst->uebersicht($spieler->memberId(), $session, intdiv($jetzt, 1000)),
			'gast'      => $spieler->istGast(),
		));
	}

	/**
	 * Startet eine gewertete Partie (POST, JSON {bedenkzeit, stufe, farbe}).
	 *
	 * @param Request $request Die Anfrage
	 *
	 * @return JsonResponse {partie: {...}}; 415 bei falschem Inhaltstyp, 400
	 *                      bei unvollständigen Daten, sonst Status aus PartieFehler
	 */
	public function start(Request $request): JsonResponse
	{
		$daten = $this->eingabe($request);

		if ($daten instanceof JsonResponse) {
			return $daten;
		}

		if (!\is_int($daten['bedenkzeit'] ?? null) || !\is_int($daten['stufe'] ?? null) || !\in_array($daten['farbe'] ?? null, array('w', 'b', 'zufall'), true)) {
			return $this->antwort(array('fehler' => 'Erwartet werden bedenkzeit (Zahl), stufe (Zahl) und farbe (w, b oder zufall).'), 400);
		}

		return $this->aktion($request, fn (Spieler $spieler, SessionInterface $session, int $jetzt): Partie => $this->partiedienst->starten(
			$spieler,
			$session,
			$daten['bedenkzeit'],
			$daten['stufe'],
			$daten['farbe'],
			$jetzt
		));
	}

	/**
	 * Nimmt einen Zug an (POST, JSON {partie, zugnummer, zug, denkzeit}).
	 *
	 * denkzeit ist die im Browser gemessene Denkzeit des Spielers in ms;
	 * bei Engine-Zügen und ohne Messung null.
	 *
	 * @param Request $request Die Anfrage
	 *
	 * @return JsonResponse {partie: {...}}; 415, 400 oder Status aus PartieFehler
	 */
	public function zug(Request $request): JsonResponse
	{
		$daten = $this->eingabe($request);

		if ($daten instanceof JsonResponse) {
			return $daten;
		}

		$denkzeit = $daten['denkzeit'] ?? null;

		if (!\is_int($daten['partie'] ?? null) || !\is_int($daten['zugnummer'] ?? null) || !\is_string($daten['zug'] ?? null) || (null !== $denkzeit && !\is_int($denkzeit))) {
			return $this->antwort(array('fehler' => 'Erwartet werden partie, zugnummer (Zahlen), zug (Text) und denkzeit (Zahl oder null).'), 400);
		}

		return $this->aktion($request, fn (Spieler $spieler, SessionInterface $session, int $jetzt): Partie => $this->partiedienst->ziehen(
			$spieler,
			$session,
			$daten['partie'],
			$daten['zugnummer'],
			$daten['zug'],
			$denkzeit,
			$jetzt
		));
	}

	/**
	 * Der Spieler gibt auf (POST, JSON {partie}).
	 *
	 * @param Request $request Die Anfrage
	 *
	 * @return JsonResponse {partie: {...}}; 415, 400 oder Status aus PartieFehler
	 */
	public function aufgeben(Request $request): JsonResponse
	{
		$daten = $this->eingabe($request);

		if ($daten instanceof JsonResponse) {
			return $daten;
		}

		if (!\is_int($daten['partie'] ?? null)) {
			return $this->antwort(array('fehler' => 'Erwartet wird partie (Zahl).'), 400);
		}

		return $this->aktion($request, fn (Spieler $spieler, SessionInterface $session, int $jetzt): Partie => $this->partiedienst->aufgeben($spieler, $session, $daten['partie'], $jetzt));
	}

	/**
	 * Bricht vor dem ersten eigenen Zug ab (POST, JSON {partie}).
	 *
	 * @param Request $request Die Anfrage
	 *
	 * @return JsonResponse {partie: {...}}; 415, 400 oder Status aus PartieFehler
	 */
	public function abbrechen(Request $request): JsonResponse
	{
		$daten = $this->eingabe($request);

		if ($daten instanceof JsonResponse) {
			return $daten;
		}

		if (!\is_int($daten['partie'] ?? null)) {
			return $this->antwort(array('fehler' => 'Erwartet wird partie (Zahl).'), 400);
		}

		return $this->aktion($request, fn (Spieler $spieler, SessionInterface $session, int $jetzt): Partie => $this->partiedienst->abbrechen($spieler, $session, $daten['partie'], $jetzt));
	}

	/**
	 * Speichert eine Übungspartie (POST, JSON {stufe, farbe, zuege, aufgegeben}).
	 *
	 * Nur für Mitglieder; Gäste haben kein Partiearchiv.
	 *
	 * @param Request $request Die Anfrage
	 *
	 * @return JsonResponse {partie: {...}}; 403 für Gäste, 415, 400 oder 422
	 */
	public function uebung(Request $request): JsonResponse
	{
		$daten = $this->eingabe($request);

		if ($daten instanceof JsonResponse) {
			return $daten;
		}

		$spieler = $this->spieler($request->getSession());

		if ($spieler->istGast()) {
			return $this->antwort(array('fehler' => 'nur_mitglieder'), 403);
		}

		if (!\is_int($daten['stufe'] ?? null) || !\is_string($daten['farbe'] ?? null) || !\is_array($daten['zuege'] ?? null) || !\is_bool($daten['aufgegeben'] ?? null)) {
			return $this->antwort(array('fehler' => 'Erwartet werden stufe (Zahl), farbe (Text), zuege (Liste) und aufgegeben (true/false).'), 400);
		}

		$jetzt = $this->jetztMs();

		try {
			$partie = $this->partiedienst->uebungSpeichern((int) $spieler->memberId(), $daten['stufe'], $daten['farbe'], array_values($daten['zuege']), $daten['aufgegeben'], $jetzt);
		} catch (PartieFehler $fehler) {
			return $this->antwort(array('fehler' => $fehler->kennung()), $fehler->status());
		}

		return $this->antwort(array('partie' => $this->partieDaten($partie, $jetzt)));
	}

	/**
	 * Aktueller Zeitpunkt in Millisekunden; in Tests überschreibbar.
	 *
	 * @return int Millisekunden seit 1970
	 */
	protected function jetztMs(): int
	{
		return (int) floor(microtime(true) * 1000);
	}

	/**
	 * Führt eine Aktion des Partiedienstes aus und übersetzt Fehler.
	 *
	 * @param Request  $request Die Anfrage
	 * @param \Closure $aktion  Bekommt Spieler, Sitzung und Zeitpunkt, liefert die Partie
	 *
	 * @return JsonResponse {partie: {...}} oder {fehler: Kennung} mit Status aus PartieFehler
	 */
	private function aktion(Request $request, \Closure $aktion): JsonResponse
	{
		$session = $request->getSession();
		$spieler = $this->spieler($session);
		$jetzt = $this->jetztMs();

		try {
			$partie = $aktion($spieler, $session, $jetzt);
		} catch (PartieFehler $fehler) {
			return $this->antwort(array('fehler' => $fehler->kennung()), $fehler->status());
		}

		return $this->antwort(array('partie' => $this->partieDaten($partie, $jetzt)));
	}

	/**
	 * Bereitet eine Partie für den Browser auf.
	 *
	 * restzeit ist bei laufender Uhr schon um die vergangene Zeit gekürzt.
	 * ersterZugFrist nennt die verbleibende Frist für den ersten eigenen Zug.
	 *
	 * @param Partie $partie Die Partie
	 * @param int    $jetzt  Aktueller Zeitpunkt in ms
	 *
	 * @return array<string, mixed> Die Angaben für spielen.js
	 */
	private function partieDaten(Partie $partie, int $jetzt): array
	{
		$laeuft = Partie::LAEUFT === $partie->status;
		$uhrLaeuft = $laeuft && $partie->spielerAmZug() && $partie->eigeneZuege() > 0;
		$ersterZug = $laeuft && $partie->spielerAmZug() && 0 === $partie->eigeneZuege();

		return array(
			'id'             => $partie->id,
			'gewertet'       => $partie->gewertet,
			'farbe'          => $partie->farbe,
			'stufe'          => $partie->stufe,
			'einstellungen'  => Stufen::einstellungen($partie->stufe),
			'klasse'         => $partie->klasse,
			'minuten'        => $partie->minuten,
			'inkrement'      => $partie->inkrement,
			'zuege'          => $partie->zuege,
			'zugnummer'      => $partie->zugnummer(),
			'status'         => $partie->status,
			'ergebnis'       => $partie->ergebnis,
			'grund'          => $partie->grund,
			'punkte'         => $partie->punkte(),
			'spielerAmZug'   => $laeuft && $partie->spielerAmZug(),
			'uhrLaeuft'      => $uhrLaeuft,
			'restzeit'       => $uhrLaeuft ? Uhr::verbleibend($partie->restzeit, $partie->uhrSeit, $jetzt) : $partie->restzeit,
			'ersterZugFrist' => $ersterZug ? max(0, Uhr::ERSTER_ZUG_MS - ($jetzt - $partie->uhrSeit)) : null,
			'verrechnet'     => $partie->verrechnet,
			'wertungVorher'  => (int) round($partie->wertungVorher),
			'wertungNachher' => (int) round($partie->wertungNachher),
		);
	}

	/**
	 * Ermittelt den Spieler: angemeldetes Mitglied oder Gast der Sitzung.
	 *
	 * Ein Gast bekommt beim ersten Aufruf eine zufällige Kennung in der Sitzung.
	 *
	 * @param SessionInterface $session Die Sitzung
	 *
	 * @return Spieler Mitglied oder Gast; ein nur im Backend angemeldeter
	 *                 Benutzer gilt als Gast
	 */
	private function spieler(SessionInterface $session): Spieler
	{
		$token = $this->tokenStorage->getToken();
		$user = null === $token ? null : $token->getUser();

		if ($user instanceof FrontendUser && (int) $user->id > 0) {
			return Spieler::mitglied((int) $user->id);
		}

		$kennung = $session->get(self::SITZUNG_GAST);

		if (!\is_string($kennung) || '' === $kennung) {
			$kennung = bin2hex(random_bytes(16));
			$session->set(self::SITZUNG_GAST, $kennung);
		}

		return Spieler::gast($kennung);
	}

	/**
	 * Liest den JSON-Körper einer POST-Anfrage.
	 *
	 * @param Request $request Die Anfrage
	 *
	 * @return array<string, mixed>|JsonResponse Die Daten, oder eine fertige
	 *                                           Fehlerantwort (415 bzw. 400)
	 */
	private function eingabe(Request $request)
	{
		if (!str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) {
			return $this->antwort(array('fehler' => 'Erwartet wird application/json.'), 415);
		}

		$daten = json_decode((string) $request->getContent(), true);

		if (!\is_array($daten)) {
			return $this->antwort(array('fehler' => 'Ungültiges JSON.'), 400);
		}

		return $daten;
	}

	/**
	 * Baut eine JSON-Antwort, die weder Browser noch Proxy speichern dürfen.
	 *
	 * @param array<string, mixed> $daten  Der Inhalt
	 * @param int                  $status HTTP-Status
	 *
	 * @return JsonResponse Die Antwort
	 */
	private function antwort(array $daten, int $status = 200): JsonResponse
	{
		$antwort = new JsonResponse($daten, $status);
		$antwort->setPrivate();
		$antwort->headers->addCacheControlDirective('no-store');

		return $antwort;
	}
}
