<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

// Felder
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['memberId'] = array('Mitglied', 'Das Mitglied aus der Mitgliederverwaltung.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['klasse'] = array('Wertungsklasse', 'Blitz, Schnellschach oder Langpartie.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['wertung'] = array('Wertung', 'Glicko-2-Wertungszahl nach der letzten Partie.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['abweichung'] = array('Abweichung', 'Unsicherheit der Wertung; über 110 gilt sie als vorläufig.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['volatilitaet'] = array('Volatilität', 'Wie stark die Leistung schwankt.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['partien'] = array('Partien', 'Zahl der gewerteten Partien.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['siege'] = array('Siege', 'Gewonnene Partien.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['remis'] = array('Remis', 'Unentschiedene Partien.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['niederlagen'] = array('Niederlagen', 'Verlorene Partien.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['hoechstwert'] = array('Höchstwert', 'Höchste gesicherte Wertung.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['hoechstwertDatum'] = array('Höchstwert am', 'Zeitpunkt des Höchstwerts.');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['letztePartie'] = array('Letzte Partie', 'Ende der letzten gewerteten Partie.');

// Wertungsklassen
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['klassen'] = array
(
	'blitz'   => 'Blitz',
	'schnell' => 'Schnellschach',
	'lang'    => 'Langpartie',
);

// Operationen
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['delete'] = array('Löschen', 'Wertung ID %s samt Verlauf löschen');
$GLOBALS['TL_LANG']['tl_schachcomputer_spieler']['show'] = array('Details', 'Details der Wertung ID %s anzeigen');
