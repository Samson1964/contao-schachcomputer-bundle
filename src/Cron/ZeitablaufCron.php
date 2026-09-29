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
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partiedienst;

/**
 * Beendet jede Minute Partien, deren Uhr oder Frist abgelaufen ist.
 *
 * Ohne diesen Lauf gingen verlassene Partien erst in die Wertung ein, wenn
 * der Spieler die Seite wieder aufruft. Contaos Cron läuft nur, wenn jemand
 * die Website aufruft oder contao:cron als echter Cronjob eingerichtet ist;
 * das Ergebnis stimmt in jedem Fall, es wird nur später verrechnet.
 */
#[AsCronJob('minutely')]
class ZeitablaufCron
{
	private ContaoFramework $framework;

	private Partiedienst $partiedienst;

	/**
	 * Übernimmt die benötigten Dienste.
	 *
	 * @param ContaoFramework $framework    Setzt beim Initialisieren Contaos Zeitzone
	 * @param Partiedienst    $partiedienst Prüft und beendet die Partien
	 */
	public function __construct(ContaoFramework $framework, Partiedienst $partiedienst)
	{
		$this->framework = $framework;
		$this->partiedienst = $partiedienst;
	}

	/**
	 * Prüft alle laufenden Partien zum aktuellen Zeitpunkt.
	 *
	 * Initialisiert zuerst das Framework: Unter contao:cron (CLI) tut das
	 * sonst niemand, und die Statistik zählte beendete Partien in der Stunde
	 * nach der Zeitzone aus php.ini statt nach der Zeitzone von Contao.
	 */
	public function __invoke(): void
	{
		$this->framework->initialize();
		$this->pruefen((int) floor(microtime(true) * 1000));
	}

	/**
	 * Prüft alle laufenden Partien zu einem festen Zeitpunkt.
	 *
	 * @param int $jetztMs Zeitpunkt in Millisekunden
	 *
	 * @return int Zahl der beendeten oder abgebrochenen Partien
	 */
	public function pruefen(int $jetztMs): int
	{
		return $this->partiedienst->allePruefen($jetztMs);
	}
}
