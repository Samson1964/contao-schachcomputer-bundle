<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Wertung;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partie;
use Schachbulle\ContaoSchachcomputerBundle\Tests\Datenbank;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Glicko2;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsdienst;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsrechner;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Prüft Speichern und einmaliges Verrechnen gegen eine SQLite-Datenbank.
 */
class WertungsdienstTest extends TestCase
{
	private Connection $db;

	private Wertungsdienst $dienst;

	/**
	 * Legt eine frische Datenbank und den Dienst an.
	 */
	protected function setUp(): void
	{
		$this->db = Datenbank::verbindung();
		$this->dienst = new Wertungsdienst($this->db, new Wertungsrechner(new Glicko2()));
	}

	/**
	 * Ein Sieg legt Spielerzeile und Verlauf an und markiert die Partie.
	 */
	public function testMitgliedGewinnt(): void
	{
		$partie = $this->beendetePartie(7, '', Partie::SIEG_WEISS);

		$this->assertTrue($this->dienst->verrechnen($partie, null));
		$this->assertTrue($partie->verrechnet);
		$this->assertSame(1500.0, $partie->wertungVorher);
		$this->assertGreaterThan(1500.0, $partie->wertungNachher);

		$spieler = $this->db->fetchAssociative('SELECT * FROM tl_schachcomputer_spieler WHERE memberId=7');
		$this->assertSame('blitz', $spieler['klasse']);
		$this->assertSame(1, (int) $spieler['partien']);
		$this->assertSame(1, (int) $spieler['siege']);
		$this->assertEqualsWithDelta($partie->wertungNachher, (float) $spieler['wertung'], 0.0001);

		$verlauf = $this->db->fetchAllAssociative('SELECT * FROM tl_schachcomputer_verlauf');
		$this->assertCount(1, $verlauf);
		$this->assertSame((int) $spieler['id'], (int) $verlauf[0]['pid']);
		$this->assertSame($partie->id, (int) $verlauf[0]['partie']);

		$zeile = $this->db->fetchAssociative('SELECT verrechnet, wertungNachher FROM tl_schachcomputer_partie WHERE id=?', array($partie->id));
		$this->assertSame(1, (int) $zeile['verrechnet']);
	}

	/**
	 * Dieselbe Partie zählt nur einmal, auch aus einem zweiten, veralteten Objekt.
	 */
	public function testNurEinmalVerrechnen(): void
	{
		$partie = $this->beendetePartie(7, '', Partie::SIEG_WEISS);
		$zweitesObjekt = clone $partie;

		$this->assertTrue($this->dienst->verrechnen($partie, null));
		$this->assertFalse($this->dienst->verrechnen($partie, null));
		$this->assertFalse($this->dienst->verrechnen($zweitesObjekt, null));
		$this->assertSame(1, (int) $this->db->fetchOne('SELECT partien FROM tl_schachcomputer_spieler WHERE memberId=7'));
		$this->assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_schachcomputer_verlauf'));
	}

	/**
	 * Ungewertete, laufende und abgebrochene Partien werden nicht verrechnet.
	 */
	public function testNichtsZuVerrechnen(): void
	{
		$uebung = $this->beendetePartie(7, '', Partie::SIEG_WEISS, false);
		$abgebrochen = $this->beendetePartie(7, '', Partie::OFFEN, true, Partie::ABGEBROCHEN);

		$this->assertFalse($this->dienst->verrechnen($uebung, null));
		$this->assertFalse($this->dienst->verrechnen($abgebrochen, null));
		$this->assertFalse($this->db->fetchOne('SELECT id FROM tl_schachcomputer_spieler'));
	}

	/**
	 * Gäste werden nur mit Sitzung verrechnet, und nur in der Sitzung.
	 */
	public function testGastNurInDerSitzung(): void
	{
		$sitzung = new Session(new MockArraySessionStorage());
		$partie = $this->beendetePartie(0, 'abc', Partie::REMIS);

		$this->assertFalse($this->dienst->verrechnen($partie, null));
		$this->assertTrue($this->dienst->verrechnen($partie, $sitzung));
		$this->assertSame(1, $this->dienst->stand(null, $sitzung, 'blitz')->remis);
		$this->assertFalse($this->db->fetchOne('SELECT id FROM tl_schachcomputer_spieler'));
		$this->assertFalse($this->db->fetchOne('SELECT id FROM tl_schachcomputer_verlauf'));
	}

	/**
	 * Vom Cron beendete Gastpartien werden beim nächsten Aufruf nachgetragen.
	 */
	public function testGastNachtragen(): void
	{
		$sitzung = new Session(new MockArraySessionStorage());
		$this->beendetePartie(0, 'abc', Partie::SIEG_WEISS);
		$this->beendetePartie(0, 'abc', Partie::SIEG_SCHWARZ);
		$this->beendetePartie(0, 'fremd', Partie::SIEG_WEISS);

		$this->assertSame(2, $this->dienst->gastNachtragen('abc', $sitzung));
		$this->assertSame(0, $this->dienst->gastNachtragen('abc', $sitzung));
		$this->assertSame(2, $this->dienst->stand(null, $sitzung, 'blitz')->partien);
	}

	/**
	 * Die Übersicht nennt jede Klasse mit Vorschlag; neue Spieler sind vorläufig.
	 */
	public function testUebersicht(): void
	{
		$this->dienst->verrechnen($this->beendetePartie(7, '', Partie::SIEG_WEISS), null);

		$uebersicht = $this->dienst->uebersicht(7, null, 1790000000);

		$this->assertSame(array('blitz', 'schnell', 'lang'), array_keys($uebersicht));
		$this->assertSame(1, $uebersicht['blitz']['partien']);
		$this->assertTrue($uebersicht['blitz']['vorlaeufig']);
		$this->assertGreaterThan(1500, $uebersicht['blitz']['wertung']);
		$this->assertSame(1500, $uebersicht['schnell']['wertung']);
		$this->assertSame(1500, $uebersicht['schnell']['vorschlag']);
	}

	/**
	 * Der Verlauf liefert die Einträge einer Klasse, älteste zuerst.
	 */
	public function testVerlauf(): void
	{
		$erste = $this->beendetePartie(7, '', Partie::SIEG_WEISS);
		$zweite = $this->beendetePartie(7, '', Partie::SIEG_SCHWARZ);
		$zweite->ende = 1790000500;
		$this->db->update('tl_schachcomputer_partie', array('ende' => 1790000500), array('id' => $zweite->id));
		$this->dienst->verrechnen($erste, null);
		$this->dienst->verrechnen($zweite, null);

		$verlauf = $this->dienst->verlauf(7, 'blitz');

		$this->assertCount(2, $verlauf);
		$this->assertSame(array(1790000000, 1790000500), array_column($verlauf, 'zeit'));
		$this->assertEqualsWithDelta($zweite->wertungNachher, $verlauf[1]['wertung'], 0.0001);
		$this->assertSame(array(), $this->dienst->verlauf(7, 'lang'));
	}

	/**
	 * Legt eine beendete Blitzpartie gegen Stufe 1500 an; der Spieler hat Weiß.
	 *
	 * @param int    $memberId ID des Mitglieds, 0 für Gäste
	 * @param string $gast     Gastkennung
	 * @param string $ergebnis PGN-Ergebnis
	 * @param bool   $gewertet Gewertet oder Übung
	 * @param string $status   Status der Partie
	 *
	 * @return Partie Die gespeicherte Partie mit ID
	 */
	private function beendetePartie(int $memberId, string $gast, string $ergebnis, bool $gewertet = true, string $status = Partie::BEENDET): Partie
	{
		$partie = new Partie();
		$partie->memberId = $memberId;
		$partie->gast = $gast;
		$partie->gewertet = $gewertet;
		$partie->klasse = 'blitz';
		$partie->stufe = 1500;
		$partie->farbe = 'w';
		$partie->status = $status;
		$partie->ergebnis = $ergebnis;
		$partie->grund = 'aufgabe';
		$partie->zuege = array('e2e4');
		$partie->ende = 1790000000;

		$this->db->insert('tl_schachcomputer_partie', $partie->alsZeile());
		$partie->id = (int) $this->db->lastInsertId();

		return $partie;
	}
}
