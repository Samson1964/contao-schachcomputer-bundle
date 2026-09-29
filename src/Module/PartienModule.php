<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Module;

use Contao\Config;
use Contao\Date;
use Contao\Input;
use Contao\Pagination;
use Contao\System;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partie;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partiedienst;

/**
 * Frontend-Modul „Schachcomputer: Eigene Partien".
 *
 * Listet die beendeten Partien des angemeldeten Mitglieds mit Blättern und
 * Links zum PGN-Download. Gäste sehen einen Hinweis zur Anmeldung.
 */
class PartienModule extends SchachcomputerModul
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_schachcomputer_partien';

	/**
	 * Liest eine Seite der eigenen Partien und bereitet die Zeilen auf.
	 */
	protected function compile(): void
	{
		System::loadLanguageFile('default');
		$GLOBALS['TL_CSS'][] = 'bundles/contaoschachcomputer/listen.css';

		$container = System::getContainer();
		$texte = $GLOBALS['TL_LANG']['MSC']['schachcomputer_partien'] ?? array();
		$this->Template->texte = $texte;
		$this->Template->zeilen = array();
		$this->Template->pagination = '';
		$memberId = $this->memberId();

		if (null === $memberId) {
			$this->Template->hinweis = $texte['nurMitglieder'] ?? '';

			return;
		}

		$this->Template->hinweis = '';
		$dienst = $container->get(Partiedienst::class);
		$router = $container->get('router');
		$proSeite = (int) $this->schachcomputerAnzahl > 0 ? (int) $this->schachcomputerAnzahl : 20;
		$gesamt = $dienst->anzahlEigenePartien($memberId);
		$parameter = 'seite_'.$this->id;
		$seite = max(1, (int) Input::get($parameter));

		if ($gesamt > $proSeite) {
			$this->Template->pagination = (new Pagination($gesamt, $proSeite, Config::get('maxPaginationLinks'), $parameter))->generate("\n  ");
		}

		$this->Template->zeilen = array_map(
			fn (Partie $partie): array => $this->zeile($partie, $texte, $router->generate('schachcomputer_pgn', array('id' => $partie->id))),
			$dienst->eigenePartien($memberId, $proSeite, ($seite - 1) * $proSeite)
		);
		$this->Template->allePgn = $gesamt > 0 ? $router->generate('schachcomputer_pgn_alle') : '';
	}

	/**
	 * Bereitet eine Partie als Tabellenzeile auf.
	 *
	 * @param Partie               $partie Die Partie
	 * @param array<string, mixed> $texte  Texte des Moduls
	 * @param string               $pgn    Adresse des PGN-Downloads
	 *
	 * @return array<string, string> datum, art, farbe, stufe, ergebnis, wertung, pgn
	 */
	private function zeile(Partie $partie, array $texte, string $pgn): array
	{
		$spielen = $GLOBALS['TL_LANG']['MSC']['schachcomputer'] ?? array();
		$punkte = $partie->punkte();
		$ergebnis = null === $punkte ? ($texte['offen'] ?? '') : (1.0 === $punkte ? ($texte['gewonnen'] ?? '') : (0.0 === $punkte ? ($texte['verloren'] ?? '') : ($texte['remis'] ?? '')));
		$grund = $spielen['gruende'][$partie->grund] ?? '';
		$wertung = '–';

		if ($partie->gewertet && $partie->verrechnet) {
			$vorher = (int) round($partie->wertungVorher);
			$nachher = (int) round($partie->wertungNachher);
			$wertung = sprintf('%d → %d (%s%d)', $vorher, $nachher, $nachher >= $vorher ? '+' : '−', abs($nachher - $vorher));
		}

		return array(
			'datum'    => Date::parse(Config::get('datimFormat'), $partie->ende),
			'art'      => $partie->gewertet ? sprintf('%s %d+%d', $spielen['klassen'][$partie->klasse] ?? $partie->klasse, $partie->minuten, $partie->inkrement) : ($texte['uebung'] ?? ''),
			'farbe'    => 'w' === $partie->farbe ? ($texte['weiss'] ?? '') : ($texte['schwarz'] ?? ''),
			'stufe'    => (string) $partie->stufe,
			'ergebnis' => '' === $grund ? $ergebnis : $ergebnis.' – '.$grund,
			'wertung'  => $wertung,
			'pgn'      => $pgn,
		);
	}
}
