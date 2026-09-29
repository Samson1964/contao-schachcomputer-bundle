<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Hält die SQLite-Testdatenbank und die DCA-Dateien im Gleichschritt.
 *
 * Fehlt eine Spalte in der Testdatenbank, würden Tests an einer Stelle
 * vorbeiprüfen, die in MySQL anders aussieht; fehlt sie in der DCA, legt
 * Contao sie gar nicht erst an.
 */
class DatenbankSchemaTest extends TestCase
{
	/**
	 * Jede Tabelle hat in DCA und Testdatenbank dieselben Spalten.
	 *
	 * @dataProvider tabellen
	 */
	public function testSpaltenStimmenUeberein(string $tabelle): void
	{
		$GLOBALS['TL_DCA'] = array();
		$GLOBALS['TL_LANG'] = array();
		include __DIR__.'/../src/Resources/contao/dca/'.$tabelle.'.php';

		$dca = array_keys(array_filter(
			$GLOBALS['TL_DCA'][$tabelle]['fields'],
			static fn (array $feld): bool => isset($feld['sql'])
		));
		unset($GLOBALS['TL_DCA'], $GLOBALS['TL_LANG']);

		$sqlite = array_column(Datenbank::verbindung()->fetchAllAssociative('PRAGMA table_info('.$tabelle.')'), 'name');

		sort($dca);
		sort($sqlite);
		$this->assertSame($dca, $sqlite);
	}

	/**
	 * Liefert die Tabellen des Bundles.
	 *
	 * @return array<string, array{string}>
	 */
	public function tabellen(): array
	{
		$tabellen = array('tl_schachcomputer_bedenkzeit', 'tl_schachcomputer_spieler', 'tl_schachcomputer_verlauf', 'tl_schachcomputer_partie', 'tl_schachcomputer_stichtag', 'tl_schachcomputer_statistik');

		return array_combine($tabellen, array_map(static fn (string $t): array => array($t), $tabellen));
	}
}
