<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Schachbulle\ContaoSchachcomputerBundle\ContaoSchachcomputerBundle;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Meldet das Bundle und seine Routen beim Contao Manager an.
 *
 * Ohne diese Klasse taucht das Bundle nicht im Kernel auf, weil Contao die
 * Bundle-Liste aus den Plugins aller installierten Pakete zusammensetzt.
 */
class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
	/**
	 * Meldet das Bundle beim Kernel an.
	 *
	 * Das Bundle wird nach dem Contao-Core geladen, damit dessen DCA-Dateien
	 * (tl_member, tl_module) schon vorliegen, wenn das Bundle eigene Felder
	 * und Paletten ergänzt.
	 *
	 * @param ParserInterface $parser Wird nicht ausgewertet, weil das Bundle
	 *                                keine Konfigurationsdateien parsen lässt
	 *
	 * @return array<int, BundleConfig> Die Konfiguration dieses einen Bundles
	 */
	public function getBundles(ParserInterface $parser): array
	{
		return array(
			BundleConfig::create(ContaoSchachcomputerBundle::class)
				->setLoadAfter(array(ContaoCoreBundle::class)),
		);
	}

	/**
	 * Lädt die Routen der JSON-Schnittstelle und des PGN-Downloads.
	 *
	 * Der Contao Manager übergibt einen LoaderResolver, keinen Loader; der
	 * passende YAML-Lader muss erst über resolve() gesucht werden.
	 *
	 * @param LoaderResolverInterface $resolver Findet den passenden Lader für YAML
	 * @param KernelInterface         $kernel   Wird nicht benötigt
	 *
	 * @return RouteCollection|null Die Routen aus Resources/config/routes.yaml,
	 *                              oder null, wenn kein YAML-Lader verfügbar ist
	 */
	public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): ?RouteCollection
	{
		$datei = __DIR__.'/../Resources/config/routes.yaml';
		$loader = $resolver->resolve($datei);

		return false === $loader ? null : $loader->load($datei);
	}
}
