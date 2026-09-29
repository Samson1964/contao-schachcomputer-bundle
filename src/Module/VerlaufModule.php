<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Module;

use Contao\System;
use Schachbulle\ContaoSchachcomputerBundle\Verlauf\Kurve;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Klassen;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsdienst;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsrechner;

/**
 * Frontend-Modul „Schachcomputer: Wertungsverlauf".
 *
 * Zeigt je Wertungsklasse, in der das angemeldete Mitglied gespielt hat,
 * die Wertung als Kurve (Inline-SVG, siehe Kurve). Gäste sehen einen
 * Hinweis zur Anmeldung.
 */
class VerlaufModule extends SchachcomputerModul
{
	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_schachcomputer_verlauf';

	/**
	 * Baut je Klasse eine Kurve mit Beschriftung.
	 */
	protected function compile(): void
	{
		System::loadLanguageFile('default');
		$GLOBALS['TL_CSS'][] = 'bundles/contaoschachcomputer/listen.css';

		$texte = $GLOBALS['TL_LANG']['MSC']['schachcomputer_verlauf'] ?? array();
		$klassenNamen = $GLOBALS['TL_LANG']['MSC']['schachcomputer']['klassen'] ?? array();
		$this->Template->texte = $texte;
		$this->Template->kurven = array();
		$memberId = $this->memberId();

		if (null === $memberId) {
			$this->Template->hinweis = $texte['nurMitglieder'] ?? '';

			return;
		}

		$this->Template->hinweis = '';
		$dienst = System::getContainer()->get(Wertungsdienst::class);
		$kurven = array();

		foreach (Klassen::ALLE as $klasse) {
			$verlauf = $dienst->verlauf($memberId, $klasse);

			if (array() === $verlauf) {
				continue;
			}

			$name = $klassenNamen[$klasse] ?? $klasse;
			$letzter = $verlauf[\count($verlauf) - 1];
			$wertung = (int) round($letzter['wertung']).(Wertungsrechner::vorlaeufig($letzter['abweichung']) ? '?' : '');

			$kurven[] = array(
				'beschriftung' => sprintf($texte['beschriftung'] ?? '%s: %s (%s)', $name, $wertung, \count($verlauf)),
				'svg'          => Kurve::svg($verlauf, sprintf($texte['kurve'] ?? '%s', $name)),
			);
		}

		$this->Template->kurven = $kurven;
	}
}
