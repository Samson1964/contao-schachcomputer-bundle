<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Backend;

use Contao\CoreBundle\Exception\ResponseException;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partiedienst;
use Schachbulle\ContaoSchachcomputerBundle\Partie\PgnExport;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * PGN-Download einer Partie im Backend (do=schachcomputer_partien&key=pgn&id=…).
 *
 * Contao ruft key-Callbacks aus $GLOBALS['BE_MOD'] in 4.13 und 5.7 gleich auf
 * und holt die Klasse über System::importStatic() aus dem Container; der
 * Dienst ist deshalb öffentlich. Wer das Backend-Modul nicht sehen darf,
 * kommt gar nicht bis hierher.
 */
class PartiePgn
{
	private Partiedienst $partiedienst;

	private PgnExport $export;

	private RequestStack $requestStack;

	/**
	 * Übernimmt die benötigten Dienste.
	 *
	 * @param Partiedienst $partiedienst Lädt die Partie
	 * @param PgnExport    $export       Schreibt die PGN
	 * @param RequestStack $requestStack Liefert ID und Hostnamen
	 */
	public function __construct(Partiedienst $partiedienst, PgnExport $export, RequestStack $requestStack)
	{
		$this->partiedienst = $partiedienst;
		$this->export = $export;
		$this->requestStack = $requestStack;
	}

	/**
	 * Sendet die PGN als Datei; Contao bricht die Seitenausgabe dafür ab.
	 *
	 * @throws ResponseException Mit der Datei als Antwort
	 *
	 * @return string Eine Fehlermeldung, falls es die Partie nicht gibt
	 */
	public function herunterladen(): string
	{
		$request = $this->requestStack->getCurrentRequest();
		$id = null === $request ? 0 : $request->query->getInt('id');
		$pgn = $this->pgn($id, null === $request ? '' : $request->getHost());

		if (null === $pgn) {
			return '<p class="tl_error">'.sprintf($GLOBALS['TL_LANG']['tl_schachcomputer_partie']['nichtGefunden'] ?? 'Partie %d nicht gefunden.', $id).'</p>';
		}

		$antwort = new Response($pgn);
		$antwort->headers->set('Content-Type', 'application/x-chess-pgn; charset=utf-8');
		$antwort->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, sprintf('schachcomputer-%d.pgn', $id)));

		throw new ResponseException($antwort);
	}

	/**
	 * Liefert die PGN einer Partie.
	 *
	 * @param int    $id    ID der Partie
	 * @param string $seite Wert für Site
	 *
	 * @return string|null Die PGN, oder null, wenn es die Partie nicht gibt
	 */
	public function pgn(int $id, string $seite): ?string
	{
		$partie = $id > 0 ? $this->partiedienst->laden($id) : null;

		return null === $partie ? null : $this->export->partie($partie, $seite);
	}
}
