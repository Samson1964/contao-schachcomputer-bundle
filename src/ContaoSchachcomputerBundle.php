<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle-Klasse des Schachcomputer-Bundles.
 *
 * Die Klasse bleibt leer, weil das Bundle weder eigene Compiler-Pässe noch
 * abweichende Verzeichnisse braucht. Symfony leitet Name, Pfad und den Namen
 * der DI-Extension (ContaoSchachcomputerExtension) aus dem Klassennamen ab;
 * die öffentlichen Dateien landen deshalb unter bundles/contaoschachcomputer/.
 */
class ContaoSchachcomputerBundle extends Bundle
{
}
