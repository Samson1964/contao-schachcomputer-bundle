<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Partie;

use PChess\Chess\Board;
use PChess\Chess\Chess;
use PChess\Chess\Piece;

/**
 * Prüft Züge nach den Schachregeln und erkennt das Partieende.
 *
 * Hülle um p-chess/chess. Die Stellung wird nicht gespeichert, sondern aus
 * der Zugliste nachgespielt: Nur so kennt p-chess die Vorgeschichte für die
 * dreifache Wiederholung. Eine Partie mit 200 Halbzügen braucht dafür knapp
 * 80 ms (gemessen mit XAMPP-PHP 8.4).
 *
 * Eigenheit von p-chess: Die Wiederholungsprüfung zählt die Stellungen nach
 * jedem Zug, die Ausgangsstellung selbst also erst ab ihrer ersten
 * Wiederkehr. Eine Wiederholung, an der die Ausgangsstellung beteiligt ist,
 * wird dadurch einen Zyklus später erkannt als nach den FIDE-Regeln.
 */
final class Schiedsrichter
{
	private Chess $chess;

	/**
	 * @var array<int, string>
	 */
	private array $uci = array();

	/**
	 * @var array<int, string>
	 */
	private array $san = array();

	/**
	 * Beginnt eine Partie in der Grundstellung oder einer anderen Stellung.
	 *
	 * @param string $fen Ausgangsstellung; Partien des Bundles beginnen immer
	 *                    in der Grundstellung, andere Stellungen dienen Tests
	 *
	 * @throws \InvalidArgumentException Bei einer ungültigen FEN
	 */
	public function __construct(string $fen = Board::DEFAULT_POSITION)
	{
		$this->chess = new Chess($fen);
	}

	/**
	 * Spielt eine Zugliste von der Ausgangsstellung an nach.
	 *
	 * @param array<int, string> $zuege Züge in UCI-Schreibweise (e2e4, e7e8q)
	 * @param string             $fen   Ausgangsstellung
	 *
	 * @throws \InvalidArgumentException Beim ersten ungültigen Zug, mit dessen
	 *                                   Nummer in der Meldung
	 *
	 * @return self Der Schiedsrichter in der Stellung nach dem letzten Zug
	 */
	public static function nachspielen(array $zuege, string $fen = Board::DEFAULT_POSITION): self
	{
		$schiedsrichter = new self($fen);

		foreach (array_values($zuege) as $index => $zug) {
			if (!$schiedsrichter->ziehen((string) $zug)) {
				throw new \InvalidArgumentException(sprintf('Ungültiger Zug „%s" als %d. Halbzug.', $zug, $index + 1));
			}
		}

		return $schiedsrichter;
	}

	/**
	 * Prüft die Schreibweise eines UCI-Zugs, ohne die Stellung zu beachten.
	 *
	 * @param string $zug Der Zug, etwa „e2e4" oder „b7a8q"
	 *
	 * @return bool true bei vier Zeichen Feld-zu-Feld plus optional q, r, b oder n
	 */
	public static function istUci(string $zug): bool
	{
		return 1 === preg_match('/^[a-h][1-8][a-h][1-8][qrbn]?$/', $zug);
	}

	/**
	 * Führt einen Zug aus, wenn er erlaubt ist.
	 *
	 * Abgelehnt werden: falsche Schreibweise, regelwidrige Züge, Züge nach
	 * dem Partieende, eine Umwandlung ohne Figur (p-chess verlangt sie) und
	 * eine Figurangabe bei einem gewöhnlichen Zug (die p-chess sonst
	 * stillschweigend überginge, sodass „e2e4q" als e4 durchkäme).
	 *
	 * @param string $zug Der Zug in UCI-Schreibweise
	 *
	 * @return bool true, wenn der Zug ausgeführt wurde; bei false bleibt die
	 *              Stellung unverändert
	 */
	public function ziehen(string $zug): bool
	{
		if (!self::istUci($zug) || null !== $this->ende()) {
			return false;
		}

		$ausgefuehrt = $this->chess->move(array(
			'from'      => substr($zug, 0, 2),
			'to'        => substr($zug, 2, 2),
			'promotion' => 5 === \strlen($zug) ? $zug[4] : null,
		));

		if (null === $ausgefuehrt) {
			return false;
		}

		if (5 === \strlen($zug) && null === $ausgefuehrt->promotion) {
			$this->chess->undo();

			return false;
		}

		$this->uci[] = $zug;
		$this->san[] = (string) $ausgefuehrt->san;

		return true;
	}

	/**
	 * Liefert die Seite am Zug.
	 *
	 * @return string „w" für Weiß, „b" für Schwarz
	 */
	public function amZug(): string
	{
		return $this->chess->turn;
	}

	/**
	 * Erkennt das Ende der Partie nach den Regeln.
	 *
	 * Wiederholung und 50-Züge-Regel beenden die Partie selbsttätig, ohne
	 * Antrag. Bei „matt" hat die Seite verloren, die am Zug ist.
	 *
	 * @return string|null „matt", „patt", „material", „wiederholung" oder
	 *                     „fuenfzig"; null, solange die Partie läuft
	 */
	public function ende(): ?string
	{
		if ($this->chess->inCheckmate()) {
			return 'matt';
		}

		if ($this->chess->inStalemate()) {
			return 'patt';
		}

		if ($this->chess->insufficientMaterial()) {
			return 'material';
		}

		if ($this->chess->inThreefoldRepetition()) {
			return 'wiederholung';
		}

		if ($this->chess->halfMovesExceeded()) {
			return 'fuenfzig';
		}

		return null;
	}

	/**
	 * Prüft, ob eine Seite noch mattsetzen könnte.
	 *
	 * Gebraucht bei Zeitüberschreitung: Hat die Engine kein Mattmaterial,
	 * endet die Partie remis. Vereinfachte Regel wie bei Lichess: Nur König,
	 * König und Springer oder König und Läufer reichen nicht. Die seltenen
	 * Hilfsmatt-Fälle der FIDE-Regeln (etwa König und Springer gegen einen
	 * zugestellten König) werten damit zugunsten des Spielers als Remis.
	 *
	 * @param string $farbe „w" oder „b"
	 *
	 * @return bool true, wenn die Seite mehr als einen einzelnen Leichtfigur-
	 *              oder gar keinen Stein neben dem König hat
	 */
	public function mattmaterial(string $farbe): bool
	{
		$steine = array();

		foreach (array_keys(Board::SQUARES) as $feld) {
			$figur = $this->chess->get($feld);

			if (null !== $figur && $figur->getColor() === $farbe && !$figur->isKing()) {
				$steine[] = $figur->getType();
			}
		}

		if (array() === $steine) {
			return false;
		}

		return !(1 === \count($steine) && \in_array($steine[0], array(Piece::KNIGHT, Piece::BISHOP), true));
	}

	/**
	 * Liefert alle ausgeführten Züge in UCI-Schreibweise.
	 *
	 * @return array<int, string> In Zugreihenfolge
	 */
	public function uci(): array
	{
		return $this->uci;
	}

	/**
	 * Liefert alle ausgeführten Züge in Kurzschreibweise (SAN) für die PGN.
	 *
	 * @return array<int, string> Etwa „e4", „Nf3", „bxa8=Q+"
	 */
	public function san(): array
	{
		return $this->san;
	}

	/**
	 * Liefert die aktuelle Stellung.
	 *
	 * @return string Die FEN der Stellung nach dem letzten Zug
	 */
	public function fen(): string
	{
		return $this->chess->fen();
	}
}
