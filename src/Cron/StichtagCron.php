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
	private Stichtagsliste $stichtagsliste;

	/**
	 * Übernimmt den Dienst, der die Listen baut.
	 *
	 * @param Stichtagsliste $stichtagsliste Baut und speichert die Listen
	 */
	public function __construct(Stichtagsliste $stichtagsliste)
	{
		$this->stichtagsliste = $stichtagsliste;
	}

	/**
	 * Legt fehlende Listen des laufenden Monats an.
	 */
	public function __invoke(): void
	{
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
