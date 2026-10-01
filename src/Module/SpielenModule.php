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
use Schachbulle\ContaoSchachcomputerBundle\Engine\Stufen;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partiedienst;

/**
 * Frontend-Modul „Schachcomputer: Spielen".
 *
 * Das Modul gibt nur das Gerüst aus: Brett, Startformular, Uhr und Knöpfe.
 * Alles Weitere erledigt spielen.js über die JSON-Schnittstelle
 * (PartieController). Das klassische Modul über $GLOBALS['FE_MOD'] läuft
 * unter Contao 4.13 und 5.7 ohne Versionsweiche.
 */
class SpielenModule extends SchachcomputerModul
{
	/**
	 * Eigene Skripte; ihr Änderungsdatum ergibt die Versionsangabe.
	 */
	private const SKRIPTE = array('spielen.js', 'engine.js', 'uhr.js', 'vorwahl.js');

	/**
	 * @var string
	 */
	protected $strTemplate = 'mod_schachcomputer_spielen';

	/**
	 * Stellt Adressen, Bedenkzeiten, Stufen und Texte für das Template bereit.
	 *
	 * Die Adressen werden über den Router erzeugt, damit ein Contao in einem
	 * Unterverzeichnis funktioniert. Die Texte gehen als JSON an das Skript,
	 * damit es selbst keine Texte enthält.
	 */
	protected function compile(): void
	{
		System::loadLanguageFile('default');

		$container = System::getContainer();
		$router = $container->get('router');
		$request = $container->get('request_stack')->getCurrentRequest();
		$basis = (null === $request ? '' : $request->getBasePath()).'/bundles/contaoschachcomputer/';
		$texte = $GLOBALS['TL_LANG']['MSC']['schachcomputer'] ?? array();

		$GLOBALS['TL_CSS'][] = 'bundles/contaoschachcomputer/vendor/cm-chessboard/assets/chessboard.css';
		$GLOBALS['TL_CSS'][] = 'bundles/contaoschachcomputer/vendor/cm-chessboard/assets/extensions/markers/markers.css';
		$GLOBALS['TL_CSS'][] = 'bundles/contaoschachcomputer/vendor/cm-chessboard/assets/extensions/promotion-dialog/promotion-dialog.css';
		$GLOBALS['TL_CSS'][] = 'bundles/contaoschachcomputer/spielen.css';

		$stufen = Stufen::alle();

		$this->Template->texte = $texte;
		$this->Template->skript = $basis.'spielen.js?v='.$this->skriptVersion();
		$this->Template->konfiguration = array(
			'standUrl'      => $router->generate('schachcomputer_stand'),
			'startUrl'      => $router->generate('schachcomputer_start'),
			'zugUrl'        => $router->generate('schachcomputer_zug'),
			'aufgebenUrl'   => $router->generate('schachcomputer_aufgeben'),
			'abbrechenUrl'  => $router->generate('schachcomputer_abbrechen'),
			'remisUrl'      => $router->generate('schachcomputer_remis'),
			'uebungUrl'     => $router->generate('schachcomputer_uebung'),
			'assetsUrl'     => $basis.'vendor/cm-chessboard/assets/',
			'engineUrl'     => $basis.'vendor/stockfish/stockfish-19-lite-single.js',
			'bedenkzeiten'  => $container->get(Partiedienst::class)->bedenkzeiten(),
			'stufen'        => $stufen,
			'einstellungen' => array_combine($stufen, array_map(array(Stufen::class, 'einstellungen'), $stufen)),
			'mitglied'      => $container->get('contao.security.token_checker')->hasFrontendUser(),
			'texte'         => $texte,
		);
	}

	/**
	 * Bildet eine kurze Versionsangabe aus dem Änderungsdatum der eigenen Skripte.
	 *
	 * Viele Server liefern /bundles mit einem Jahr Cachedauer aus; ohne
	 * Angabe liefe nach einem Update weiter das alte Skript. Gelesen wird aus
	 * Resources/public des Bundles, weil das öffentliche Verzeichnis je nach
	 * Installation eine Kopie oder ein Verweis ist.
	 *
	 * @return string Acht Hexadezimalzeichen, die sich mit jeder Änderung ändern
	 */
	private function skriptVersion(): string
	{
		$verzeichnis = __DIR__.'/../Resources/public/';
		$stand = '';

		foreach (self::SKRIPTE as $datei) {
			$stand .= $datei.'@'.(@filemtime($verzeichnis.$datei) ?: 0).';';
		}

		return substr(md5($stand), 0, 8);
	}
}
