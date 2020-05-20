<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2020 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class GLPINetwork {

   public function getTabNameForItem() {
      return __('GLPI Network');
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($item->getType() == 'Config') {
         $glpiNetwork = new self();
         $glpiNetwork->showForConfig();
      }
   }

   public static function showForConfig() {
      if (!Config::canView()) {
         return;
      }

      $registration_key = self::getRegistrationKey();
      $informations = self::getRegistrationInformations();

      $canedit = Config::canUpdate();
      if ($canedit) {
         echo "<form name='form' action=\"".Toolbox::getItemTypeFormURL(Config::class)."\" method='post'>";
      }
      echo "<div class='center' id='tabsbody'>";
      echo "<table class='tab_cadre_fixe'>";

      echo "<tr><th colspan='2'>" . __('Registration') . "</th></tr>";

      if ($registration_key === "") {
         echo "<tr><td colspan='2'>".
         __('A registration key is needed to use advanced feature (like marketplace) in GLPI')."<br><br>".
         "<a href='".GLPI_NETWORK_SERVICES."'>".__('Register on GLPI Network!')."</a><br>".
         __("And retrieve your key to paste it below").
         "</td></tr>";
      }

      echo "<tr class='tab_bg_2'>";
      echo "<td><label for='glpinetwork_registration_key'>" . __('Registration key') . "</label></td>";
      echo "<td>" . Html::textarea(['name' => 'glpinetwork_registration_key', 'value' => $registration_key, 'display' => false]) . "</td>";
      echo "</tr>";

      if ($registration_key !== "") {
         if (!empty($informations['validation_message'])) {
            echo "<tr class='tab_bg_2'>";
            echo "<td></td>";
            echo "<td>";
            echo "<div class=' " . ($informations['is_valid'] ? 'ok' : 'red') . "'> ";
            echo "<i class='fa fa-info-circle'></i>";
            echo $informations['validation_message'];
            echo "</div>";
            echo "</td>";
            echo "</tr>";
         }

         echo "<tr class='tab_bg_2'>";
         echo "<td>" . __('Subscription') . "</td>";
         echo "<td>" . ($informations['subscription'] !== null ? $informations['subscription']['title'] : __('Unknown')) . "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_2'>";
         echo "<td>" . __('Registered by') . "</td>";
         echo "<td>" . ($informations['owner'] !== null ? $informations['owner']['name'] : __('Unknown')) . "</td>";
         echo "</tr>";
      }

      if ($canedit) {
         echo "<tr class='tab_bg_2'>";
         echo "<td colspan='2' class='center'>";
         echo "<input type='submit' name='update' class='submit' value=\""._sx('button', 'Save')."\">";
         echo "</td></tr>";
      }

      echo "</table></div>";
      Html::closeForm();
   }

   /**
    * Get GLPI User Agent in expected format from GLPI Network services.
    *
    * @return string
    */
   public static function getGlpiUserAgent(): string {
      $version = defined('GLPI_PREVER') ? GLPI_PREVER : GLPI_VERSION;
      $comments = sprintf('installation-mode:%s', GLPI_INSTALL_MODE);
      if (!empty(GLPI_USER_AGENT_EXTRA_COMMENTS)) {
         // append extra comments (remove '(' and ')' chars to not break UA string)
         $comments .= '; ' . preg_replace('/\(\)/', ' ', GLPI_USER_AGENT_EXTRA_COMMENTS);
      }
      return sprintf('GLPI/%s (%s)', $version, $comments);
   }

   /**
    * Get GLPI Network UID to pass in requests to GLPI Network Services.
    *
    * @return string
    */
   public static function getGlpiNetworkUid(): string {
      return Config::getUuid('glpi_network');
   }

   /**
    * Get GLPI Network registration key.
    *
    * A registration key is a base64 encoded JSON string with a key 'signature' containing the binary
    * signature of the whole.
    *
    * @return string
    */
   public static function getRegistrationKey(): string {
      global $CFG_GLPI;
      return $CFG_GLPI['glpinetwork_registration_key'] ?? '';
   }

   /**
    * Get GLPI Network registration informations.
    *
    * @return array  Registration data:
    *    - is_valid (boolean):          indicates if key is valid;
    *    - validation_message (string): message related to validation state;
    *    - owner (array):               owner attributes;
    *    - subscription (array):        subscription attributes.
    */
   public static function getRegistrationInformations() {
      global $GLPI_CACHE;

      $registration_key = self::getRegistrationKey();

      $cache_key = sprintf('registration_%s_informations', sha1($registration_key));
      if (($informations = $GLPI_CACHE->get($cache_key)) !== null) {
         return $informations;
      }

      $informations = [
         'is_valid'           => false,
         'validation_message' => null,
         'owner'              => null,
         'subscription'       => null,
      ];

      if ($registration_key === '') {
         return $informations;
      }

      // Verify registration from registration API
      $error_message = null;
      $registration_response = Toolbox::callCurl(
         rtrim(GLPI_NETWORK_REGISTRATION_API_URL, '/') . '/info',
         [
            CURLOPT_HTTPHEADER => [
               'Accept:application/json',
               'Content-Type:application/json',
               'User-Agent:' . self::getGlpiUserAgent(),
               'X-Registration-Key:' . $registration_key,
               'X-Glpi-Network-Uid:' . self::getGlpiNetworkUid(),
            ]
         ],
         $error_message
      );
      $registration_data = $error_message === null ? json_decode($registration_response, true) : null;
      if ($error_message !== null || json_last_error() !== JSON_ERROR_NONE
          || !is_array($registration_data) || !array_key_exists('is_valid', $registration_data)) {
         $informations['validation_message'] = __('Unable to fetch registration informations.');
         return $informations;
      }

      $informations['is_valid']           = $registration_data['is_valid'];
      $informations['validation_message'] = $registration_data['is_valid']
         ? __('The registration key is valid.')
         : __('The registration key is invalid.');
      $informations['owner']              = $registration_data['owner'];
      $informations['subscription']       = $registration_data['subscription'];

      $GLPI_CACHE->set($cache_key, $informations, new \DateInterval('P1D')); // Cache for one day

      return $informations;
   }

   /**
    * Check if GLPI Network registration is existing and valid.
    *
    * @return boolean
    */
   public static function isRegistered(): bool {
      return self::getRegistrationInformations()['is_valid'];
   }

   public static function showInstallMessage() {
      return nl2br(sprintf(__("You need help to integrate GLPI in your IT, have a bug fixed or benefit from pre-configured rules or dictionaries?\n\n".
         "We provide the %s space for you.\n".
         "GLPI-Network is a commercial product that includes a subscription for tier 3 support, ensuring the correction of bugs encountered with a commitment time.\n\n".
         "In this same space, you will be able to <b>contact an official partner</b> to help you with your GLPI integration.\n\n".
         "Or, support the GLPI development effort by <b>donating</b>."),
         "<a href='".GLPI_NETWORK_SERVICES."' target='_blank'>".GLPI_NETWORK_SERVICES."</a>"));
   }

   public static function getErrorMessage() {
      return nl2br(sprintf(__("Having troubles setting up an advanced GLPI module?\n".
         "We can help you solve them. Sign up for support on %s."),
         "<a href='".GLPI_NETWORK_SERVICES."' target='_blank'>".GLPI_NETWORK_SERVICES."</a>"));
   }

   public static function addErrorMessageAfterRedirect() {
      Session::addMessageAfterRedirect(self::getErrorMessage(), false, ERROR);
   }

   public static function isServicesAvailable() {
      $content = \Toolbox::callCurl(GLPI_NETWORK_REGISTRATION_API_URL);
      return strlen($content) > 0;
   }

   public static function getOffers(bool $force_refresh = false): array {
      global $GLPI_CACHE;

      $lang = preg_replace('/^([a-z]+)_.+$/', '$1', $_SESSION["glpilanguage"]);
      $cache_key = 'glpi_network_offers_' . $lang;

      if (!$force_refresh && $GLPI_CACHE->has($cache_key)) {
         return $GLPI_CACHE->get($cache_key);
      }

      $response = \Toolbox::callCurl(
         rtrim(GLPI_NETWORK_REGISTRATION_API_URL, '/') . '/offers',
         [
            CURLOPT_HTTPHEADER => ['Accept-Language: ' . $lang]
         ]
      );
      $offers   = json_decode($response, true);

      if (is_array($offers)) {
         $GLPI_CACHE->set($cache_key, $offers, HOUR_TIMESTAMP);
      }

      return $offers;
   }
}
