<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Partie;

/**
 * Ein Zug oder eine Aktion, die in der aktuellen Lage nicht erlaubt ist.
 *
 * Die Kennung geht als „fehler" an den Browser; der Controller leitet daraus
 * den HTTP-Status ab (siehe status()).
 */
final class PartieFehler extends \RuntimeException
{
	public const NICHT_GEFUNDEN = 'nicht_gefunden';

	public const BEENDET = 'beendet';

	public const VERALTET = 'veraltet';

	public const UNGUELTIG = 'ungueltig';

	public const NICHT_ERLAUBT = 'nicht_erlaubt';

	public const LAEUFT_SCHON = 'laeuft_schon';

	private string $kennung;

	/**
	 * Legt die Kennung fest; sie dient zugleich als Meldungstext.
	 *
	 * @param string $kennung Eine der Konstanten dieser Klasse
	 */
	public function __construct(string $kennung)
	{
		parent::__construct($kennung);

		$this->kennung = $kennung;
	}

	/**
	 * Liefert die Kennung für den Browser.
	 *
	 * @return string Eine der Konstanten dieser Klasse
	 */
	public function kennung(): string
	{
		return $this->kennung;
	}

	/**
	 * Übersetzt die Kennung in einen HTTP-Status.
	 *
	 * 409 heißt für den Browser: Stand neu laden, weil die Partie inzwischen
	 * weitergegangen oder beendet ist.
	 *
	 * @return int 404, 409 oder 422
	 */
	public function status(): int
	{
		switch ($this->kennung) {
			case self::NICHT_GEFUNDEN:
				return 404;

			case self::BEENDET:
			case self::VERALTET:
			case self::LAEUFT_SCHON:
				return 409;

			default:
				return 422;
		}
	}
}
