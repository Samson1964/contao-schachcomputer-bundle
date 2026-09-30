<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Partie;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Ablauf;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partie;
use Schachbulle\ContaoSchachcomputerBundle\Partie\PartieFehler;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Schiedsrichter;

/**
 * Prüft die Regeln einer Partie mit festen Zeitpunkten.
 */
class AblaufTest extends TestCase
{
	/**
	 * Startzeitpunkt aller Testpartien in Millisekunden.
	 */
	private const T0 = 1790000000000;

	/**
	 * 50 regelgerechte Halbzüge (Spanische Partie, Breyer-Verteidigung, dann
	 * ruhige Figurenzüge) ohne Partieende und ohne Stellungswiederholung.
	 *
	 * Die Remisregeln verlangen mindestens 19 eigene Züge, nach einer
	 * Ablehnung 24; auch PartiedienstTest, PartieControllerTest und PgnTest
	 * kommen damit bis zum Remisangebot.
	 */
	public const ZUEGE = 'e2e4 e7e5 g1f3 b8c6 f1b5 a7a6 b5a4 g8f6 e1g1 f8e7 f1e1 b7b5 a4b3 d7d6 c2c3 e8g8 h2h3 c6b8 d2d4 b8d7 b1d2 c8b7 b3c2 f8e8 d2f1 e7f8 f1g3 g7g6 a2a4 c7c5 d4d5 c5c4 c1g5 h7h6 g5e3 d7c5 d1d2 h6h5 e3g5 f8e7 g1h2 g8g7 e1f1 e8f8 a1e1 a8c8 d2e2 d8d7 e2d1 d7c7';

	/**
	 * Der Start übernimmt die Bedenkzeit und stellt die Uhr.
	 */
	public function testStarten(): void
	{
		$partie = $this->partie('b');

		$this->assertSame(Partie::LAEUFT, $partie->status);
		$this->assertSame(7, $partie->memberId);
		$this->assertSame(3, $partie->bedenkzeit);
		$this->assertSame('blitz', $partie->klasse);
		$this->assertSame(180000, $partie->restzeit);
		$this->assertSame(180000, $partie->restzeitEngine);
		$this->assertSame(0, $partie->remisAngebot);
		$this->assertSame(self::T0, $partie->uhrSeit);
		$this->assertSame(1790000000, $partie->beginn);
		$this->assertFalse($partie->spielerAmZug());
	}

	/**
	 * Unbekannte Stufen und Farben werden abgewiesen.
	 */
	public function testStartenMitUngueltigerStufe(): void
	{
		$this->expectExceptionObject(new PartieFehler(PartieFehler::UNGUELTIG));

		Ablauf::starten(7, '', $this->bedenkzeit(), 1550, 'w', self::T0);
	}

	/**
	 * Der erste eigene Zug kostet keine Zeit und bringt keine Gutschrift,
	 * ab dem zweiten wird abgezogen und gutgeschrieben.
	 */
	public function testUhrLaeuftAbDemZweitenZug(): void
	{
		$partie = $this->partie('w');

		Ablauf::zug($partie, 'e2e4', 0, 5000, self::T0 + 5000);
		$this->assertSame(array(0), $partie->zeiten);
		$this->assertSame(180000, $partie->restzeit);
		$this->assertFalse($partie->spielerAmZug());

		Ablauf::zug($partie, 'e7e5', 1, null, self::T0 + 7000);
		$this->assertTrue($partie->spielerAmZug());
		$this->assertSame(self::T0 + 7000, $partie->uhrSeit);

		// Server misst 10 s, der Browser meldet 9,5 s: angerechnet werden 9,5 s
		Ablauf::zug($partie, 'g1f3', 2, 9500, self::T0 + 17000);
		$this->assertSame(array(0, 9500), $partie->zeiten);
		$this->assertSame(180000 - 9500 + 2000, $partie->restzeit);
		$this->assertSame(array('e2e4', 'e7e5', 'g1f3'), $partie->zuege);
	}

	/**
	 * Eine veraltete Zugnummer wird abgewiesen, ohne etwas zu ändern.
	 */
	public function testVeralteteZugnummer(): void
	{
		$partie = $this->partie('w');
		Ablauf::zug($partie, 'e2e4', 0, null, self::T0 + 1000);

		try {
			Ablauf::zug($partie, 'e7e5', 0, null, self::T0 + 2000);
			$this->fail('Erwartet: PartieFehler');
		} catch (PartieFehler $fehler) {
			$this->assertSame(PartieFehler::VERALTET, $fehler->kennung());
			$this->assertSame(409, $fehler->status());
		}

		$this->assertSame(array('e2e4'), $partie->zuege);
	}

	/**
	 * Ein regelwidriger Zug wird abgewiesen.
	 */
	public function testRegelwidrigerZug(): void
	{
		$partie = $this->partie('w');

		try {
			Ablauf::zug($partie, 'e2e5', 0, null, self::T0 + 1000);
			$this->fail('Erwartet: PartieFehler');
		} catch (PartieFehler $fehler) {
			$this->assertSame(PartieFehler::UNGUELTIG, $fehler->kennung());
			$this->assertSame(422, $fehler->status());
		}

		$this->assertSame(array(), $partie->zuege);
		$this->assertSame(Partie::LAEUFT, $partie->status);
	}

	/**
	 * Kommt der zweite Zug nach Ablauf der Zeit, ist die Partie verloren.
	 */
	public function testZeitueberschreitungBeimZug(): void
	{
		$partie = $this->partie('w');
		Ablauf::zug($partie, 'e2e4', 0, null, self::T0 + 1000);
		Ablauf::zug($partie, 'e7e5', 1, null, self::T0 + 2000);

		Ablauf::zug($partie, 'g1f3', 2, 200000, self::T0 + 2000 + 200000);

		$this->assertSame(Partie::BEENDET, $partie->status);
		$this->assertSame('zeit', $partie->grund);
		$this->assertSame(Partie::SIEG_SCHWARZ, $partie->ergebnis);
		$this->assertSame(0, $partie->restzeit);
		$this->assertSame(array('e2e4', 'e7e5'), $partie->zuege);
	}

	/**
	 * Ohne Mattmaterial der Engine endet die Zeitüberschreitung remis.
	 */
	public function testZeitueberschreitungOhneMattmaterialDerEngine(): void
	{
		$partie = $this->partie('w');

		$this->assertSame(0.5, Ablauf::punkteBeiZeitablauf($partie, new Schiedsrichter('kn6/8/8/8/8/8/8/K6Q w - - 0 1')));
		$this->assertSame(0.0, Ablauf::punkteBeiZeitablauf($partie, new Schiedsrichter('kr6/8/8/8/8/8/8/K6Q w - - 0 1')));
	}

	/**
	 * Setzt der Spieler matt, gewinnt er.
	 */
	public function testSpielerSetztMatt(): void
	{
		$partie = $this->partie('b');
		Ablauf::zug($partie, 'f2f3', 0, null, self::T0 + 1000);
		Ablauf::zug($partie, 'e7e5', 1, null, self::T0 + 3000);
		Ablauf::zug($partie, 'g2g4', 2, null, self::T0 + 4000);
		Ablauf::zug($partie, 'd8h4', 3, 1000, self::T0 + 5000);

		$this->assertSame(Partie::BEENDET, $partie->status);
		$this->assertSame('matt', $partie->grund);
		$this->assertSame(Partie::SIEG_SCHWARZ, $partie->ergebnis);
		$this->assertSame(1.0, $partie->punkte());
	}

	/**
	 * Setzt die Engine matt, verliert der Spieler.
	 */
	public function testEngineSetztMatt(): void
	{
		$partie = $this->partie('w');
		Ablauf::zug($partie, 'f2f3', 0, null, self::T0 + 1000);
		Ablauf::zug($partie, 'e7e5', 1, null, self::T0 + 2000);
		Ablauf::zug($partie, 'g2g4', 2, 1000, self::T0 + 3000);
		Ablauf::zug($partie, 'd8h4', 3, null, self::T0 + 4000);

		$this->assertSame('matt', $partie->grund);
		$this->assertSame(0.0, $partie->punkte());
	}

	/**
	 * Bleibt der Engine-Zug nach dem ersten eigenen Zug aus, ist die Partie verloren.
	 */
	public function testEngineZugBleibtAus(): void
	{
		$partie = $this->partie('w');
		Ablauf::zug($partie, 'e2e4', 0, null, self::T0 + 1000);

		$this->assertFalse(Ablauf::pruefen($partie, self::T0 + 1000 + 60000));
		$this->assertTrue(Ablauf::pruefen($partie, self::T0 + 1000 + 60001));
		$this->assertSame(Partie::BEENDET, $partie->status);
		$this->assertSame('verlassen', $partie->grund);
		$this->assertSame(0.0, $partie->punkte());
	}

	/**
	 * Bleibt schon der erste Engine-Zug aus, wird ungewertet abgebrochen.
	 */
	public function testErsterEngineZugBleibtAus(): void
	{
		$partie = $this->partie('b');

		$this->assertTrue(Ablauf::pruefen($partie, self::T0 + 60001));
		$this->assertSame(Partie::ABGEBROCHEN, $partie->status);
		$this->assertSame('verlassen', $partie->grund);
		$this->assertNull($partie->punkte());
	}

	/**
	 * Wer nicht innerhalb der Frist den ersten Zug macht, dessen Partie wird
	 * ungewertet abgebrochen – beim Prüfen wie beim verspäteten Zug.
	 */
	public function testErsterZugZuSpaet(): void
	{
		$geprueft = $this->partie('w');
		$this->assertFalse(Ablauf::pruefen($geprueft, self::T0 + 61000));
		$this->assertTrue(Ablauf::pruefen($geprueft, self::T0 + 61001));
		$this->assertSame('erster_zug', $geprueft->grund);

		$gezogen = $this->partie('w');
		Ablauf::zug($gezogen, 'e2e4', 0, null, self::T0 + 61001);
		$this->assertSame(Partie::ABGEBROCHEN, $gezogen->status);
		$this->assertSame(array(), $gezogen->zuege);
	}

	/**
	 * Die Uhr läuft beim Prüfen erst nach Restzeit plus Ausgleich ab.
	 */
	public function testPruefenMitLaufenderUhr(): void
	{
		$partie = $this->partie('w');
		Ablauf::zug($partie, 'e2e4', 0, null, self::T0 + 1000);
		Ablauf::zug($partie, 'e7e5', 1, null, self::T0 + 2000);

		$this->assertFalse(Ablauf::pruefen($partie, self::T0 + 2000 + 181000));
		$this->assertTrue(Ablauf::pruefen($partie, self::T0 + 2000 + 181001));
		$this->assertSame('zeit', $partie->grund);
		$this->assertFalse(Ablauf::pruefen($partie, self::T0 + 999999));
	}

	/**
	 * Die Uhr des Computers verliert die vom Server gemessene Zeit zwischen
	 * Spielerzug und Computerzug, abzüglich bis zu 1 s Ausgleich für die
	 * Übertragung, und bekommt danach die Gutschrift; die Uhr des Spielers
	 * bleibt davon unberührt.
	 */
	public function testComputerUhrAbzugUndGutschrift(): void
	{
		$partie = $this->partie('w');
		Ablauf::zug($partie, 'e2e4', 0, 800, self::T0 + 1000);
		$this->assertSame(180000, $partie->restzeitEngine);

		// 4 s vom gespeicherten Spielerzug bis zum Eintreffen des Computerzugs,
		// davon 1 s Ausgleich: angerechnet werden 3 s
		Ablauf::zug($partie, 'e7e5', 1, null, self::T0 + 5000);
		$this->assertSame(180000 - 3000 + 2000, $partie->restzeitEngine);
		$this->assertSame(180000, $partie->restzeit);

		// Während der Spieler denkt, steht die Uhr des Computers
		Ablauf::zug($partie, 'g1f3', 2, 6000, self::T0 + 11000);
		$this->assertSame(179000, $partie->restzeitEngine);
		$this->assertSame(180000 - 6000 + 2000, $partie->restzeit);

		Ablauf::zug($partie, 'b8c6', 3, null, self::T0 + 16000);
		$this->assertSame(179000 - 4000 + 2000, $partie->restzeitEngine);
		$this->assertSame(array(0, 6000), $partie->zeiten);
		$this->assertSame(Partie::LAEUFT, $partie->status);
	}

	/**
	 * Der Ausgleich für die Übertragung beträgt höchstens 1 s: Unter einer
	 * Sekunde kostet der Computerzug nichts, die Gutschrift gibt es trotzdem.
	 */
	public function testComputerUhrMitAusgleichFuerDieUebertragung(): void
	{
		$schnell = $this->partie('b');
		Ablauf::zug($schnell, 'e2e4', 0, null, self::T0 + 600);
		$this->assertSame(180000 + 2000, $schnell->restzeitEngine);

		$langsam = $this->partie('b');
		Ablauf::zug($langsam, 'e2e4', 0, null, self::T0 + 30000);
		$this->assertSame(180000 - 29000 + 2000, $langsam->restzeitEngine);
	}

	/**
	 * Hat der Computer Weiß, läuft seine Uhr ab dem Start, und schon sein
	 * erster Zug wird abgezogen und gutgeschrieben (keine Sonderregel wie
	 * beim ersten Zug des Spielers).
	 */
	public function testComputerMitWeissZiehtAbDemErstenZugAb(): void
	{
		$partie = $this->partie('b');

		Ablauf::zug($partie, 'e2e4', 0, null, self::T0 + 2500);

		$this->assertSame(180000 - 1500 + 2000, $partie->restzeitEngine);
		$this->assertSame(180000, $partie->restzeit);
		$this->assertSame(self::T0 + 2500, $partie->uhrSeit);
	}

	/**
	 * Trifft der Computerzug nach Ablauf seiner Uhr ein, wird er nicht
	 * ausgeführt, und der Computer verliert auf Zeit. Wegen des Ausgleichs
	 * gilt ein Zug bis 1 s nach dem Ablauf noch als rechtzeitig.
	 */
	public function testComputerZugNachAblaufSeinerUhr(): void
	{
		$rechtzeitig = $this->partie('w');
		Ablauf::zug($rechtzeitig, 'e2e4', 0, null, self::T0 + 1000);
		$rechtzeitig->restzeitEngine = 5000;
		Ablauf::zug($rechtzeitig, 'e7e5', 1, null, self::T0 + 1000 + 6000);
		$this->assertSame(Partie::LAEUFT, $rechtzeitig->status);
		$this->assertSame(0 + 2000, $rechtzeitig->restzeitEngine);

		$zuSpaet = $this->partie('w');
		Ablauf::zug($zuSpaet, 'e2e4', 0, null, self::T0 + 1000);
		$zuSpaet->restzeitEngine = 5000;
		Ablauf::zug($zuSpaet, 'e7e5', 1, null, self::T0 + 1000 + 6001);

		$this->assertSame(Partie::BEENDET, $zuSpaet->status);
		$this->assertSame('zeit', $zuSpaet->grund);
		$this->assertSame(Partie::SIEG_WEISS, $zuSpaet->ergebnis);
		$this->assertSame(1.0, $zuSpaet->punkte());
		$this->assertSame(0, $zuSpaet->restzeitEngine);
		$this->assertSame(180000, $zuSpaet->restzeit);
		$this->assertSame(array('e2e4'), $zuSpaet->zuege);
		$this->assertSame(1790000007, $zuSpaet->ende);
	}

	/**
	 * Verliert der Computer auf Zeit, während der Spieler kein Mattmaterial
	 * mehr hat, endet die Partie remis – das Gegenstück zu
	 * punkteBeiZeitablauf().
	 */
	public function testComputerZeitverlustOhneMattmaterialDesSpielers(): void
	{
		// Der Spieler hat Weiß; Schwarz (Computer) hat noch eine Dame
		$partie = $this->partie('w');

		$this->assertSame(0.5, Ablauf::punkteBeiEngineZeitablauf($partie, new Schiedsrichter('kq6/8/8/8/8/8/8/KN6 w - - 0 1')));
		$this->assertSame(1.0, Ablauf::punkteBeiEngineZeitablauf($partie, new Schiedsrichter('kq6/8/8/8/8/8/8/KR6 w - - 0 1')));

		$schwarz = $this->partie('b');
		$this->assertSame(0.5, Ablauf::punkteBeiEngineZeitablauf($schwarz, new Schiedsrichter('k7/8/8/8/8/8/8/K6Q w - - 0 1')));
		$this->assertSame(1.0, Ablauf::punkteBeiEngineZeitablauf($schwarz, new Schiedsrichter('kr6/8/8/8/8/8/8/K6Q w - - 0 1')));
	}

	/**
	 * Bleibt der Computerzug ganz aus, zählt allein die Engine-Frist: Weder
	 * die Prüfung noch ein verspäteter Zug werten die abgelaufene Uhr des
	 * Computers als Zeitverlust, nach 60 s gilt die Partie als verlassen.
	 */
	public function testAusbleibenderComputerZugTrotzAbgelaufenerComputerUhr(): void
	{
		$geprueft = $this->partie('w');
		Ablauf::zug($geprueft, 'e2e4', 0, null, self::T0 + 1000);
		$geprueft->restzeitEngine = 10000;

		$this->assertFalse(Ablauf::pruefen($geprueft, self::T0 + 1000 + 30000));
		$this->assertSame(Partie::LAEUFT, $geprueft->status);
		$this->assertFalse(Ablauf::pruefen($geprueft, self::T0 + 1000 + 60000));
		$this->assertTrue(Ablauf::pruefen($geprueft, self::T0 + 1000 + 60001));
		$this->assertSame('verlassen', $geprueft->grund);
		$this->assertSame(0.0, $geprueft->punkte());

		// Kommt der Zug erst nach der Frist, bleibt es beim Verlassen
		$gezogen = $this->partie('w');
		Ablauf::zug($gezogen, 'e2e4', 0, null, self::T0 + 1000);
		$gezogen->restzeitEngine = 10000;
		Ablauf::zug($gezogen, 'e7e5', 1, null, self::T0 + 1000 + 60001);
		$this->assertSame('verlassen', $gezogen->grund);
		$this->assertSame(0.0, $gezogen->punkte());
	}

	/**
	 * Partien von vor Fassung 1.1.0 haben keine Uhr des Computers (-1):
	 * kein Abzug, keine Gutschrift, kein Zeitverlust des Computers.
	 */
	public function testAltpartieOhneComputerUhr(): void
	{
		$partie = $this->partie('w');
		$partie->restzeitEngine = -1;
		Ablauf::zug($partie, 'e2e4', 0, null, self::T0 + 1000);

		Ablauf::zug($partie, 'e7e5', 1, null, self::T0 + 1000 + 50000);

		$this->assertSame(Partie::LAEUFT, $partie->status);
		$this->assertSame(-1, $partie->restzeitEngine);
		$this->assertSame(array('e2e4', 'e7e5'), $partie->zuege);
	}

	/**
	 * Remis anbieten darf der Spieler frühestens vor seinem 20. Zug, also
	 * wenn er 19 Züge gemacht hat – mit Weiß wie mit Schwarz.
	 */
	public function testRemisErstAbDemZwanzigstenEigenenZug(): void
	{
		$weiss = $this->gespielt('w', 36);
		$this->assertSame(18, $weiss->eigeneZuege());
		$this->assertTrue($weiss->spielerAmZug());
		$this->assertFalse(Ablauf::remisErlaubt($weiss));
		$this->assertRemisNichtErlaubt($weiss, self::T0 + 36500);

		$weiss = $this->gespielt('w', 38);
		$this->assertSame(19, $weiss->eigeneZuege());
		$this->assertTrue(Ablauf::remisErlaubt($weiss));

		$schwarz = $this->gespielt('b', 37);
		$this->assertSame(18, $schwarz->eigeneZuege());
		$this->assertTrue($schwarz->spielerAmZug());
		$this->assertFalse(Ablauf::remisErlaubt($schwarz));

		$schwarz = $this->gespielt('b', 39);
		$this->assertSame(19, $schwarz->eigeneZuege());
		$this->assertTrue(Ablauf::remisErlaubt($schwarz));
	}

	/**
	 * Anbieten kann nur, wer am Zug ist.
	 */
	public function testRemisNurAmZug(): void
	{
		$partie = $this->gespielt('w', 37);
		$this->assertSame(19, $partie->eigeneZuege());
		$this->assertFalse($partie->spielerAmZug());
		$this->assertFalse(Ablauf::remisErlaubt($partie));

		$this->assertRemisNichtErlaubt($partie, self::T0 + 37500);
	}

	/**
	 * Übungspartien kennen kein Remisangebot (dort gibt es „Partie beenden“),
	 * beendete Partien auch nicht.
	 */
	public function testKeinRemisInUebungUndBeendeterPartie(): void
	{
		$this->assertFalse(Ablauf::remisErlaubt(Ablauf::uebung(7, 800, 'w', \array_slice(explode(' ', self::ZUEGE), 0, 38), false, self::T0)));

		// Auch eine laufende Partie nicht, sobald sie ungewertet ist
		$uebung = $this->gespielt('w', 38);
		$uebung->gewertet = false;
		$this->assertFalse(Ablauf::remisErlaubt($uebung));
		$this->assertRemisNichtErlaubt($uebung, self::T0 + 38500);

		$aufgegeben = $this->gespielt('w', 38);
		Ablauf::aufgeben($aufgegeben, self::T0 + 38500);
		$this->assertFalse(Ablauf::remisErlaubt($aufgegeben));

		$this->expectExceptionObject(new PartieFehler(PartieFehler::BEENDET));
		Ablauf::remis($aufgegeben, 38, true, self::T0 + 39000);
	}

	/**
	 * Nimmt Stockfish an, endet die Partie remis durch Einigung.
	 */
	public function testRemisAngenommen(): void
	{
		$partie = $this->gespielt('w', 38);

		Ablauf::remis($partie, 38, true, self::T0 + 38000 + 4500);

		$this->assertSame(Partie::BEENDET, $partie->status);
		$this->assertSame('einigung', $partie->grund);
		$this->assertSame(Partie::REMIS, $partie->ergebnis);
		$this->assertSame(0.5, $partie->punkte());
		$this->assertSame(1790000042, $partie->ende);
		$this->assertCount(38, $partie->zuege);
		$this->assertSame(19, $partie->remisAngebot);
		$this->assertFalse(Ablauf::remisErlaubt($partie));
	}

	/**
	 * Lehnt Stockfish ab, läuft die Partie weiter – die Uhr des Spielers
	 * ebenfalls. Das nächste Angebot ist erst fünf eigene Züge später möglich.
	 */
	public function testRemisAbgelehnt(): void
	{
		$partie = $this->gespielt('w', 38);
		$restzeit = $partie->restzeit;

		Ablauf::remis($partie, 38, false, self::T0 + 38000 + 1500);

		$this->assertSame(Partie::LAEUFT, $partie->status);
		$this->assertSame('', $partie->grund);
		$this->assertSame(19, $partie->remisAngebot);
		$this->assertSame($restzeit, $partie->restzeit);
		$this->assertSame(self::T0 + 38000, $partie->uhrSeit);
		$this->assertTrue($partie->spielerAmZug());
		$this->assertFalse(Ablauf::remisErlaubt($partie));
		$this->assertRemisNichtErlaubt($partie, self::T0 + 38000 + 2000);

		// Der nächste Zug kostet die ganze Zeit seit Beginn des Zuges, samt Prüfung des Angebots
		Ablauf::zug($partie, 'e3g5', 38, null, self::T0 + 38000 + 3000);
		$this->assertSame($restzeit - 3000 + 2000, $partie->restzeit);

		$this->weiterspielen($partie, 46);
		$this->assertSame(23, $partie->eigeneZuege());
		$this->assertFalse(Ablauf::remisErlaubt($partie));

		$this->weiterspielen($partie, 48);
		$this->assertSame(24, $partie->eigeneZuege());
		$this->assertTrue(Ablauf::remisErlaubt($partie));

		Ablauf::remis($partie, 48, false, $partie->uhrSeit + 500);
		$this->assertSame(24, $partie->remisAngebot);
		$this->assertFalse(Ablauf::remisErlaubt($partie));
	}

	/**
	 * Vor dem Angebot zählt die Uhr des Spielers: Ist sie samt Ausgleich
	 * abgelaufen, verliert er auf Zeit, statt dass über das Remis entschieden
	 * wird.
	 */
	public function testRemisNachAblaufDerUhr(): void
	{
		$rechtzeitig = $this->gespielt('w', 38);
		$rechtzeitig->restzeit = 5000;
		Ablauf::remis($rechtzeitig, 38, true, self::T0 + 38000 + 6000);
		$this->assertSame('einigung', $rechtzeitig->grund);

		$zuSpaet = $this->gespielt('w', 38);
		$zuSpaet->restzeit = 5000;
		Ablauf::remis($zuSpaet, 38, true, self::T0 + 38000 + 6001);

		$this->assertSame(Partie::BEENDET, $zuSpaet->status);
		$this->assertSame('zeit', $zuSpaet->grund);
		$this->assertSame(Partie::SIEG_SCHWARZ, $zuSpaet->ergebnis);
		$this->assertSame(0, $zuSpaet->restzeit);
		$this->assertSame(0, $zuSpaet->remisAngebot);
	}

	/**
	 * Ein Angebot zu einer veralteten Zugnummer wird abgewiesen, ohne etwas
	 * zu ändern.
	 */
	public function testRemisMitVeralteterZugnummer(): void
	{
		$partie = $this->gespielt('w', 38);
		$vorher = $partie->alsZeile();

		try {
			Ablauf::remis($partie, 37, true, self::T0 + 39000);
			$this->fail('Erwartet: PartieFehler');
		} catch (PartieFehler $fehler) {
			$this->assertSame(PartieFehler::VERALTET, $fehler->kennung());
			$this->assertSame(409, $fehler->status());
		}

		$this->assertSame($vorher, $partie->alsZeile());
	}

	/**
	 * Aufgeben ist jederzeit möglich und zählt als Niederlage.
	 */
	public function testAufgeben(): void
	{
		$partie = $this->partie('w');
		Ablauf::aufgeben($partie, self::T0 + 1000);

		$this->assertSame('aufgabe', $partie->grund);
		$this->assertSame(Partie::SIEG_SCHWARZ, $partie->ergebnis);

		$this->expectExceptionObject(new PartieFehler(PartieFehler::BEENDET));
		Ablauf::aufgeben($partie, self::T0 + 2000);
	}

	/**
	 * Abbrechen geht nur vor dem ersten eigenen Zug.
	 */
	public function testAbbrechen(): void
	{
		$vorher = $this->partie('b');
		Ablauf::zug($vorher, 'e2e4', 0, null, self::T0 + 1000);
		Ablauf::abbrechen($vorher, self::T0 + 2000);
		$this->assertSame(Partie::ABGEBROCHEN, $vorher->status);
		$this->assertSame(Partie::OFFEN, $vorher->ergebnis);

		$nachher = $this->partie('w');
		Ablauf::zug($nachher, 'e2e4', 0, null, self::T0 + 1000);
		$this->expectExceptionObject(new PartieFehler(PartieFehler::NICHT_ERLAUBT));
		Ablauf::abbrechen($nachher, self::T0 + 2000);
	}

	/**
	 * Die Übungspartie bestimmt ihr Ergebnis aus der Stellung.
	 */
	public function testUebungMitMatt(): void
	{
		$partie = Ablauf::uebung(7, 800, 'b', array('f2f3', 'e7e5', 'g2g4', 'd8h4'), false, self::T0);

		$this->assertFalse($partie->gewertet);
		$this->assertTrue($partie->verrechnet);
		$this->assertSame(Partie::BEENDET, $partie->status);
		$this->assertSame('matt', $partie->grund);
		$this->assertSame(Partie::SIEG_SCHWARZ, $partie->ergebnis);
	}

	/**
	 * Ohne Ende nach den Regeln zählt die Aufgabe, sonst bleibt das Ergebnis offen.
	 */
	public function testUebungAufgegebenOderUnbeendet(): void
	{
		$aufgegeben = Ablauf::uebung(7, 800, 'w', array('e2e4', 'e7e5'), true, self::T0);
		$this->assertSame('aufgabe', $aufgegeben->grund);
		$this->assertSame(Partie::SIEG_SCHWARZ, $aufgegeben->ergebnis);

		$offen = Ablauf::uebung(7, 800, 'w', array('e2e4', 'e7e5'), false, self::T0);
		$this->assertSame('unbeendet', $offen->grund);
		$this->assertSame(Partie::OFFEN, $offen->ergebnis);
	}

	/**
	 * Unsinnige Zuglisten werden abgewiesen.
	 *
	 * @dataProvider ungueltigeUebungen
	 *
	 * @param array<int, mixed> $zuege
	 */
	public function testUngueltigeUebung(array $zuege): void
	{
		$this->expectExceptionObject(new PartieFehler(PartieFehler::UNGUELTIG));

		Ablauf::uebung(7, 800, 'w', $zuege, false, self::T0);
	}

	/**
	 * Liefert ungültige Zuglisten.
	 *
	 * @return array<string, array{array<int, mixed>}>
	 */
	public function ungueltigeUebungen(): array
	{
		return array(
			'leer'          => array(array()),
			'regelwidrig'   => array(array('e2e4', 'e2e4')),
			'keine Zeichen' => array(array(42)),
		);
	}

	/**
	 * Eine Partie aus Datenbankzeile und zurück bleibt gleich.
	 */
	public function testZeileHinUndZurueck(): void
	{
		$partie = $this->partie('w');
		Ablauf::zug($partie, 'e2e4', 0, 1200, self::T0 + 1500);
		$partie->remisAngebot = 21;

		$zeile = array('id' => '5') + array_map('strval', $partie->alsZeile());
		$zurueck = Partie::ausZeile($zeile);

		$this->assertSame(5, $zurueck->id);
		$this->assertSame(array('e2e4'), $zurueck->zuege);
		$this->assertSame(array(0), $zurueck->zeiten);
		$this->assertSame($partie->uhrSeit, $zurueck->uhrSeit);
		$this->assertSame(180000, $zurueck->restzeitEngine);
		$this->assertSame(21, $zurueck->remisAngebot);
		$this->assertTrue($zurueck->gewertet);
		$this->assertSame(1, $partie->alsZeile()['zugnummer']);

		// Altpartien ohne Uhr des Computers behalten ihr Kennzeichen -1
		$this->assertSame(-1, Partie::ausZeile(array('restzeitEngine' => '-1') + $zeile)->restzeitEngine);
	}

	/**
	 * Legt eine Blitzpartie 3+2 gegen Stufe 1500 an.
	 *
	 * @param string $farbe Farbe des Spielers
	 *
	 * @return Partie Die laufende Partie
	 */
	private function partie(string $farbe): Partie
	{
		return Ablauf::starten(7, '', $this->bedenkzeit(), 1500, $farbe, self::T0);
	}

	/**
	 * Legt eine Partie an, die schon die ersten Halbzüge aus ZUEGE hinter
	 * sich hat.
	 *
	 * Die Züge werden gesetzt statt einzeln über zug() gespielt: Jeder Zug
	 * spielt die ganze Partie nach, 38 Halbzüge kosteten so fast eine halbe
	 * Sekunde je Test. Die Uhren stehen wie nach dem Start; uhrSeit liegt
	 * n Sekunden nach T0, als wäre jeder Halbzug 1 s nach dem vorigen gekommen.
	 *
	 * @param string $farbe     Farbe des Spielers
	 * @param int    $halbzuege Zahl der Halbzüge, höchstens 50
	 *
	 * @return Partie Die laufende Partie
	 */
	private function gespielt(string $farbe, int $halbzuege): Partie
	{
		$partie = $this->partie($farbe);
		$partie->zuege = \array_slice(explode(' ', self::ZUEGE), 0, $halbzuege);
		$partie->uhrSeit = self::T0 + $halbzuege * 1000;

		return $partie;
	}

	/**
	 * Spielt eine Partie mit den Zügen aus ZUEGE bis zur angegebenen Zahl von
	 * Halbzügen weiter, jeden Halbzug 1 s nach dem Start der laufenden Uhr.
	 *
	 * Die Spielerzüge melden keine Browsermessung; angerechnet wird also die
	 * volle Sekunde.
	 *
	 * @param Partie $partie Die laufende Partie; ihre bisherigen Züge müssen
	 *                       der Anfang von ZUEGE sein
	 * @param int    $bis    Zahl der Halbzüge danach, höchstens 50
	 *
	 * @return Partie Dieselbe Partie
	 */
	private function weiterspielen(Partie $partie, int $bis): Partie
	{
		$zuege = explode(' ', self::ZUEGE);

		for ($index = $partie->zugnummer(); $index < $bis; ++$index) {
			Ablauf::zug($partie, $zuege[$index], $index, null, $partie->uhrSeit + 1000);
		}

		return $partie;
	}

	/**
	 * Prüft, dass ein Remisangebot mit NICHT_ERLAUBT abgewiesen wird und die
	 * Partie dabei unverändert bleibt.
	 *
	 * @param Partie $partie  Die Partie; angeboten wird zu ihrer aktuellen Zugnummer
	 * @param int    $jetztMs Zeitpunkt des Angebots, vor Ablauf aller Fristen
	 */
	private function assertRemisNichtErlaubt(Partie $partie, int $jetztMs): void
	{
		$vorher = $partie->alsZeile();

		try {
			Ablauf::remis($partie, $partie->zugnummer(), true, $jetztMs);
			$this->fail('Erwartet: PartieFehler');
		} catch (PartieFehler $fehler) {
			$this->assertSame(PartieFehler::NICHT_ERLAUBT, $fehler->kennung());
			$this->assertSame(422, $fehler->status());
		}

		$this->assertSame($vorher, $partie->alsZeile());
	}

	/**
	 * Liefert eine Bedenkzeit 3+2 im Blitz.
	 *
	 * @return array{id: int, minuten: int, inkrement: int, klasse: string}
	 */
	private function bedenkzeit(): array
	{
		return array('id' => 3, 'minuten' => 3, 'inkrement' => 2, 'klasse' => 'blitz');
	}
}
