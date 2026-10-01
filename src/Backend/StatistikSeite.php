<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Backend;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\System;
use Schachbulle\ContaoSchachcomputerBundle\Statistik\Statistik;
use Schachbulle\ContaoSchachcomputerBundle\Statistik\Zeitraum;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Statistik der Aufrufe und Partien (do=schachcomputer_partien&key=statistik).
 *
 * Aufbau wie die Statistik des Schachaufgaben-Bundles (und des
 * counter-Bundles): Ebenen Tag, Monat und Jahr zum Blättern, Kennzahlen,
 * zwei Balkendiagramme eine Ebene feiner und zwei Tabellen. Die Rechnungen
 * mit Zeiträumen stehen in Statistik\Zeitraum, die Abfragen in
 * Statistik\Statistik; diese Klasse setzt nur die Seite zusammen.
 *
 * Öffentlicher Dienst, weil Contao den key-Callback über
 * System::importStatic() aus dem Container holt.
 */
class StatistikSeite
{
	/**
	 * Zeilen der beiden Tabellen.
	 */
	private const TOP = 20;

	private Statistik $statistik;

	private RequestStack $requestStack;

	/**
	 * Übernimmt die benötigten Dienste.
	 *
	 * @param Statistik    $statistik    Liefert Zähler, Verläufe und Tabellen
	 * @param RequestStack $requestStack Liefert Ebene und Datum aus der Adresse
	 */
	public function __construct(Statistik $statistik, RequestStack $requestStack)
	{
		$this->statistik = $statistik;
		$this->requestStack = $requestStack;
	}

	/**
	 * Baut die Statistikseite.
	 *
	 * @return string Das HTML der Seite
	 */
	public function ausfuehren(): string
	{
		System::loadLanguageFile('default');
		$GLOBALS['TL_CSS'][] = 'bundles/contaoschachcomputer/backend.css';

		$request = $this->requestStack->getCurrentRequest();
		$ebene = null === $request ? 'monat' : (string) $request->query->get('ebene', 'monat');
		$ebene = \in_array($ebene, Zeitraum::EBENEN, true) ? $ebene : 'monat';
		$zeitpunkt = Zeitraum::zeitpunkt(null === $request ? '' : (string) $request->query->get('datum', ''), time());
		$monate = (array) ($GLOBALS['TL_LANG']['MONTHS'] ?? array());
		$texte = $GLOBALS['TL_LANG']['MSC']['schachcomputer_statistik'] ?? array();

		[$von, $bis, $beginn, $ende] = Zeitraum::grenzen($ebene, $zeitpunkt);
		$einheit = Zeitraum::einheit($ebene);
		$summen = $this->statistik->summen($von, $bis);
		$aufrufe = $this->statistik->verlauf(array(Statistik::AUFRUF), $von, $bis, $einheit);
		$gestartet = $this->statistik->verlauf(array(Statistik::GESTARTET), $von, $bis, $einheit);
		$beendet = $this->statistik->verlauf(Statistik::BEENDET, $von, $bis, $einheit);
		$gewonnen = $this->statistik->verlauf(array(Statistik::GEWONNEN), $von, $bis, $einheit);
		$balkenGestartet = array();
		$balkenGewonnen = array();

		foreach (Zeitraum::achse($ebene, $zeitpunkt, $monate) as $schluessel => $titel) {
			$balkenGestartet[] = array('titel' => $titel, 'wert' => $gestartet[$schluessel] ?? 0, 'wert2' => $aufrufe[$schluessel] ?? 0);
			$balkenGewonnen[] = array('titel' => $titel, 'wert' => $gewonnen[$schluessel] ?? 0, 'wert2' => $beendet[$schluessel] ?? 0);
		}

		// „beendet" ist keine eigene Art, sondern die Summe aus Sieg, Remis und Niederlage
		$beendetSumme = array('mitglieder' => 0, 'gaeste' => 0, 'gesamt' => 0);

		foreach (Statistik::BEENDET as $art) {
			foreach ($beendetSumme as $gruppe => $wert) {
				$beendetSumme[$gruppe] = $wert + $summen[$art][$gruppe];
			}
		}

		$punkte = $summen[Statistik::GEWONNEN]['gesamt'] + 0.5 * $summen[Statistik::REMIS]['gesamt'];
		$vor = Zeitraum::verschieben($ebene, $zeitpunkt, 1);

		$template = new BackendTemplate('be_schachcomputer_statistik');
		$template->texte = $texte;
		$template->zeitraum = Zeitraum::bezeichnung($ebene, $zeitpunkt, $monate);
		$template->summen = $summen + array('beendet' => $beendetSumme);
		$template->quote = $beendetSumme['gesamt'] > 0 ? (int) round(100 * $punkte / $beendetSumme['gesamt']) : null;
		$template->bestand = $this->statistik->bestand($beginn, $ende);
		$template->hatDaten = array_sum(array_column($summen, 'gesamt')) > 0;
		$template->diagrammGestartet = Diagramm::balken($balkenGestartet, (string) ($texte['diagrammGestartet'] ?? ''), 240, 'monat' === $ebene);
		$template->diagrammGewonnen = Diagramm::balken($balkenGewonnen, (string) ($texte['diagrammGewonnen'] ?? ''), 240, 'monat' === $ebene);
		$template->bedenkzeiten = $this->statistik->bedenkzeiten($beginn, $ende, self::TOP);
		$template->aktivste = array_map(
			fn (array $zeile): array => $zeile + array('url' => $this->mitgliedUrl($zeile['memberId'])),
			$this->statistik->aktivsteMitglieder($beginn, $ende, self::TOP)
		);
		$template->klassen = $GLOBALS['TL_LANG']['MSC']['schachcomputer']['klassen'] ?? array();
		$template->ebenenLinks = array_map(
			fn (string $e): array => array('url' => $this->url($request, $e, $zeitpunkt), 'text' => $texte['ebene_'.$e] ?? $e, 'aktiv' => $e === $ebene),
			Zeitraum::EBENEN
		);
		$template->urlZurueck = $this->url($request, $ebene, Zeitraum::verschieben($ebene, $zeitpunkt, -1));
		$template->urlVor = $this->url($request, $ebene, $vor);
		$template->urlHeute = $this->url($request, $ebene, time());
		$template->kannVor = $vor <= time();
		$template->urlModul = (null === $request ? '' : $request->getBaseUrl().$request->getPathInfo()).'?do=schachcomputer_partien';

		return $template->parse();
	}

	/**
	 * Adresse, unter der das Mitglied im Backend bearbeitet wird.
	 *
	 * Backend::addToUrl() hängt Anfrage-Token und Referer an, wie es Contao bei
	 * seinen eigenen Listen tut. Die Angaben der Statistik (key, ebene, datum)
	 * fallen weg, damit die Adresse nur ins Modul „Mitglieder" führt; über den
	 * Referer kommt der „Zurück"-Knopf des Mitglieds wieder zur Statistik.
	 *
	 * @param int $memberId ID des Mitglieds aus tl_member
	 *
	 * @return string Maskierte Adresse für das Template
	 */
	private function mitgliedUrl(int $memberId): string
	{
		return Backend::addToUrl('do=member&act=edit&id='.$memberId, true, array('key', 'ebene', 'datum'));
	}

	/**
	 * Adresse der Statistik für eine Ebene und einen Zeitpunkt.
	 *
	 * @param Request|null $request   Die Anfrage (für Basisadresse und ref)
	 * @param string       $ebene     tag, monat oder jahr
	 * @param int          $zeitpunkt Ein Zeitpunkt im Zeitraum
	 *
	 * @return string Maskierte Adresse für das Template
	 */
	private function url(?Request $request, string $ebene, int $zeitpunkt): string
	{
		$basis = null === $request ? '' : $request->getBaseUrl().$request->getPathInfo();

		return htmlspecialchars($basis.'?'.http_build_query(array(
			'do'    => 'schachcomputer_partien',
			'key'   => 'statistik',
			'ebene' => $ebene,
			'datum' => date('Y-m-d', $zeitpunkt),
			'ref'   => null === $request ? '' : (string) $request->query->get('ref', ''),
		)), ENT_QUOTES);
	}
}
