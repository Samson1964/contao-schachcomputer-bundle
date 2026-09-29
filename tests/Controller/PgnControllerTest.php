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
use Schachbulle\ContaoSchachcomputerBundle\Controller\PgnController;
use Schachbulle\ContaoSchachcomputerBundle\Partie\Partiedienst;
use Schachbulle\ContaoSchachcomputerBundle\Partie\PgnExport;
use Schachbulle\ContaoSchachcomputerBundle\Tests\Datenbank;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Glicko2;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsdienst;
use Schachbulle\ContaoSchachcomputerBundle\Wertung\Wertungsrechner;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Prüft den PGN-Download: nur eigene Partien, nur für Mitglieder.
 */
class PgnControllerTest extends TestCase
{
	private Connection $db;

	private Partiedienst $partiedienst;

	private TokenStorage $tokenStorage;

	/**
	 * Legt Datenbank, Dienste und leere Anmeldung an.
	 */
	protected function setUp(): void
	{
		$this->db = Datenbank::verbindung();
		$this->partiedienst = new Partiedienst($this->db, new Wertungsdienst($this->db, new Wertungsrechner(new Glicko2())));
		$this->tokenStorage = new TokenStorage();
	}

	/**
	 * Gäste bekommen 403, fremde Partien 404, eigene die Datei.
	 */
	public function testNurEigenePartien(): void
	{
		$max = Datenbank::mitglied($this->db, 'Max', 'Mustermann');
		$eva = Datenbank::mitglied($this->db, 'Eva', 'Andere');
		$eigene = $this->partiedienst->uebungSpeichern($max, 800, 'w', array('e2e4'), true, 1790000000000);
		$fremde = $this->partiedienst->uebungSpeichern($eva, 800, 'w', array('d2d4'), true, 1790000000000);
		$controller = new PgnController($this->partiedienst, new PgnExport($this->db, $this->partiedienst), $this->tokenStorage);

		$this->assertSame(403, $controller->einzeln($this->anfrage($eigene->id))->getStatusCode());

		$this->anmelden($max);
		$this->assertSame(404, $controller->einzeln($this->anfrage($fremde->id))->getStatusCode());

		$antwort = $controller->einzeln($this->anfrage($eigene->id));
		$this->assertSame(200, $antwort->getStatusCode());
		$this->assertSame('application/x-chess-pgn; charset=utf-8', $antwort->headers->get('Content-Type'));
		$this->assertStringContainsString('attachment; filename=schachcomputer-'.$eigene->id.'.pgn', (string) $antwort->headers->get('Content-Disposition'));
		$this->assertStringContainsString('1. e4', (string) $antwort->getContent());

		$alle = (string) $controller->alle($this->anfrage(0))->getContent();
		$this->assertStringContainsString('1. e4', $alle);
		$this->assertStringNotContainsString('1. d4', $alle);
	}

	/**
	 * Baut eine Anfrage mit dem Routenattribut id.
	 *
	 * @param int $id ID der Partie
	 *
	 * @return Request Die Anfrage
	 */
	private function anfrage(int $id): Request
	{
		$anfrage = Request::create('https://example.org/_schachcomputer/pgn/'.$id);
		$anfrage->attributes->set('id', (string) $id);

		return $anfrage;
	}

	/**
	 * Meldet ein Mitglied an (siehe PartieControllerTest).
	 *
	 * @param int $id ID des Mitglieds
	 */
	private function anmelden(int $id): void
	{
		$mitglied = (new \ReflectionClass(FrontendUser::class))->newInstanceWithoutConstructor();
		$mitglied->id = $id;

		$this->tokenStorage->setToken(new UsernamePasswordToken($mitglied, 'contao_frontend', array('ROLE_MEMBER')));
	}
}
