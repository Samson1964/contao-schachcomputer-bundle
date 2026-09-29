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
 * Prüft, dass Deutsch und Englisch dieselben Schlüssel haben.
 *
 * Aufbau wie im Schachaufgaben-Bundle. Neue Sprachdateien in sprachdateien()
 * eintragen.
 */
class SprachdateienTest extends TestCase
{
	private const SPRACHEN = __DIR__.'/../../src/Resources/contao/languages';

	/**
	 * Deutsch und Englisch haben dieselben Schlüssel.
	 *
	 * @dataProvider sprachdateien
	 */
	public function testDeutschUndEnglischHabenDieselbenSchluessel(string $datei): void
	{
		$this->assertSame(
			$this->schluessel($this->laden('de', $datei)),
			$this->schluessel($this->laden('en', $datei))
		);
	}

	/**
	 * Jede Sprachdatei ist in beiden Sprachen vorhanden.
	 */
	public function testKeineDateiFehlt(): void
	{
		$namen = static fn (string $sprache): array => array_map('basename', glob(self::SPRACHEN.'/'.$sprache.'/*.php'));

		$this->assertSame($namen('de'), $namen('en'));
	}

	/**
	 * Liefert die Sprachdateien, die es in beiden Sprachen geben muss.
	 *
	 * @return array<string, array{string}>
	 */
	public function sprachdateien(): array
	{
		$dateien = array_map(static fn (string $pfad): string => basename($pfad, '.php'), glob(self::SPRACHEN.'/de/*.php'));

		return array_combine($dateien, array_map(static fn (string $datei): array => array($datei), $dateien));
	}

	/**
	 * Lädt eine Sprachdatei in einen leeren $GLOBALS['TL_LANG'].
	 *
	 * @param string $sprache Sprachkürzel, etwa „de"
	 * @param string $datei   Dateiname ohne Endung
	 *
	 * @return array<string, mixed> Der Inhalt von $GLOBALS['TL_LANG']
	 */
	private function laden(string $sprache, string $datei): array
	{
		$GLOBALS['TL_LANG'] = array();
		include self::SPRACHEN."/$sprache/$datei.php";
		$inhalt = $GLOBALS['TL_LANG'];
		unset($GLOBALS['TL_LANG']);

		return $inhalt;
	}

	/**
	 * Sammelt alle Schlüsselpfade eines verschachtelten Arrays.
	 *
	 * Listen (Beschriftung und Erklärung) zählen als Blatt, damit nur die
	 * benannten Schlüssel verglichen werden.
	 *
	 * @param array<mixed> $werte    Das Array
	 * @param string       $vorsilbe Pfad bis hierher
	 *
	 * @return array<int, string> Sortierte Pfade wie „MSC.schachcomputer.laden"
	 */
	private function schluessel(array $werte, string $vorsilbe = ''): array
	{
		$pfade = array();

		foreach ($werte as $schluessel => $wert) {
			$pfad = $vorsilbe.$schluessel;

			if (\is_array($wert) && !array_is_list($wert)) {
				$pfade = array_merge($pfade, $this->schluessel($wert, $pfad.'.'));
			} else {
				$pfade[] = $pfad;
			}
		}

		sort($pfade);

		return $pfade;
	}
}
