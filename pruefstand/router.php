<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

/*
 * Router für den eingebauten PHP-Webserver im Prüfstand.
 *
 * Vorhandene Dateien liefert der Server selbst aus, alles Übrige geht an
 * Contaos index.php. .wasm-Dateien werden ausdrücklich als application/wasm
 * gesendet, damit Stockfish wie auf einem echten Server lädt.
 */

$wurzel = $_SERVER['DOCUMENT_ROOT'] ?? '';
$pfad = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ('' !== $wurzel && '/' !== $pfad && is_file($wurzel.$pfad)) {
	if (str_ends_with($pfad, '.wasm')) {
		header('Content-Type: application/wasm');
		readfile($wurzel.$pfad);

		return true;
	}

	return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $wurzel.'/index.php';

require $wurzel.'/index.php';
