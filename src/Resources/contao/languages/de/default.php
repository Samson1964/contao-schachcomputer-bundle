<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

// Modul „Spielen": Texte für Template und spielen.js (%s wird ersetzt)
$GLOBALS['TL_LANG']['MSC']['schachcomputer'] = array
(
	'brett'             => 'Schachbrett',
	'uhr'               => 'Deine Restzeit',
	'laden'             => 'Wird geladen …',
	'fehler'            => 'Das hat nicht geklappt. Bitte lade die Seite neu.',
	'deineWertung'      => 'Deine Wertung',
	'bedenkzeit'        => 'Bedenkzeit',
	'stufe'             => 'Stärke des Computers',
	'farbe'             => 'Deine Farbe',
	'weiss'             => 'Weiß',
	'schwarz'           => 'Schwarz',
	'zufall'            => 'Zufall',
	'gewertetStarten'   => 'Gewertete Partie starten',
	'uebungStarten'     => 'Übungspartie',
	'keineBedenkzeit'   => 'Zurzeit sind keine Bedenkzeiten für gewertete Partien freigegeben.',
	'gastHinweis'       => 'Als Gast wird deine Wertung nur für diesen Besuch gespeichert. Melde dich an, um sie dauerhaft zu behalten und in den Ranglisten zu erscheinen.',
	'skala'             => 'Die Stärke des Computers beruht auf der Eichung von Stockfish an der CCRL-Blitzliste. Das sind keine DWZ-Werte: Stockfish mit 1500 spielt stärker als ein Vereinsspieler mit DWZ 1500. Die Stufen unter 1400 sind geschätzt.',
	'gegner'            => 'Stockfish, Stufe %s',
	'uebung'            => 'Übungspartie',
	'spieler'           => 'Spieler',
	'pgnEvent'          => 'Partie gegen Stockfish',
	'amZug'             => 'Du bist am Zug.',
	'ersterZug'         => 'Mach deinen ersten Zug innerhalb von %s Sekunden, sonst wird die Partie abgebrochen.',
	'engineDenkt'       => 'Der Computer denkt nach …',
	'gewonnen'          => 'Gewonnen',
	'verloren'          => 'Verloren',
	'remis'             => 'Remis',
	'unbeendet'         => 'Partie beendet.',
	'abgebrochen'       => 'Die Partie wurde ungewertet abgebrochen.',
	'wertungAenderung'  => 'Deine Wertung: %s → %s.',
	'abbrechen'         => 'Abbrechen',
	'aufgeben'          => 'Aufgeben',
	'aufgebenFrage'     => 'Willst du wirklich aufgeben?',
	'zuruecknehmen'     => 'Zug zurücknehmen',
	'beenden'           => 'Partie beenden',
	'pgnKopieren'       => 'PGN kopieren',
	'pgnKopiert'        => 'Die PGN liegt in der Zwischenablage.',
	'neuePartie'        => 'Neue Partie',
	'uebungGespeichert' => 'Die Partie steht jetzt unter „Eigene Partien".',
	'klassen'           => array
	(
		'blitz'   => 'Blitz',
		'schnell' => 'Schnellschach',
		'lang'    => 'Langpartie',
	),
	'gruende'           => array
	(
		'matt'         => 'Matt',
		'patt'         => 'Patt',
		'material'     => 'Ungenügendes Material',
		'wiederholung' => 'Dreifache Wiederholung',
		'fuenfzig'     => '50-Züge-Regel',
		'zeit'         => 'Zeitüberschreitung',
		'aufgabe'      => 'Aufgabe',
		'verlassen'    => 'Partie verlassen',
		'abbruch'      => 'Abgebrochen',
		'erster_zug'   => 'Erster Zug nicht gemacht',
		'unbeendet'    => 'Nicht beendet',
	),
);
