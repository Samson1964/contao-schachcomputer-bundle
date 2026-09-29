<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Sprache;

use PHPUnit\Framework\TestCase;

/**
 * Prüft, dass Templates und Skripte nur Texte verwenden, die es gibt.
 *
 * Ein fehlender Schlüssel fiele sonst erst im Browser auf – als leere
 * Beschriftung oder als „undefined".
 */
class TexteTest extends TestCase
{
	private const WURZEL = __DIR__.'/../../src/Resources/';

	/**
	 * Jeder verwendete Schlüssel steht in der deutschen und englischen default.php.
	 *
	 * @dataProvider verwendungen
	 */
	public function testAlleTexteVorhanden(string $datei, string $muster, string $gruppe): void
	{
		preg_match_all($muster, (string) file_get_contents(self::WURZEL.$datei), $treffer);
		$verwendet = array_unique($treffer[1]);
		$this->assertNotSame(array(), $verwendet, "Keine Texte in $datei gefunden – Muster prüfen");

		foreach (array('de', 'en') as $sprache) {
			$GLOBALS['TL_LANG'] = array();
			include self::WURZEL."contao/languages/$sprache/default.php";
			$vorhanden = array_keys($GLOBALS['TL_LANG']['MSC'][$gruppe] ?? array());
			unset($GLOBALS['TL_LANG']);

			$this->assertSame(array(), array_values(array_diff($verwendet, $vorhanden)), "Fehlende Texte ($sprache) für $datei");
		}
	}

	/**
	 * Liefert Datei, Suchmuster und Textgruppe (MSC-Schlüssel).
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public function verwendungen(): array
	{
		return array(
			'Template Spielen' => array('contao/templates/mod_schachcomputer_spielen.html5', "/texte\\['(\\w+)'\\]/", 'schachcomputer'),
			'spielen.js'       => array('public/spielen.js', '/this\.texte\.(\w+)/', 'schachcomputer'),
			'Template Rangliste' => array('contao/templates/mod_schachcomputer_rangliste.html5', "/texte\\['(\\w+)'\\]/", 'schachcomputer_rangliste'),
			'Modul Rangliste'    => array('../Module/RanglisteModule.php', "/texte\\['(\\w+)'\\]/", 'schachcomputer_rangliste'),
			'Template Partien'   => array('contao/templates/mod_schachcomputer_partien.html5', "/texte\\['(\\w+)'\\]/", 'schachcomputer_partien'),
			'Modul Partien'      => array('../Module/PartienModule.php', "/texte\\['(\\w+)'\\]/", 'schachcomputer_partien'),
		);
	}
}
