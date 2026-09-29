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

// Modul „Rangliste" (%s wird ersetzt)
$GLOBALS['TL_LANG']['MSC']['schachcomputer_rangliste'] = array
(
	'platz'        => 'Platz',
	'name'         => 'Name',
	'wertung'      => 'Wertung',
	'hoechstwert'  => 'Höchstwert',
	'partien'      => 'Partien',
	'datum'        => 'Erreicht am',
	'veraenderung' => 'Plätze / Punkte',
	'neu'          => 'neu',
	'leer'         => 'In dieser Liste steht noch niemand. Aufgenommen wird, wer eine gesicherte Wertung hat.',
	'monat'        => 'Monat',
	'anzeigen'     => 'Anzeigen',
	'stand'        => 'Stand am 1. %s',
);

// Modul „Eigene Partien"
$GLOBALS['TL_LANG']['MSC']['schachcomputer_partien'] = array
(
	'nurMitglieder' => 'Melde dich an, um deine Partien zu sehen.',
	'leer'          => 'Du hast noch keine Partie beendet.',
	'datum'         => 'Datum',
	'art'           => 'Bedenkzeit',
	'uebung'        => 'Übung',
	'farbe'         => 'Farbe',
	'weiss'         => 'Weiß',
	'schwarz'       => 'Schwarz',
	'stufe'         => 'Stufe',
	'ergebnis'      => 'Ergebnis',
	'gewonnen'      => 'Gewonnen',
	'verloren'      => 'Verloren',
	'remis'         => 'Remis',
	'offen'         => 'Offen',
	'wertung'       => 'Wertung',
	'pgn'           => 'PGN',
	'allePgn'       => 'Alle Partien als PGN herunterladen',
);

// Modul „Wertungsverlauf" (%s wird ersetzt: Klasse, Wertung, Partien)
$GLOBALS['TL_LANG']['MSC']['schachcomputer_verlauf'] = array
(
	'nurMitglieder' => 'Melde dich an, um deinen Wertungsverlauf zu sehen.',
	'leer'          => 'Du hast noch keine gewertete Partie beendet.',
	'beschriftung'  => '%s: %s nach %s Partien',
	'kurve'         => 'Wertungsverlauf %s',
);

// Backend-Statistik (do=schachcomputer_partien&key=statistik; %s wird ersetzt)
$GLOBALS['TL_LANG']['MSC']['schachcomputer_statistik'] = array
(
	'zurueckModul'      => 'Zurück',
	'ueberschrift'      => 'Statistik der Aufrufe und Partien',
	'zeitraumTitel'     => 'Zeitraum',
	'ebene_tag'         => 'Tag',
	'ebene_monat'       => 'Monat',
	'ebene_jahr'        => 'Jahr',
	'zurueck'           => 'zurück',
	'vor'               => 'vor',
	'heute'             => 'bis heute',
	'bestand'           => '%s veröffentlichte Bedenkzeiten · %s Mitglieder mit Wertung · %s neue Spieler im Zeitraum',
	'keineDaten'        => 'Für diesen Zeitraum ist nichts gezählt. Mit „zurück“ lässt sich ein früherer Zeitraum ansteuern; über die Knöpfe oben wird aus dem Tag ein ganzer Monat oder ein ganzes Jahr.',
	'art_aufruf'        => 'Aufrufe',
	'art_gestartet'     => 'gewertete Partien begonnen',
	'art_beendet'       => 'gewertete Partien beendet',
	'art_abgebrochen'   => 'abgebrochen',
	'art_uebung'        => 'Übungspartien gespeichert',
	'davon'             => '%s Mitglieder · %s Gäste',
	'quote'             => 'Punktquote',
	'ergebnisse'        => '%s gewonnen · %s remis · %s verloren',
	'diagrammGestartet' => 'Aufrufe und begonnene Partien',
	'diagrammGewonnen'  => 'Beendete und gewonnene Partien',
	'legendeGestartet'  => array('begonnen', 'aufgerufen'),
	'legendeGewonnen'   => array('gewonnen', 'beendet'),
	'bedenkzeiten'      => 'Meistgespielte Bedenkzeiten (Mitglieder)',
	'aktivste'          => 'Aktivste Mitglieder',
	'keineMitglieder'   => 'In diesem Zeitraum haben keine Mitglieder eine gewertete Partie beendet.',
	'platz'             => 'Platz',
	'partien'           => 'Partien',
	'bedenkzeit'        => 'Bedenkzeit',
	'stufe'             => 'Ø Stufe',
	'punkte'            => 'Punkte',
	'name'              => 'Name',
	'besteWertung'      => 'Beste Wertung',
	'hinweis'           => 'Gezählt wird ab der Einführung der Statistik: Aufrufe beim Laden der Seite mit dem Modul „Spielen“, dazu Beginn und Ende jeder gewerteten Partie sowie gespeicherte Übungspartien. Übungspartien von Gästen laufen nur im Browser und werden nicht erfasst. Die Tabellen beruhen auf den Partien der Mitglieder; Gastpartien werden nach einem Tag gelöscht.',
);
