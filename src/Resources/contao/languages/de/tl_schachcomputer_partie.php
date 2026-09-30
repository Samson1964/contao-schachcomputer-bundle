<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

// Felder
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['memberId'] = array('Mitglied', 'Leer bei Gästen.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['gast'] = array('Gastkennung', 'Zufällige Kennung aus der Sitzung eines Gastes.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['gewertet'] = array('Gewertet', 'Nicht gewertet sind Übungspartien.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['bedenkzeit'] = array('Bedenkzeit', 'ID der gewählten Bedenkzeit.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['minuten'] = array('Minuten', 'Grundbedenkzeit beim Start.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['inkrement'] = array('Zeitgutschrift', 'Sekunden je Zug beim Start.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['klasse'] = array('Wertungsklasse', 'Leer bei Übungspartien.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['stufe'] = array('Stufe', 'Spielstufe der Engine.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['farbe'] = array('Farbe', 'Farbe des Spielers (w/b).');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['status'] = array('Status', 'Läuft, beendet oder abgebrochen.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['ergebnis'] = array('Ergebnis', 'Ergebnis in PGN-Schreibweise.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['grund'] = array('Grund', 'Wodurch die Partie endete.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['zuege'] = array('Züge', 'Alle Züge in UCI-Schreibweise.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['zeiten'] = array('Denkzeiten', 'Angerechnete Denkzeit je Spielerzug in Millisekunden.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['zugnummer'] = array('Halbzüge', 'Zahl der gespielten Halbzüge.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['restzeit'] = array('Restzeit', 'Restzeit des Spielers in Millisekunden.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['restzeitEngine'] = array('Restzeit des Computers', 'Restzeit von Stockfish in Millisekunden; -1 bei Partien ohne Uhr des Computers (vor Fassung 1.1.0).');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['remisAngebot'] = array('Remisangebot', 'Zahl der eigenen Züge beim letzten Remisangebot des Spielers; 0, solange er keins gemacht hat.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['uhrSeit'] = array('Uhr seit', 'Start der laufenden Uhr in Millisekunden.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['beginn'] = array('Beginn', 'Start der Partie.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['ende'] = array('Ende', 'Ende der Partie.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['verrechnet'] = array('Verrechnet', 'Ob die Partie in die Wertung eingegangen ist.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['wertungVorher'] = array('Wertung vorher', 'Wertung des Spielers vor der Partie.');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['wertungNachher'] = array('Wertung nachher', 'Wertung des Spielers nach der Partie.');

// Wertungsklassen, Status und Gründe
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['klassen'] = array
(
	'blitz'   => 'Blitz',
	'schnell' => 'Schnellschach',
	'lang'    => 'Langpartie',
);
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['stati'] = array
(
	'laeuft'      => 'läuft',
	'beendet'     => 'beendet',
	'abgebrochen' => 'abgebrochen',
);
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['gruende'] = array
(
	'matt'         => 'Matt',
	'patt'         => 'Patt',
	'material'     => 'Ungenügendes Material',
	'wiederholung' => 'Dreifache Wiederholung',
	'fuenfzig'     => '50-Züge-Regel',
	'zeit'         => 'Zeitüberschreitung',
	'aufgabe'      => 'Aufgabe',
	'einigung'     => 'Einigung',
	'verlassen'    => 'Verlassen',
	'abbruch'      => 'Abgebrochen',
	'erster_zug'   => 'Erster Zug nicht gemacht',
	'unbeendet'    => 'Nicht beendet',
);

// Operationen
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['delete'] = array('Löschen', 'Partie ID %s löschen');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['show'] = array('Details', 'Details der Partie ID %s anzeigen');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['pgn'] = array('PGN', 'Partie ID %s als PGN herunterladen');
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['nichtGefunden'] = 'Partie %d nicht gefunden.';
$GLOBALS['TL_LANG']['tl_schachcomputer_partie']['statistik'] = array('Statistik', 'Aufrufe und Partien auswerten');
