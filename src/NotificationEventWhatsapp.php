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

class NotificationEventWhatsapp extends NotificationEventAbstract
{
    public static function getTargetFieldName()
    {
        return 'mobile';
    }

    public static function getTargetField(&$data)
    {
        $field = self::getTargetFieldName();

        if (
            empty($data[$field])
            && isset($data['users_id'])
        ) {
            $user = new User();
            if ($user->getFromDB((int) $data['users_id'])) {
                $data[$field] = $user->fields['mobile'] ?: $user->fields['phone'] ?: $user->fields['phone2'];
            }
        }

        if (empty($data[$field]) || !NotificationWhatsapp::isPhoneNumberValid((string) $data[$field])) {
            $data[$field] = null;
        } else {
            $data[$field] = NotificationWhatsapp::normalizePhoneNumber((string) $data[$field]);
        }

        return $field;
    }

    public static function canCron()
    {
        return true;
    }

    public static function getAdminData()
    {
        global $CFG_GLPI;

        $recipient = NotificationWhatsapp::normalizePhoneNumber((string) ($CFG_GLPI['whatsapp_admin_recipient'] ?? ''));
        if ($recipient === '') {
            return [];
        }

        return [
            'mobile'   => $recipient,
            'language' => $CFG_GLPI['language'],
        ];
    }

    public static function getEntityAdminsData($entity)
    {
        $admin = self::getAdminData();

        return $admin === [] ? [] : [$admin];
    }

    public static function send(array $data)
    {
        return NotificationWhatsapp::sendQueuedNotifications($data);
    }
}
