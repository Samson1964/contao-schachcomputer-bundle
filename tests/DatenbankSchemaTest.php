<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests;

use Contao\CoreBundle\Doctrine\Schema\DcaSchemaProvider;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
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
	 *
	 * @param string $tabelle Name der Tabelle, etwa „tl_schachcomputer_partie“
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
	 * Die Uhr des Computers steht in MySQL und SQLite auf -1, solange nichts
	 * anderes gespeichert wird.
	 *
	 * -1 kennzeichnet Partien, die vor Fassung 1.1.0 begonnen wurden; sie
	 * bekommen die Spalte beim Update mit ihrem Standardwert. Contao liest den
	 * Standardwert per regulärem Ausdruck aus der sql-Angabe und erkennt dabei
	 * nur Ziffern oder Werte in Anführungszeichen. Ein „default -1“ ohne
	 * Anführungszeichen ginge verloren, MySQL füllte die bestehenden Zeilen
	 * mit 0, und in jeder laufenden Altpartie verlöre der Computer beim
	 * nächsten Zug auf Zeit. Deshalb wird hier Contaos eigener Leser
	 * aufgerufen (private Methode per Reflection, Stand Contao 5.7).
	 */
	public function testComputerUhrStandardwertMinusEins(): void
	{
		$GLOBALS['TL_DCA'] = array();
		$GLOBALS['TL_LANG'] = array();
		include __DIR__.'/../src/Resources/contao/dca/tl_schachcomputer_partie.php';
		$sql = $GLOBALS['TL_DCA']['tl_schachcomputer_partie']['fields']['restzeitEngine']['sql'];
		unset($GLOBALS['TL_DCA'], $GLOBALS['TL_LANG']);

		// serverVersion erspart die Verbindung zu einem MySQL-Server
		$doctrine = $this->createMock(Registry::class);
		$doctrine->method('getConnection')->willReturn(DriverManager::getConnection(array('driver' => 'pdo_mysql', 'serverVersion' => '8.0.30')));
		// Seit PHP 8.1 darf Reflection auch nichtöffentliche Methoden ohne setAccessible() aufrufen
		$leser = new \ReflectionMethod(DcaSchemaProvider::class, 'parseColumnSql');
		$tabelle = new Table('tl_schachcomputer_partie');
		$leser->invoke(new DcaSchemaProvider($this->createMock(ContaoFramework::class), $doctrine), $tabelle, 'restzeitEngine', $sql);

		$spalte = $tabelle->getColumn('restzeitEngine');
		$this->assertSame('-1', (string) $spalte->getDefault());
		$this->assertFalse($spalte->getUnsigned());
		$this->assertTrue($spalte->getNotnull());

		$sqlite = Datenbank::verbindung();
		$sqlite->executeStatement("INSERT INTO tl_schachcomputer_partie (gast) VALUES ('alt')");
		$this->assertSame(-1, (int) $sqlite->fetchOne('SELECT restzeitEngine FROM tl_schachcomputer_partie'));
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
