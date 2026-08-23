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

use Glpi\Application\View\TemplateRenderer;

class NotificationWhatsappSetting extends NotificationSetting
{
    public static function ensureDefaultConfiguration(): void
    {
        global $CFG_GLPI;

        $defaults = [
            'notifications_whatsapp'           => '0',
            'whatsapp_api_url'                 => 'https://graph.facebook.com/v20.0',
            'whatsapp_phone_number_id'         => '',
            'whatsapp_access_token'            => '',
            'whatsapp_template_name'           => '',
            'whatsapp_template_language'       => 'en',
            'whatsapp_default_country_code'    => '',
            'whatsapp_admin_recipient'         => '',
        ];

        $missing = [];
        foreach ($defaults as $name => $value) {
            if (!array_key_exists($name, $CFG_GLPI)) {
                $missing[$name] = $value;
            }
        }

        if ($missing !== []) {
            Config::setConfigurationValues('core', $missing);
            $CFG_GLPI = array_replace($CFG_GLPI, $missing);
        }
    }

    #[Override]
    public static function getTypeName($nb = 0)
    {
        return __('WhatsApp notifications configuration');
    }

    public function getEnableLabel()
    {
        return __('Enable WhatsApp notifications');
    }

    public static function getMode()
    {
        return Notification_NotificationTemplate::MODE_WHATSAPP;
    }

    public function showFormConfig()
    {
        global $CFG_GLPI;

        self::ensureDefaultConfiguration();

        $glpi_encryption_key = new GLPIKey();
        if ($glpi_encryption_key->hasReadErrors()) {
            $glpi_encryption_key->showReadErrors();

            return;
        }

        TemplateRenderer::getInstance()->display('pages/setup/notification/whatsapp_setting.html.twig', [
            'item' => $this,
            'access_token' => (string) $glpi_encryption_key->decrypt($CFG_GLPI['whatsapp_access_token'] ?? ''),
            'params' => [
                'candel' => false,
                'addbuttons' => [
                    'test_whatsapp_send' => [
                        'text'      => __('Send a test WhatsApp notification'),
                        'icon'      => 'ti ti-brand-whatsapp',
                        'btn_class' => 'btn-outline-secondary',
                    ],
                ],
            ],
        ]);
    }

    #[Override]
    public static function getIcon()
    {
        return "ti ti-brand-whatsapp";
    }
}
