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
 * Wer spielt: ein Mitglied (ID aus tl_member) oder ein Gast (Kennung aus
 * der Sitzung). Entscheidet, welche Partien jemand sehen und ziehen darf.
 */
final class Spieler
{
	private ?int $memberId;

	private string $gast;

	/**
	 * Legt die Kennung fest; von außen über mitglied() oder gast().
	 *
	 * @param int|null $memberId ID aus tl_member, null bei Gästen
	 * @param string   $gast     Gastkennung, leer bei Mitgliedern
	 */
	private function __construct(?int $memberId, string $gast)
	{
		$this->memberId = $memberId;
		$this->gast = $gast;
	}

	/**
	 * Ein angemeldetes Mitglied.
	 *
	 * @param int $memberId ID aus tl_member, größer als 0
	 *
	 * @return self Der Spieler
	 */
	public static function mitglied(int $memberId): self
	{
		if ($memberId <= 0) {
			throw new \InvalidArgumentException('Ein Mitglied braucht eine ID größer als 0.');
		}

		return new self($memberId, '');
	}

	/**
	 * Ein Gast mit der Kennung aus seiner Sitzung.
	 *
	 * @param string $kennung Zufällige, nicht leere Kennung
	 *
	 * @return self Der Spieler
	 */
	public static function gast(string $kennung): self
	{
		if ('' === $kennung) {
			throw new \InvalidArgumentException('Ein Gast braucht eine Kennung.');
		}

		return new self(null, $kennung);
	}

	/**
	 * Prüft, ob es ein Gast ist.
	 *
	 * @return bool true bei Gästen
	 */
	public function istGast(): bool
	{
		return null === $this->memberId;
	}

	/**
	 * Liefert die Mitglieds-ID.
	 *
	 * @return int|null Die ID, oder null bei Gästen
	 */
	public function memberId(): ?int
	{
		return $this->memberId;
	}

	/**
	 * Liefert die Gastkennung.
	 *
	 * @return string Die Kennung, leer bei Mitgliedern
	 */
	public function gastkennung(): string
	{
		return $this->gast;
	}

	/**
	 * Prüft, ob eine Partie diesem Spieler gehört.
	 *
	 * @param Partie $partie Die Partie
	 *
	 * @return bool true, wenn Mitglieds-ID bzw. Gastkennung übereinstimmen
	 */
	public function besitzt(Partie $partie): bool
	{
		if ($this->istGast()) {
			return 0 === $partie->memberId && hash_equals($partie->gast, $this->gast);
		}

		return $partie->memberId === $this->memberId;
	}
}
