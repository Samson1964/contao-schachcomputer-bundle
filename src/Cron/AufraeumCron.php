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
use Doctrine\DBAL\Connection;

/**
 * Löscht täglich beendete Gastpartien, die älter als einen Tag sind.
 *
 * Gäste haben kein Partiearchiv; ihre Partien stehen nur in der Datenbank,
 * damit der Server sie führen kann. Einen Tag Frist gibt es, damit ein Gast
 * eine vom Cron beendete Partie beim nächsten Aufruf noch verrechnet bekommt.
 */
#[AsCronJob('daily')]
class AufraeumCron
{
	/**
	 * So lange bleiben beendete Gastpartien stehen (Sekunden).
	 */
	public const FRIST = 86400;

	private Connection $connection;

	/**
	 * Übernimmt die Datenbankverbindung.
	 *
	 * @param Connection $connection Die Datenbankverbindung von Contao
	 */
	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
	}

	/**
	 * Räumt zum aktuellen Zeitpunkt auf.
	 */
	public function __invoke(): void
	{
		$this->aufraeumen(time());
	}

	/**
	 * Löscht beendete und abgebrochene Gastpartien vor Ablauf der Frist.
	 *
	 * @param int $jetzt Zeitpunkt in Sekunden
	 *
	 * @return int Zahl der gelöschten Partien
	 */
	public function aufraeumen(int $jetzt): int
	{
		return (int) $this->connection->executeStatement(
			"DELETE FROM tl_schachcomputer_partie WHERE memberId=0 AND status<>'laeuft' AND ende>0 AND ende<?",
			array($jetzt - self::FRIST)
		);
	}
}
