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
use Contao\System;
use Schachbulle\ContaoSchachcomputerBundle\Rangliste\Platzierung;
use Schachbulle\ContaoSchachcomputerBundle\Rangliste\Ranglisten;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Klassen;

/**
 * Frontend-Modul „Schachcomputer: Rangliste".
 *
 * Zeigt je nach Einstellung die aktuelle Rangliste, die ewige Bestenliste
 * oder eine Monatsrangliste einer Wertungsklasse. Bei der Monatsrangliste
 * wählt der Besucher den Monat über ?monat=JJJJ-MM. Das angemeldete Mitglied
 * wird hervorgehoben und, falls es nicht unter den gezeigten Plätzen ist,
 * mit seinem Platz unter der Tabelle angehängt.
 */
class RanglisteModule extends SchachcomputerModul
{
	public const MODI = array('aktuell', 'ewig', 'stichtag');

	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_schachcomputer_rangliste';

	/**
	 * Liest die gewählte Liste und bereitet Kopf und Zeilen für das Template auf.
	 */
	protected function compile(): void
	{
		System::loadLanguageFile('default');
		$GLOBALS['TL_CSS'][] = 'bundles/contaoschachcomputer/listen.css';

		$container = System::getContainer();
		$dienst = $container->get(Ranglisten::class);
		$texte = $GLOBALS['TL_LANG']['MSC']['schachcomputer_rangliste'] ?? array();
		$klasse = Klassen::gueltig((string) $this->schachcomputerKlasse) ? (string) $this->schachcomputerKlasse : Klassen::BLITZ;
		$modus = \in_array($this->schachcomputerModus, self::MODI, true) ? (string) $this->schachcomputerModus : 'aktuell';
		$anzahl = (int) $this->schachcomputerAnzahl > 0 ? (int) $this->schachcomputerAnzahl : 20;
		$monate = array();
		$monat = '';

		if ('stichtag' === $modus) {
			$monate = $dienst->monate($klasse);
			$gewuenscht = (string) Input::get('monat');
			$monat = \in_array($gewuenscht, $monate, true) ? $gewuenscht : ($monate[0] ?? '');
			$liste = '' === $monat ? array() : $dienst->stichtag($klasse, $monat);
		} elseif ('ewig' === $modus) {
			$liste = $dienst->ewig($klasse, time());
		} else {
			$liste = $dienst->aktuell($klasse, time());
		}

		// Der TokenChecker ist in beiden Fassungen ein öffentlicher Dienst
		$eigenerName = $container->get('contao.security.token_checker')->getFrontendUsername();
		$zeilen = array();
		$eigene = null;

		foreach ($liste as $index => $eintrag) {
			$zeile = array(
				'eigene' => null !== $eigenerName && $eintrag['username'] === $eigenerName,
				'zellen' => $this->zellen($modus, $eintrag, $texte),
			);

			if ($index < $anzahl) {
				$zeilen[] = $zeile;
			} elseif ($zeile['eigene']) {
				$eigene = $zeile;
			}
		}

		$kopf = array($texte['platz'] ?? '', $texte['name'] ?? '', 'ewig' === $modus ? ($texte['hoechstwert'] ?? '') : ($texte['wertung'] ?? ''), 'ewig' === $modus ? ($texte['datum'] ?? '') : ($texte['partien'] ?? ''));

		if ('stichtag' === $modus) {
			$kopf[] = $texte['veraenderung'] ?? '';
		}

		$this->Template->texte = $texte;
		$this->Template->modus = $modus;
		$this->Template->ueberschrift = $GLOBALS['TL_LANG']['MSC']['schachcomputer']['klassen'][$klasse] ?? $klasse;
		$this->Template->kopf = $kopf;
		$this->Template->zeilen = $zeilen;
		$this->Template->eigene = $eigene;
		$this->Template->formularId = 'schachcomputer-monat-'.$this->id;
		$this->Template->monatText = '' === $monat ? '' : $this->monatText($monat);
		$this->Template->monate = array_map(fn (string $wert): array => array('wert' => $wert, 'text' => $this->monatText($wert), 'aktiv' => $wert === $monat), $monate);
	}

	/**
	 * Bereitet die Zellen einer Zeile auf.
	 *
	 * @param string               $modus   aktuell, ewig oder stichtag
	 * @param array<string, mixed> $eintrag Eintrag aus Ranglisten
	 * @param array<string, mixed> $texte   Texte der Rangliste
	 *
	 * @return array<int, string> Platz, Name, Wertung, Partien bzw. Datum und bei Monatslisten die Veränderung
	 */
	private function zellen(string $modus, array $eintrag, array $texte): array
	{
		$zellen = array(
			$eintrag['platz'].'.',
			$eintrag['name'],
			(string) $eintrag['wertung'],
			'ewig' === $modus ? Date::parse(Config::get('dateFormat'), $eintrag['datum']) : (string) $eintrag['partien'],
		);

		if ('stichtag' === $modus) {
			$zellen[] = Platzierung::veraenderung($eintrag['platz'], $eintrag['wertung'], $eintrag['platzVormonat'], $eintrag['wertungVormonat'], $texte['neu'] ?? '');
		}

		return $zellen;
	}

	/**
	 * Schreibt einen Monat aus, etwa „Oktober 2026".
	 *
	 * @param string $monat JJJJ-MM
	 *
	 * @return string Monatsname (aus den Contao-Sprachdateien) und Jahr
	 */
	private function monatText(string $monat): string
	{
		[$jahr, $zahl] = array_map('intval', explode('-', $monat));

		return Date::parse('F Y', (int) mktime(0, 0, 0, $zahl, 1, $jahr));
	}
}
