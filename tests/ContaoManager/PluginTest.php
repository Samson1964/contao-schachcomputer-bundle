<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachcomputerBundle\ContaoManager\Plugin;
use Schachbulle\ContaoSchachcomputerBundle\ContaoSchachcomputerBundle;

/**
 * Prüft die Anmeldung des Bundles beim Contao Manager.
 */
class PluginTest extends TestCase
{
	/**
	 * Das Bundle wird genau einmal und nach dem Contao-Core angemeldet.
	 */
	public function testBundleWirdNachDemCoreGeladen(): void
	{
		$bundles = (new Plugin())->getBundles($this->createMock(ParserInterface::class));

		$this->assertCount(1, $bundles);
		$this->assertSame(ContaoSchachcomputerBundle::class, $bundles[0]->getName());
		$this->assertSame(array(ContaoCoreBundle::class), $bundles[0]->getLoadAfter());
	}
}
