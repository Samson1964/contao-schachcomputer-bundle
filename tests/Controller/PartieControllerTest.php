<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Tests\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Schachbulle\ContaoSchachcomputerBundle\Controller\PartieController;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partiedienst;
use Schachbulle\ContaoSchachcomputerBundle\Statistik\Statistik;
use Schachbulle\ContaoSchachcomputerBundle\Tests\Datenbank;
use Schachbulle\ContaoSchachcomputerBundle\Tests\Partie\AblaufTest;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Glicko2;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsdienst;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsrechner;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Prüft die JSON-Schnittstelle mit echten Diensten und SQLite.
 */
class PartieControllerTest extends TestCase
{
	/**
	 * Startzeitpunkt aller Tests in Millisekunden.
	 */
	private const T0 = 1790000000000;

	private Connection $db;

	private TokenStorage $tokenStorage;

	private Session $sitzung;

	private int $blitz;

	/**
	 * Zeitpunkt, den der Controller als „jetzt“ sieht; Tests stellen ihn vor.
	 */
	private int $jetzt = self::T0;

	/**
	 * Legt Datenbank, Bedenkzeit, leere Anmeldung und Sitzung an.
	 */
	protected function setUp(): void
	{
		$this->db = Datenbank::verbindung();
		$this->blitz = Datenbank::bedenkzeit($this->db);
		$this->tokenStorage = new TokenStorage();
		$this->sitzung = new Session(new MockArraySessionStorage());
		$this->jetzt = self::T0;
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
	 * partieDaten liefert beide Uhren: Die gerade laufende ist auf den
	 * Zeitpunkt der Antwort umgerechnet, die stehende zeigt den gespeicherten Wert.
	 */
	public function testBeideUhrenInDenPartiedaten(): void
	{
		$controller = $this->controller();

		$partie = $this->daten($controller->start($this->post(array('bedenkzeit' => $this->blitz, 'stufe' => 1500, 'farbe' => 'w'))))['partie'];
		$this->assertSame(180000, $partie['restzeitEngine']);
		$this->assertFalse($partie['engineUhrLaeuft']);

		$this->jetzt = self::T0 + 1000;
		$nachSpielerzug = $this->daten($controller->zug($this->post(array('partie' => $partie['id'], 'zugnummer' => 0, 'zug' => 'e2e4', 'denkzeit' => 800))))['partie'];
		$this->assertTrue($nachSpielerzug['engineUhrLaeuft']);
		$this->assertSame(180000, $nachSpielerzug['restzeitEngine']);

		// Der Computer rechnet: seine Uhr läuft, die des Spielers steht
		$this->jetzt = self::T0 + 3500;
		$rechnet = $this->daten($controller->stand($this->get($partie['id'])))['partie'];
		$this->assertTrue($rechnet['engineUhrLaeuft']);
		$this->assertSame(177500, $rechnet['restzeitEngine']);
		$this->assertFalse($rechnet['uhrLaeuft']);
		$this->assertSame(180000, $rechnet['restzeit']);

		// 4 s gemessen, abzüglich 1 s Ausgleich, plus 2 s Gutschrift
		$this->jetzt = self::T0 + 5000;
		$nachComputerzug = $this->daten($controller->zug($this->post(array('partie' => $partie['id'], 'zugnummer' => 1, 'zug' => 'e7e5', 'denkzeit' => null))))['partie'];
		$this->assertFalse($nachComputerzug['engineUhrLaeuft']);
		$this->assertSame(180000 - 3000 + 2000, $nachComputerzug['restzeitEngine']);
		$this->assertTrue($nachComputerzug['uhrLaeuft']);

		// Der Spieler denkt: seine Uhr läuft, die des Computers steht
		$this->jetzt = self::T0 + 7000;
		$denkt = $this->daten($controller->stand($this->get($partie['id'])))['partie'];
		$this->assertFalse($denkt['engineUhrLaeuft']);
		$this->assertSame(179000, $denkt['restzeitEngine']);
		$this->assertSame(178000, $denkt['restzeit']);

		$ende = $this->daten($controller->aufgeben($this->post(array('partie' => $partie['id']))))['partie'];
		$this->assertFalse($ende['engineUhrLaeuft']);
		$this->assertSame(179000, $ende['restzeitEngine']);
	}

	/**
	 * Hat der Computer Weiß, läuft seine Uhr ab dem Start; eine Altpartie
	 * ohne Uhr des Computers meldet -1 und nie eine laufende Computer-Uhr.
	 */
	public function testComputerUhrBeiWeissUndAltpartie(): void
	{
		$controller = $this->controller();

		$partie = $this->daten($controller->start($this->post(array('bedenkzeit' => $this->blitz, 'stufe' => 1500, 'farbe' => 'b'))))['partie'];
		$this->assertTrue($partie['engineUhrLaeuft']);
		$this->assertSame(180000, $partie['restzeitEngine']);

		$this->jetzt = self::T0 + 1500;
		$this->assertSame(178500, $this->daten($controller->stand($this->get($partie['id'])))['partie']['restzeitEngine']);

		$this->db->executeStatement('UPDATE tl_schachcomputer_partie SET restzeitEngine=-1 WHERE id=?', array($partie['id']));
		$alt = $this->daten($controller->stand($this->get($partie['id'])))['partie'];
		$this->assertFalse($alt['engineUhrLaeuft']);
		$this->assertSame(-1, $alt['restzeitEngine']);
	}

	/**
	 * Remis anbieten über die Schnittstelle: remisErlaubt in den
	 * Partiedaten, veraltete Zugnummer, Ablehnung mit weiterlaufender Uhr,
	 * zu frühes zweites Angebot und Annahme mit Verrechnung als Remis.
	 */
	public function testRemisAnbieten(): void
	{
		$controller = $this->controller();
		$partie = $this->daten($controller->start($this->post(array('bedenkzeit' => $this->blitz, 'stufe' => 1500, 'farbe' => 'w'))))['partie'];
		$this->assertFalse($partie['remisErlaubt']);

		$zuege = explode(' ', AblaufTest::ZUEGE);

		for ($index = 0; $index < 38; ++$index) {
			$this->jetzt = self::T0 + ($index + 1) * 1000;
			$partie = $this->daten($controller->zug($this->post(array('partie' => $partie['id'], 'zugnummer' => $index, 'zug' => $zuege[$index], 'denkzeit' => null))))['partie'];

			// Erst vor dem 20. eigenen Zug, und nie, solange der Computer am Zug ist
			$this->assertSame(37 === $index, $partie['remisErlaubt'], 'nach Halbzug '.($index + 1));
		}

		$this->jetzt = self::T0 + 39000;
		$veraltet = $controller->remis($this->post(array('partie' => $partie['id'], 'zugnummer' => 37, 'angenommen' => true)));
		$this->assertSame(409, $veraltet->getStatusCode());
		$this->assertSame('veraltet', $this->daten($veraltet)['fehler']);

		// 19 Züge je 1 s ohne Browsermessung: 180 s + 18 × (2 s Gutschrift − 1 s) = 198 s; seit 1 s läuft die Uhr
		$abgelehnt = $this->daten($controller->remis($this->post(array('partie' => $partie['id'], 'zugnummer' => 38, 'angenommen' => false))))['partie'];
		$this->assertSame('laeuft', $abgelehnt['status']);
		$this->assertTrue($abgelehnt['spielerAmZug']);
		$this->assertTrue($abgelehnt['uhrLaeuft']);
		$this->assertSame(197000, $abgelehnt['restzeit']);
		$this->assertFalse($abgelehnt['remisErlaubt']);

		$zweites = $controller->remis($this->post(array('partie' => $partie['id'], 'zugnummer' => 38, 'angenommen' => true)));
		$this->assertSame(422, $zweites->getStatusCode());
		$this->assertSame('nicht_erlaubt', $this->daten($zweites)['fehler']);

		// Fünf eigene Züge später ginge es wieder; hier abgekürzt über die Datenbank
		$this->db->executeStatement('UPDATE tl_schachcomputer_partie SET remisAngebot=14 WHERE id=?', array($partie['id']));
		$this->assertTrue($this->daten($controller->stand($this->get($partie['id'])))['partie']['remisErlaubt']);

		$this->jetzt = self::T0 + 40000;
		$ende = $this->daten($controller->remis($this->post(array('partie' => $partie['id'], 'zugnummer' => 38, 'angenommen' => true))))['partie'];
		$this->assertSame('beendet', $ende['status']);
		$this->assertSame('einigung', $ende['grund']);
		$this->assertSame('1/2-1/2', $ende['ergebnis']);
		$this->assertSame(0.5, $ende['punkte']);
		$this->assertTrue($ende['verrechnet']);
		$this->assertFalse($ende['remisErlaubt']);
	}

	/**
	 * Unvollständige Angaben zum Remisangebot ergeben 400, eine fremde
	 * Partie 404.
	 */
	public function testRemisEingabeUndFremdePartie(): void
	{
		$controller = $this->controller();

		$this->assertSame(400, $controller->remis($this->post(array('partie' => 1, 'zugnummer' => 38)))->getStatusCode());
		$this->assertSame(400, $controller->remis($this->post(array('partie' => 1, 'zugnummer' => 38, 'angenommen' => 'ja')))->getStatusCode());
		$this->assertSame(400, $controller->remis($this->post(array('partie' => '1', 'zugnummer' => 38, 'angenommen' => true)))->getStatusCode());
		$this->assertSame(400, $controller->remis($this->post(array('partie' => 1, 'angenommen' => false)))->getStatusCode());

		$this->anmelden(7);
		$id = $this->daten($controller->start($this->post(array('bedenkzeit' => $this->blitz, 'stufe' => 1500, 'farbe' => 'w'))))['partie']['id'];

		$this->anmelden(8);
		$fremd = $controller->remis($this->post(array('partie' => $id, 'zugnummer' => 0, 'angenommen' => true)));
		$this->assertSame(404, $fremd->getStatusCode());
		$this->assertSame('nicht_gefunden', $this->daten($fremd)['fehler']);
	}

	/**
	 * Die Route für das Remisangebot ist eingerichtet wie die übrigen
	 * POST-Routen und zeigt auf eine vorhandene Methode des Controllers.
	 */
	public function testRemisRoute(): void
	{
		$routen = (new YamlFileLoader(new FileLocator(__DIR__.'/../../src/Resources/config')))->load('routes.yaml');
		$route = $routen->get('schachcomputer_remis');

		$this->assertNotNull($route);
		$this->assertSame('/_schachcomputer/remis', $route->getPath());
		$this->assertSame(array('POST'), $route->getMethods());
		$this->assertSame(PartieController::class.'::remis', $route->getDefault('_controller'));
		$this->assertSame('frontend', $route->getDefault('_scope'));
		$this->assertFalse($route->getDefault('_token_check'));
		$this->assertTrue(method_exists(PartieController::class, 'remis'));
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
	 * Prüft, dass jede Aktion zuerst das Contao-Framework initialisiert.
	 *
	 * Die Routen sind statische Symfony-Routen und laufen am
	 * Contao-RouteProvider vorbei, der das sonst übernimmt. Ohne den Aufruf
	 * zählte die Statistik nach der Zeitzone aus php.ini statt nach Contao,
	 * siehe die Cronjobs (AufgabenTest::testCronjobsInitialisierenDasFramework).
	 * Die einzelnen Aufrufe dürfen mit einem Fehlerstatus enden (415/400/403)
	 * – geprüft wird nur, dass initialize() als Erstes läuft, nicht das
	 * Ergebnis der jeweiligen Aktion.
	 */
	public function testAktionenInitialisierenDasFramework(): void
	{
		$framework = $this->createMock(ContaoFramework::class);
		$framework->expects($this->exactly(7))->method('initialize');

		$controller = $this->controller($framework);

		$controller->stand($this->get());
		$controller->start($this->post(array()));
		$controller->zug($this->post(array()));
		$controller->aufgeben($this->post(array()));
		$controller->abbrechen($this->post(array()));
		$controller->remis($this->post(array()));
		$controller->uebung($this->post(array()));
	}

	/**
	 * Baut den Controller mit der Testuhr ($this->jetzt, anfangs T0).
	 *
	 * Der Controller liest die Zeit bei jeder Anfrage über eine Closure aus
	 * dem Test, damit ein Test sie zwischen zwei Anfragen vorstellen kann.
	 *
	 * @param ContaoFramework|null $framework Eigener Framework-Mock für Erwartungen,
	 *                                        sonst ein nachsichtiger Standard-Mock
	 *
	 * @return PartieController Der Controller
	 */
	private function controller(?ContaoFramework $framework = null): PartieController
	{
		$wertungsdienst = new Wertungsdienst($this->db, new Wertungsrechner(new Glicko2()));
		$statistik = new Statistik($this->db, new NullLogger());

		$controller = new class(new Partiedienst($this->db, $wertungsdienst, $statistik, new NullLogger()), $wertungsdienst, $this->tokenStorage, $statistik, $framework ?? $this->createMock(ContaoFramework::class)) extends PartieController {
			/**
			 * Liefert die Testzeit.
			 *
			 * @var \Closure(): int
			 */
			public \Closure $uhr;

			/**
			 * Testzeit statt der Systemuhr.
			 *
			 * @return int Der Zeitpunkt, den der Test gerade eingestellt hat
			 */
			protected function jetztMs(): int
			{
				return ($this->uhr)();
			}
		};
		$controller->uhr = fn (): int => $this->jetzt;

		return $controller;
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
