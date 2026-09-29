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
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partie;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partiedienst;
use Schachbulle\ContaoSchachcomputerBundle\Partie\PgnExport;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * PGN-Download der eigenen Partien im Frontend.
 *
 * Nur für angemeldete Mitglieder und nur ihre eigenen beendeten Partien.
 * Die Partie-ID wird selbst aus den Routenattributen gelesen, weil
 * Routenplatzhalter immer als Zeichenkette ankommen.
 */
class PgnController
{
	private Partiedienst $partiedienst;

	private PgnExport $export;

	private TokenStorageInterface $tokenStorage;

	/**
	 * Übernimmt die benötigten Dienste.
	 *
	 * @param Partiedienst          $partiedienst Lädt die Partie
	 * @param PgnExport             $export       Schreibt die PGN
	 * @param TokenStorageInterface $tokenStorage Liefert das angemeldete Mitglied
	 */
	public function __construct(Partiedienst $partiedienst, PgnExport $export, TokenStorageInterface $tokenStorage)
	{
		$this->partiedienst = $partiedienst;
		$this->export = $export;
		$this->tokenStorage = $tokenStorage;
	}

	/**
	 * Eine eigene Partie als PGN-Datei (GET /_schachcomputer/pgn/{id}).
	 *
	 * @param Request $request Die Anfrage mit dem Attribut id
	 *
	 * @return Response Die Datei; 403 für Gäste, 404 für fremde, laufende
	 *                  oder unbekannte Partien
	 */
	public function einzeln(Request $request): Response
	{
		$memberId = $this->memberId();

		if (null === $memberId) {
			return $this->leer(403);
		}

		$partie = $this->partiedienst->laden((int) $request->attributes->get('id'));

		if (null === $partie || $partie->memberId !== $memberId || Partie::BEENDET !== $partie->status) {
			return $this->leer(404);
		}

		return $this->datei($this->export->partie($partie, $request->getHost()), sprintf('schachcomputer-%d.pgn', $partie->id));
	}

	/**
	 * Alle eigenen beendeten Partien in einer PGN-Datei (GET /_schachcomputer/pgn).
	 *
	 * @param Request $request Die Anfrage
	 *
	 * @return Response Die Datei; 403 für Gäste
	 */
	public function alle(Request $request): Response
	{
		$memberId = $this->memberId();

		if (null === $memberId) {
			return $this->leer(403);
		}

		return $this->datei($this->export->mitglied($memberId, $request->getHost()), 'schachcomputer-partien.pgn');
	}

	/**
	 * Ermittelt das angemeldete Mitglied.
	 *
	 * @return int|null Die ID aus tl_member, oder null für Gäste
	 */
	private function memberId(): ?int
	{
		$token = $this->tokenStorage->getToken();
		$user = null === $token ? null : $token->getUser();

		return $user instanceof FrontendUser && (int) $user->id > 0 ? (int) $user->id : null;
	}

	/**
	 * Baut eine PGN-Datei zum Herunterladen, die nicht zwischengespeichert wird.
	 *
	 * @param string $inhalt    Die PGN
	 * @param string $dateiname Vorgeschlagener Dateiname
	 *
	 * @return Response Die Antwort
	 */
	private function datei(string $inhalt, string $dateiname): Response
	{
		$antwort = new Response($inhalt);
		$antwort->headers->set('Content-Type', 'application/x-chess-pgn; charset=utf-8');
		$antwort->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $dateiname));
		$antwort->setPrivate();
		$antwort->headers->addCacheControlDirective('no-store');

		return $antwort;
	}

	/**
	 * Baut eine leere Fehlerantwort.
	 *
	 * @param int $status HTTP-Status
	 *
	 * @return Response Die Antwort
	 */
	private function leer(int $status): Response
	{
		$antwort = new Response('', $status);
		$antwort->setPrivate();

		return $antwort;
	}
}
