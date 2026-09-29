<?php

declare(strict_types=1);

/*
 * Schachcomputer-Bundle: gewertete Partien gegen Stockfish im Browser
 * — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoSchachcomputerBundle\Module;

use Contao\BackendTemplate;
use Contao\FrontendUser;
use Contao\Module;
use Contao\StringUtil;
use Contao\System;

/**
 * Gemeinsame Grundlage der Frontend-Module des Schachcomputers.
 *
 * Enthält, was alle vier Module gleich machen: im Backend nur einen
 * Platzhalter ausgeben und das angemeldete Mitglied ermitteln. Die Module
 * selbst setzen nur $strTemplate und compile(). Das klassische Modul über
 * $GLOBALS['FE_MOD'] läuft unter Contao 4.13 und 5.7 ohne Versionsweiche.
 */
abstract class SchachcomputerModul extends Module
{
	/**
	 * Gibt im Backend nur einen Platzhalter aus, im Frontend das Modul.
	 *
	 * Der Link zum Bearbeiten des Moduls kommt über den Router (Route
	 * contao_backend) wie in Contaos eigenen Modulen; so stimmt er auch,
	 * wenn das Backend nicht unter /contao liegt (contao.backend.route_prefix).
	 *
	 * @return string Das HTML des Moduls
	 */
	public function generate()
	{
		$request = System::getContainer()->get('request_stack')->getCurrentRequest();

		if (null !== $request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
			$template = new BackendTemplate('be_wildcard');
			$template->wildcard = '### '.mb_strtoupper($GLOBALS['TL_LANG']['FMD'][$this->type][0] ?? $this->type).' ###';
			$template->title = $this->headline;
			$template->id = $this->id;
			$template->link = $this->name;
			$template->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', array('do' => 'themes', 'table' => 'tl_module', 'act' => 'edit', 'id' => $this->id)));

			return $template->parse();
		}

		return parent::generate();
	}

	/**
	 * Ermittelt das angemeldete Mitglied über den Sicherheitskontext.
	 *
	 * security.token_storage ist in 4.13 (Symfony 5.4) und in 5.7 (über
	 * Contaos MakeServicesPublicPass) öffentlich.
	 *
	 * @return int|null Die ID aus tl_member, oder null für Gäste und für
	 *                  einen nur im Backend angemeldeten Benutzer
	 */
	protected function memberId(): ?int
	{
		$token = System::getContainer()->get('security.token_storage')->getToken();
		$user = null === $token ? null : $token->getUser();

		return $user instanceof FrontendUser && (int) $user->id > 0 ? (int) $user->id : null;
	}
}
