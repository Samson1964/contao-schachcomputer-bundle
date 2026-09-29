<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Lädt die Dienstdefinitionen des Bundles in den Container.
 *
 * Ohne diese Klasse würde Symfony die services.yaml nie lesen, und Controller,
 * Cronjobs und Listener blieben stumm. Die Basisklasse liegt in Symfony 5.4
 * (Contao 4.13) wie in Symfony 7 (Contao 5.7) unter demselben Namensraum.
 */
class ContaoSchachcomputerExtension extends Extension
{
	/**
	 * Liest die services.yaml des Bundles ein.
	 *
	 * @param array<int|string, mixed> $mergedConfig Die zusammengeführte
	 *                                               Bundle-Konfiguration; das
	 *                                               Bundle wertet sie nicht aus
	 * @param ContainerBuilder         $container    Der Container, in den die
	 *                                               Definitionen geschrieben werden
	 */
	public function load(array $mergedConfig, ContainerBuilder $container): void
	{
		$loader = new YamlFileLoader(
			$container,
			new FileLocator(__DIR__.'/../Resources/config')
		);

		$loader->load('services.yaml');
	}
}
