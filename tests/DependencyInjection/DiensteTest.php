<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\DependencyInjection;

use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Schachbulle\ContaoSchachcomputerBundle\Controller\PartieController;
use Schachbulle\ContaoSchachcomputerBundle\DependencyInjection\ContaoSchachcomputerExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Prüft, dass sich alle Dienste des Bundles verdrahten lassen.
 *
 * Die Dienste, die Contao liefert (Datenbank, Anmeldung), werden als
 * synthetische Dienste vorgegeben. Scheitert das Autowiring einer Klasse,
 * bricht compile() mit einer Meldung ab – hier früher als erst im Prüfstand.
 */
class DiensteTest extends TestCase
{
	/**
	 * Der Container lässt sich kompilieren, der Controller ist öffentlich.
	 */
	public function testContainerLaesstSichKompilieren(): void
	{
		$container = new ContainerBuilder();
		$container->setParameter('kernel.project_dir', \dirname(__DIR__, 2));

		foreach (array(Connection::class, TokenStorageInterface::class, RequestStack::class, LoggerInterface::class, ContaoFramework::class) as $contaoDienst) {
			$container->register($contaoDienst)->setSynthetic(true)->setPublic(true);
		}

		(new ContaoSchachcomputerExtension())->load(array(), $container);
		$container->compile();

		$this->assertTrue($container->has(PartieController::class));
		$this->assertTrue($container->getDefinition(PartieController::class)->isPublic());
	}
}
