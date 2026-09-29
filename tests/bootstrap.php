<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

/*
 * Bootstrap für die Unit-Tests.
 *
 * Anders als im Schachaufgaben-Bundle gibt es keinen Ersatz-Autoloader: Die
 * Tests brauchen p-chess/chess, Doctrine DBAL (SQLite im Arbeitsspeicher) und
 * Symfony-Komponenten. Deshalb muss vorher „composer update" gelaufen sein.
 */

$autoload = __DIR__.'/../vendor/autoload.php';

if (!is_file($autoload)) {
	fwrite(STDERR, "vendor/autoload.php fehlt. Bitte zuerst im Bundle-Verzeichnis ausführen:\n/c/xampp/php/php.exe C:/Users/samso/bin/composer.phar update --no-security-blocking\n");

	exit(1);
}

require $autoload;
