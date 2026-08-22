<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2026 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

require_once(__DIR__ . '/_check_webserver_config.php');

use Glpi\Event;

Session::checkRight("config", UPDATE);

if (!empty($_POST["test_whatsapp_send"])) {
    $result = NotificationWhatsapp::testNotification();
    if ($result['success']) {
        Session::addMessageAfterRedirect(__s('Test WhatsApp notification sent.'), true, INFO);
    } else {
        Session::addMessageAfterRedirect(
            htmlescape(sprintf(__('Unable to send notification using %1$s'), __('WhatsApp')) . ': ' . ($result['error'] ?? '')),
            true,
            ERROR
        );
    }
    Html::back();
} elseif (!empty($_POST["update"])) {
    $config = new Config();
    $config->update($_POST);
    Event::log(0, "system", 3, "setup", sprintf(
        __('%1$s edited the WhatsApp notifications configuration'),
        $_SESSION["glpiname"] ?? __("Unknown"),
    ));
    Html::back();
}

$menus = ["config", "notification", NotificationWhatsappSetting::class];
$config_id = Config::getConfigIDForContext('core');
NotificationWhatsappSetting::displayFullPageForItem($config_id, $menus);
