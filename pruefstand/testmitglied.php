<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

/*
 * Legt in einer Contao-Testinstallation ein Testmitglied und ein
 * Anmeldemodul auf der Testseite des Bundles an.
 *
 * Das Mitglied heißt „schachcomputer-test" und bekommt bei jedem Aufruf ein
 * neues Zufallspasswort. Die Zugangsdaten stehen danach in
 * pruefstand/zugang.local.md, das Git ignoriert – nie in Chat oder Commit.
 * Das Anmeldemodul heißt „[Test] Schachcomputer Anmeldung"; das Werkzeug
 * contao-testseiten-bauen.php räumt es beim nächsten Neuaufbau mit ab, dann
 * dieses Skript erneut aufrufen.
 *
 * Voraussetzung: Die Testseite (Artikel-Alias „bundle-schachcomputer")
 * existiert schon.
 *
 * Aufruf: /c/xampp/php/php.exe pruefstand/testmitglied.php F:/Claude/contao-test
 */

use Contao\ManagerBundle\HttpKernel\ContaoKernel;
use Symfony\Component\Console\Input\ArgvInput;

$projekt = rtrim(str_replace('\\', '/', $argv[1] ?? ''), '/');

if ('' === $projekt || !is_file($projekt.'/vendor/autoload.php')) {
	fwrite(STDERR, "Aufruf: php testmitglied.php <contao-verzeichnis>\n");

	exit(1);
}

require $projekt.'/vendor/autoload.php';

$kernel = ContaoKernel::fromInput($projekt, new ArgvInput(array('x', '--env=prod')));
$kernel->boot();
$db = $kernel->getContainer()->get('database_connection');
$jetzt = time();

$artikel = $db->fetchOne("SELECT id FROM tl_article WHERE alias='bundle-schachcomputer'");

if (false === $artikel) {
	fwrite(STDERR, "Artikel „bundle-schachcomputer“ fehlt – zuerst contao-testseiten-bauen.php ausführen.\n");

	exit(1);
}

// „disable“ ist in Contao 4.13 ein char(1) (aktiv = ''), in Contao 5.7 ein
// tinyint (aktiv = 0); der Standardwert der Spalte verrät, welche Fassung läuft
$standard = $db->fetchOne("SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tl_member' AND COLUMN_NAME='disable'");
$aktiv = is_numeric($standard) ? 0 : '';

// Mitglied anlegen oder Passwort erneuern
$passwort = bin2hex(random_bytes(8));
$werte = array(
	'tstamp'    => $jetzt,
	'firstname' => 'Test',
	'lastname'  => 'Schachcomputer',
	'email'     => 'schachcomputer-test@example.org',
	'username'  => 'schachcomputer-test',
	'password'  => password_hash($passwort, PASSWORD_DEFAULT),
	'login'     => '1',
	'disable'   => $aktiv,
);
$mitglied = $db->fetchOne("SELECT id FROM tl_member WHERE username='schachcomputer-test'");

if (false === $mitglied) {
	$db->insert('tl_member', $werte + array('dateAdded' => $jetzt, 'groups' => serialize(array())));
	$mitglied = $db->lastInsertId();
} else {
	$db->update('tl_member', $werte, array('id' => $mitglied));
}

// Anmeldemodul im ersten Theme, als erstes Element auf der Testseite
$modul = $db->fetchOne("SELECT id FROM tl_module WHERE type='login' AND name='[Test] Schachcomputer Anmeldung'");

if (false === $modul) {
	$db->insert('tl_module', array(
		'pid'    => (int) $db->fetchOne('SELECT id FROM tl_theme ORDER BY id LIMIT 1'),
		'tstamp' => $jetzt,
		'name'   => '[Test] Schachcomputer Anmeldung',
		'type'   => 'login',
	));
	$modul = $db->lastInsertId();
}

if (false === $db->fetchOne("SELECT id FROM tl_content WHERE pid=? AND ptable='tl_article' AND type='module' AND module=?", array($artikel, $modul))) {
	$db->insert('tl_content', array(
		'pid'     => $artikel,
		'ptable'  => 'tl_article',
		'sorting' => 1,
		'tstamp'  => $jetzt,
		'type'    => 'module',
		'module'  => $modul,
	));
}

file_put_contents(__DIR__.'/zugang.local.md', "# Testzugang (nicht versioniert)\n\nInstallation: $projekt\nBenutzername: schachcomputer-test\nPasswort: $passwort\n");
printf("Mitglied %d und Anmeldemodul %d angelegt. Zugang: pruefstand/zugang.local.md\n", $mitglied, $modul);
