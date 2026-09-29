<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Schachbulle\ContaoSchachcomputerBundle\Rangliste\Stichtagsliste;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Klassen;

/**
 * Speichert die Monatsranglisten zum Monatsersten.
 *
 * Läuft stündlich statt monatlich: Contaos Cron wird nur ausgeführt, wenn
 * jemand die Website aufruft oder contao:cron läuft. Der erste Lauf nach
 * Monatsbeginn legt die Listen an; alle weiteren finden sie vor und kosten
 * nur eine Abfrage je Klasse. Weil die Liste aus dem Verlauf vor dem
 * Stichtag entsteht, ist es gleich, wann dieser erste Lauf kommt.
 */
#[AsCronJob('hourly')]
class StichtagCron
{
	private ContaoFramework $framework;

	private Stichtagsliste $stichtagsliste;

	/**
	 * Übernimmt die benötigten Dienste.
	 *
	 * @param ContaoFramework $framework      Setzt beim Initialisieren Contaos Zeitzone
	 * @param Stichtagsliste  $stichtagsliste Baut und speichert die Listen
	 */
	public function __construct(ContaoFramework $framework, Stichtagsliste $stichtagsliste)
	{
		$this->framework = $framework;
		$this->stichtagsliste = $stichtagsliste;
	}

	/**
	 * Legt fehlende Listen des laufenden Monats an.
	 *
	 * Initialisiert zuerst das Framework: Unter contao:cron (CLI) tut das
	 * sonst niemand, und der Monatserste (mktime/date) läge in der Zeitzone
	 * aus php.ini statt in der von Contao – bei UTC gegenüber Berlin um ein
	 * bis zwei Stunden verschoben.
	 */
	public function __invoke(): void
	{
		$this->framework->initialize();
		$this->erstellen(time());
	}

	/**
	 * Legt fehlende Listen des Monats an, in dem der Zeitpunkt liegt.
	 *
	 * @param int $zeitpunkt Unix-Zeitstempel
	 *
	 * @return int Zahl der gespeicherten Zeilen über alle Klassen
	 */
	public function erstellen(int $zeitpunkt): int
	{
		$stichtag = Stichtagsliste::stichtag($zeitpunkt);
		$gespeichert = 0;

		foreach (Klassen::ALLE as $klasse) {
			$gespeichert += $this->stichtagsliste->erstellen($klasse, $stichtag);
		}

		return $gespeichert;
	}
}
