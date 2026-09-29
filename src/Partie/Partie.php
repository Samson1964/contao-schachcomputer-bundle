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
 * Eine Partie, wie sie in tl_schachcomputer_partie steht.
 *
 * Reiner Datenträger mit Umrechnung von und zu einer Datenbankzeile; die
 * Regeln stehen in Ablauf, das Speichern in Partiedienst. Zeitpunkte in
 * Sekunden (beginn, ende), Uhrzeiten in Millisekunden (restzeit, uhrSeit,
 * zeiten).
 *
 * restzeit ist die Restzeit des Spielers zum Zeitpunkt uhrSeit. Ist der
 * Spieler am Zug, läuft seine Uhr seit uhrSeit; ist die Engine am Zug,
 * steht sie, und uhrSeit markiert den Beginn der Engine-Frist.
 */
final class Partie
{
	public const LAEUFT = 'laeuft';

	public const BEENDET = 'beendet';

	public const ABGEBROCHEN = 'abgebrochen';

	public const SIEG_WEISS = '1-0';

	public const SIEG_SCHWARZ = '0-1';

	public const REMIS = '1/2-1/2';

	public const OFFEN = '*';

	public int $id = 0;

	/**
	 * ID aus tl_member, 0 bei Gästen.
	 */
	public int $memberId = 0;

	/**
	 * Gastkennung aus der Sitzung, leer bei Mitgliedern.
	 */
	public string $gast = '';

	public bool $gewertet = true;

	/**
	 * ID der Bedenkzeit aus tl_schachcomputer_bedenkzeit, 0 bei Übungspartien.
	 */
	public int $bedenkzeit = 0;

	public int $minuten = 0;

	/**
	 * Zeitgutschrift je Zug in Sekunden.
	 */
	public int $inkrement = 0;

	/**
	 * blitz, schnell oder lang; leer bei Übungspartien.
	 */
	public string $klasse = '';

	public int $stufe = 0;

	/**
	 * Farbe des Spielers: „w" oder „b".
	 */
	public string $farbe = 'w';

	public string $status = self::LAEUFT;

	/**
	 * 1-0, 0-1, 1/2-1/2 oder * (offen bzw. abgebrochen); leer, solange die
	 * Partie läuft.
	 */
	public string $ergebnis = '';

	/**
	 * matt, patt, material, wiederholung, fuenfzig, zeit, aufgabe,
	 * verlassen, abbruch, erster_zug oder unbeendet.
	 */
	public string $grund = '';

	/**
	 * @var array<int, string> Züge in UCI-Schreibweise
	 */
	public array $zuege = array();

	/**
	 * @var array<int, int> Angerechnete Denkzeit je Spielerzug in ms
	 */
	public array $zeiten = array();

	public int $restzeit = 0;

	public int $uhrSeit = 0;

	public int $beginn = 0;

	public int $ende = 0;

	public bool $verrechnet = false;

	public float $wertungVorher = 0.0;

	public float $wertungNachher = 0.0;

	/**
	 * Baut eine Partie aus einer Datenbankzeile.
	 *
	 * Werte werden ausdrücklich umgewandelt, weil MySQL und SQLite Zahlen
	 * teils als Zeichenketten liefern.
	 *
	 * @param array<string, mixed> $zeile Zeile aus tl_schachcomputer_partie
	 *
	 * @return self Die Partie
	 */
	public static function ausZeile(array $zeile): self
	{
		$partie = new self();
		$partie->id = (int) $zeile['id'];
		$partie->memberId = (int) $zeile['memberId'];
		$partie->gast = (string) $zeile['gast'];
		$partie->gewertet = (bool) (int) $zeile['gewertet'];
		$partie->bedenkzeit = (int) $zeile['bedenkzeit'];
		$partie->minuten = (int) $zeile['minuten'];
		$partie->inkrement = (int) $zeile['inkrement'];
		$partie->klasse = (string) $zeile['klasse'];
		$partie->stufe = (int) $zeile['stufe'];
		$partie->farbe = (string) $zeile['farbe'];
		$partie->status = (string) $zeile['status'];
		$partie->ergebnis = (string) $zeile['ergebnis'];
		$partie->grund = (string) $zeile['grund'];
		$partie->zuege = '' === trim((string) $zeile['zuege']) ? array() : explode(' ', trim((string) $zeile['zuege']));
		$zeiten = json_decode((string) $zeile['zeiten'], true);
		$partie->zeiten = \is_array($zeiten) ? array_map('intval', $zeiten) : array();
		$partie->restzeit = (int) $zeile['restzeit'];
		$partie->uhrSeit = (int) $zeile['uhrSeit'];
		$partie->beginn = (int) $zeile['beginn'];
		$partie->ende = (int) $zeile['ende'];
		$partie->verrechnet = (bool) (int) $zeile['verrechnet'];
		$partie->wertungVorher = (float) $zeile['wertungVorher'];
		$partie->wertungNachher = (float) $zeile['wertungNachher'];

		return $partie;
	}

	/**
	 * Wandelt die Partie in Spaltenwerte für INSERT und UPDATE.
	 *
	 * Die ID fehlt absichtlich; sie vergibt die Datenbank bzw. steht in der
	 * WHERE-Bedingung.
	 *
	 * @return array<string, int|float|string> Spalte => Wert
	 */
	public function alsZeile(): array
	{
		return array(
			'memberId'       => $this->memberId,
			'gast'           => $this->gast,
			'gewertet'       => $this->gewertet ? 1 : 0,
			'bedenkzeit'     => $this->bedenkzeit,
			'minuten'        => $this->minuten,
			'inkrement'      => $this->inkrement,
			'klasse'         => $this->klasse,
			'stufe'          => $this->stufe,
			'farbe'          => $this->farbe,
			'status'         => $this->status,
			'ergebnis'       => $this->ergebnis,
			'grund'          => $this->grund,
			'zuege'          => implode(' ', $this->zuege),
			'zeiten'         => json_encode(array_values($this->zeiten)),
			'zugnummer'      => $this->zugnummer(),
			'restzeit'       => $this->restzeit,
			'uhrSeit'        => $this->uhrSeit,
			'beginn'         => $this->beginn,
			'ende'           => $this->ende,
			'verrechnet'     => $this->verrechnet ? 1 : 0,
			'wertungVorher'  => $this->wertungVorher,
			'wertungNachher' => $this->wertungNachher,
		);
	}

	/**
	 * Zahl der gespielten Halbzüge; dient beim Speichern als Versionsnummer.
	 *
	 * @return int 0 vor dem ersten Zug
	 */
	public function zugnummer(): int
	{
		return \count($this->zuege);
	}

	/**
	 * Prüft, ob der Spieler am Zug ist.
	 *
	 * Ohne Brett berechenbar: Bei gerader Zahl von Halbzügen ist Weiß am Zug.
	 *
	 * @return bool true, wenn der Spieler ziehen muss; false bei der Engine
	 */
	public function spielerAmZug(): bool
	{
		return (0 === $this->zugnummer() % 2) === ('w' === $this->farbe);
	}

	/**
	 * Zahl der Züge, die der Spieler schon gemacht hat.
	 *
	 * @return int 0, solange er noch nicht gezogen hat
	 */
	public function eigeneZuege(): int
	{
		$halbzuege = $this->zugnummer();

		return 'w' === $this->farbe ? intdiv($halbzuege + 1, 2) : intdiv($halbzuege, 2);
	}

	/**
	 * Übersetzt das Ergebnis in Punkte aus Sicht des Spielers.
	 *
	 * @return float|null 1, 0,5 oder 0; null bei offener oder abgebrochener Partie
	 */
	public function punkte(): ?float
	{
		if (self::REMIS === $this->ergebnis) {
			return 0.5;
		}

		if (self::SIEG_WEISS === $this->ergebnis) {
			return 'w' === $this->farbe ? 1.0 : 0.0;
		}

		if (self::SIEG_SCHWARZ === $this->ergebnis) {
			return 'b' === $this->farbe ? 1.0 : 0.0;
		}

		return null;
	}

	/**
	 * Bildet das PGN-Ergebnis aus den Punkten des Spielers.
	 *
	 * @param float $punkte 1, 0,5 oder 0 aus Sicht des Spielers
	 *
	 * @return string 1-0, 0-1 oder 1/2-1/2
	 */
	public function ergebnisFuer(float $punkte): string
	{
		if (0.5 === $punkte) {
			return self::REMIS;
		}

		$weissGewinnt = ('w' === $this->farbe) === (1.0 === $punkte);

		return $weissGewinnt ? self::SIEG_WEISS : self::SIEG_SCHWARZ;
	}

	/**
	 * Liefert die Farbe der Engine.
	 *
	 * @return string „w" oder „b", immer die andere Farbe als die des Spielers
	 */
	public function engineFarbe(): string
	{
		return 'w' === $this->farbe ? 'b' : 'w';
	}
}
