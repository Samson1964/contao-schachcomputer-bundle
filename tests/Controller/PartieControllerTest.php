<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Controller;

use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Schachbulle\ContaoSchachcomputerBundle\Controller\PartieController;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partiedienst;
use Schachbulle\ContaoSchachcomputerBundle\Statistik\Statistik;
use Schachbulle\ContaoSchachcomputerBundle\Tests\Datenbank;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Glicko2;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsdienst;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsrechner;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Prüft die JSON-Schnittstelle mit echten Diensten und SQLite.
 */
class PartieControllerTest extends TestCase
{
	private Connection $db;

	private TokenStorage $tokenStorage;

	private Session $sitzung;

	private int $blitz;

	/**
	 * Legt Datenbank, Bedenkzeit, leere Anmeldung und Sitzung an.
	 */
	protected function setUp(): void
	{
		$this->db = Datenbank::verbindung();
		$this->blitz = Datenbank::bedenkzeit($this->db);
		$this->tokenStorage = new TokenStorage();
		$this->sitzung = new Session(new MockArraySessionStorage());
	}

	/**
	 * Ohne application/json gibt es 415, bei fehlenden Angaben 400.
	 */
	public function testEingabePruefung(): void
	{
		$controller = $this->controller();

		$formular = Request::create('/_schachcomputer/start', 'POST', array('stufe' => 1500));
		$formular->setSession($this->sitzung);
		$this->assertSame(415, $controller->start($formular)->getStatusCode());

		$this->assertSame(400, $controller->start($this->post(array('stufe' => '1500')))->getStatusCode());
		$this->assertSame(400, $controller->zug($this->post(array('partie' => 1, 'zugnummer' => 0, 'zug' => 'e2e4', 'denkzeit' => 'viel')))->getStatusCode());
	}

	/**
	 * Ein Gast startet, zieht, sieht seine Partie im Stand und gibt auf.
	 */
	public function testGastSpielt(): void
	{
		$controller = $this->controller();

		$start = $this->daten($controller->start($this->post(array('bedenkzeit' => $this->blitz, 'stufe' => 1500, 'farbe' => 'w'))));
		$partie = $start['partie'];
		$this->assertSame('w', $partie['farbe']);
		$this->assertSame(1500, $partie['einstellungen']['uciElo']);
		$this->assertTrue($partie['spielerAmZug']);
		$this->assertFalse($partie['uhrLaeuft']);
		$this->assertSame(60000, $partie['ersterZugFrist']);

		$zug = $this->daten($controller->zug($this->post(array('partie' => $partie['id'], 'zugnummer' => 0, 'zug' => 'e2e4', 'denkzeit' => 800))));
		$this->assertSame(array('e2e4'), $zug['partie']['zuege']);
		$this->assertFalse($zug['partie']['spielerAmZug']);

		$stand = $this->daten($controller->stand($this->get()));
		$this->assertTrue($stand['gast']);
		$this->assertSame($partie['id'], $stand['partie']['id']);
		$this->assertSame(array('blitz', 'schnell', 'lang'), array_keys($stand['wertungen']));

		$ende = $this->daten($controller->aufgeben($this->post(array('partie' => $partie['id']))));
		$this->assertSame('beendet', $ende['partie']['status']);
		$this->assertSame(0.0, (float) $ende['partie']['punkte']);
		$this->assertTrue($ende['partie']['verrechnet']);

		$this->assertNull($this->daten($controller->stand($this->get()))['partie']);

		$beendet = $this->daten($controller->stand($this->get($partie['id'])))['partie'];
		$this->assertSame('aufgabe', $beendet['grund']);
		$this->assertNull($this->daten($controller->stand($this->get($partie['id'] + 99)))['partie']);
	}

	/**
	 * GET stand?partie=ID liefert eine existierende fremde Partie nicht aus,
	 * weder an einen Gast mit anderer Sitzung noch an ein anderes Mitglied.
	 */
	public function testStandZeigtKeineFremdePartie(): void
	{
		$this->anmelden(7);
		$fremd = $this->daten($this->controller()->start($this->post(array('bedenkzeit' => $this->blitz, 'stufe' => 1500, 'farbe' => 'w'))))['partie']['id'];
		$this->assertSame($fremd, $this->daten($this->controller()->stand($this->get($fremd)))['partie']['id']);

		// Anderes Mitglied
		$this->anmelden(8);
		$this->assertNull($this->daten($this->controller()->stand($this->get($fremd)))['partie']);

		// Gast mit eigener, neuer Sitzung
		$this->tokenStorage->setToken(null);
		$this->sitzung = new Session(new MockArraySessionStorage());
		$this->assertNull($this->daten($this->controller()->stand($this->get($fremd)))['partie']);

		// Ein Gast sieht auch die Partie eines anderen Gastes nicht
		$gastPartie = $this->daten($this->controller()->start($this->post(array('bedenkzeit' => $this->blitz, 'stufe' => 1500, 'farbe' => 'w'))))['partie']['id'];
		$this->sitzung = new Session(new MockArraySessionStorage());
		$this->assertNull($this->daten($this->controller()->stand($this->get($gastPartie)))['partie']);
	}

	/**
	 * Regelwidrige und veraltete Züge bekommen 422 bzw. 409.
	 */
	public function testFehlerstatus(): void
	{
		$controller = $this->controller();
		$id = $this->daten($controller->start($this->post(array('bedenkzeit' => $this->blitz, 'stufe' => 1500, 'farbe' => 'w'))))['partie']['id'];

		$regelwidrig = $controller->zug($this->post(array('partie' => $id, 'zugnummer' => 0, 'zug' => 'e2e5')));
		$this->assertSame(422, $regelwidrig->getStatusCode());
		$this->assertSame('ungueltig', $this->daten($regelwidrig)['fehler']);

		$veraltet = $controller->zug($this->post(array('partie' => $id, 'zugnummer' => 3, 'zug' => 'e2e4')));
		$this->assertSame(409, $veraltet->getStatusCode());

		$zweiteStart = $controller->start($this->post(array('bedenkzeit' => $this->blitz, 'stufe' => 1500, 'farbe' => 'w')));
		$this->assertSame(409, $zweiteStart->getStatusCode());
		$this->assertSame('laeuft_schon', $this->daten($zweiteStart)['fehler']);
	}

	/**
	 * Übungspartien speichern nur Mitglieder.
	 */
	public function testUebungNurFuerMitglieder(): void
	{
		$eingabe = array('stufe' => 900, 'farbe' => 'w', 'zuege' => array('e2e4', 'e7e5'), 'aufgegeben' => false);

		$this->assertSame(403, $this->controller()->uebung($this->post($eingabe))->getStatusCode());

		$this->anmelden(7);
		$antwort = $this->daten($this->controller()->uebung($this->post($eingabe)));
		$this->assertFalse($antwort['partie']['gewertet']);
		$this->assertSame('unbeendet', $antwort['partie']['grund']);
		$this->assertSame(7, (int) $this->db->fetchOne('SELECT memberId FROM tl_schachcomputer_partie'));
	}

	/**
	 * Ein Mitglied spielt unter seiner ID, nicht als Gast.
	 */
	public function testMitgliedSpielt(): void
	{
		$this->anmelden(7);
		$controller = $this->controller();

		$partie = $this->daten($controller->start($this->post(array('bedenkzeit' => $this->blitz, 'stufe' => 1500, 'farbe' => 'b'))))['partie'];

		$this->assertSame(7, (int) $this->db->fetchOne('SELECT memberId FROM tl_schachcomputer_partie WHERE id=?', array($partie['id'])));
		$this->assertFalse($this->daten($controller->stand($this->get()))['gast']);
		$this->assertNull($this->sitzung->get(PartieController::SITZUNG_GAST));
	}

	/**
	 * Nur der Stand mit ?aufruf=1 zählt als Aufruf.
	 */
	public function testAufrufWirdGezaehlt(): void
	{
		$controller = $this->controller();
		$anfrage = Request::create('/', 'GET', array('aufruf' => '1'));
		$anfrage->setSession($this->sitzung);

		$controller->stand($anfrage);
		$controller->stand($this->get());

		$zeile = $this->db->fetchAssociative('SELECT art, gast, anzahl FROM tl_schachcomputer_statistik');
		$this->assertSame(array('aufruf', '1', 1), array($zeile['art'], (string) $zeile['gast'], (int) $zeile['anzahl']));
	}

	/**
	 * Baut den Controller mit fester Uhr (T0).
	 *
	 * @return PartieController Der Controller
	 */
	private function controller(): PartieController
	{
		$wertungsdienst = new Wertungsdienst($this->db, new Wertungsrechner(new Glicko2()));
		$statistik = new Statistik($this->db, new NullLogger());

		return new class(new Partiedienst($this->db, $wertungsdienst, $statistik, new NullLogger()), $wertungsdienst, $this->tokenStorage, $statistik) extends PartieController {
			/**
			 * Feste Zeit statt der Systemuhr.
			 *
			 * @return int Der Startzeitpunkt der Tests
			 */
			protected function jetztMs(): int
			{
				return 1790000000000;
			}
		};
	}

	/**
	 * Meldet ein Mitglied im Frontend an.
	 *
	 * FrontendUser wird ohne Konstruktor erzeugt, weil der Konstruktor das
	 * Contao-Framework bräuchte; die ID steht über die magische Eigenschaft.
	 *
	 * @param int $id ID des Mitglieds
	 */
	private function anmelden(int $id): void
	{
		$mitglied = (new \ReflectionClass(FrontendUser::class))->newInstanceWithoutConstructor();
		$mitglied->id = $id;

		$this->tokenStorage->setToken(new UsernamePasswordToken($mitglied, 'contao_frontend', array('ROLE_MEMBER')));
	}

	/**
	 * Baut eine JSON-POST-Anfrage mit der Testsitzung.
	 *
	 * @param array<string, mixed> $daten Der JSON-Inhalt
	 *
	 * @return Request Die Anfrage
	 */
	private function post(array $daten): Request
	{
		$anfrage = Request::create('/', 'POST', array(), array(), array(), array('CONTENT_TYPE' => 'application/json'), json_encode($daten));
		$anfrage->setSession($this->sitzung);

		return $anfrage;
	}

	/**
	 * Baut eine GET-Anfrage mit der Testsitzung.
	 *
	 * @param int $partie ID für ?partie=, 0 für die laufende Partie
	 *
	 * @return Request Die Anfrage
	 */
	private function get(int $partie = 0): Request
	{
		$anfrage = Request::create('/', 'GET', $partie > 0 ? array('partie' => $partie) : array());
		$anfrage->setSession($this->sitzung);

		return $anfrage;
	}

	/**
	 * Liest den JSON-Inhalt einer Antwort.
	 *
	 * @param JsonResponse $antwort Die Antwort
	 *
	 * @return array<string, mixed> Die Daten
	 */
	private function daten(JsonResponse $antwort): array
	{
		return json_decode((string) $antwort->getContent(), true);
	}
}
