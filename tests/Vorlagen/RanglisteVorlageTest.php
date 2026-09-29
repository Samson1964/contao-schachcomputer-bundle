<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Vorlagen;

use PHPUnit\Framework\TestCase;

/**
 * Prüft, dass die Ranglisten-Vorlage Mitgliedsnamen maskiert ausgibt.
 *
 * Vor- und Nachname stammen aus tl_member und sind je nach Installation vom
 * Mitglied selbst änderbar. Ungeschützt ausgegeben, könnte ein Mitglied über
 * seinen Namen Skripte in die öffentliche Rangliste schleusen.
 */
class RanglisteVorlageTest extends TestCase
{
	/**
	 * Rendert die Vorlage mit einem Namen, der HTML enthält, in der Liste
	 * und in der angehängten eigenen Zeile.
	 *
	 * Die Vorlage läuft ohne Contao-Framework: Ein schlichtes Objekt stellt
	 * die Variablen und die Blockmethoden (extend, block, endblock) bereit,
	 * die hier nichts tun müssen.
	 */
	public function testNamenWerdenMaskiert(): void
	{
		$gefaehrlich = '<script>alert(1)</script>';
		$daten = array(
			'modus'        => 'aktuell',
			'monate'       => array(),
			'monatText'    => '',
			'formularId'   => 'monat',
			'texte'        => array('leer' => 'leer'),
			'ueberschrift' => 'Blitz',
			'kopf'         => array('Platz', 'Name', 'Wertung', 'Partien'),
			'zeilen'       => array(array('eigene' => false, 'zellen' => array('1.', $gefaehrlich, '1500', '3'))),
			'eigene'       => array('zellen' => array('7.', $gefaehrlich, '1400', '2')),
		);

		$html = $this->rendern(__DIR__.'/../../src/Resources/contao/templates/mod_schachcomputer_rangliste.html5', $daten);

		$this->assertStringNotContainsString($gefaehrlich, $html);
		$this->assertSame(2, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
	}

	/**
	 * Führt eine Contao-Vorlage (.html5) mit den übergebenen Variablen aus.
	 *
	 * @param string               $datei Pfad zur Vorlage
	 * @param array<string, mixed> $daten Variablen, die die Vorlage über $this liest
	 *
	 * @return string Die Ausgabe der Vorlage
	 */
	private function rendern(string $datei, array $daten): string
	{
		$vorlage = new class($daten) {
			/**
			 * @param array<string, mixed> $daten Variablen der Vorlage
			 */
			public function __construct(private array $daten)
			{
			}

			/**
			 * Liefert eine Variable der Vorlage.
			 *
			 * @param string $name Name der Variablen
			 *
			 * @return mixed Der Wert oder null, wenn er fehlt
			 */
			public function __get(string $name): mixed
			{
				return $this->daten[$name] ?? null;
			}

			/**
			 * Meldet, ob eine Variable gesetzt ist. Ohne diese Methode hielte
			 * empty($this->zeilen) in der Vorlage jede Liste für leer.
			 *
			 * @param string $name Name der Variablen
			 *
			 * @return bool true, wenn die Variable vorhanden und nicht null ist
			 */
			public function __isset(string $name): bool
			{
				return isset($this->daten[$name]);
			}

			/**
			 * Nimmt die Elternvorlage entgegen; hier ohne Wirkung.
			 *
			 * @param string $name Name der Elternvorlage
			 */
			public function extend(string $name): void
			{
			}

			/**
			 * Beginnt einen Block; hier ohne Wirkung.
			 *
			 * @param string $name Name des Blocks
			 */
			public function block(string $name): void
			{
			}

			/**
			 * Beendet einen Block; hier ohne Wirkung.
			 */
			public function endblock(): void
			{
			}

			/**
			 * Führt die Vorlage im Kontext dieses Objekts aus.
			 *
			 * @param string $datei Pfad zur Vorlage
			 *
			 * @return string Die Ausgabe
			 */
			public function ausfuehren(string $datei): string
			{
				ob_start();

				try {
					include $datei;
				} finally {
					$ausgabe = (string) ob_get_clean();
				}

				return $ausgabe;
			}
		};

		return $vorlage->ausfuehren($datei);
	}
}
