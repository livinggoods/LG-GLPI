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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

use function Safe\json_decode;
use function Safe\preg_replace;
use function Safe\strtotime;

class NotificationWhatsapp implements NotificationInterface
{
    public static function check($value, $options = [])
    {
        return self::isPhoneNumberValid((string) $value);
    }

    public static function isPhoneNumberValid(string $phone): bool
    {
        return self::normalizePhoneNumber($phone) !== '';
    }

    public static function normalizePhoneNumber(string $phone): string
    {
        global $CFG_GLPI;

        $phone = preg_replace('/[^\d+]/', '', $phone);
        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        } elseif (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        } else {
            $country_code = preg_replace('/\D/', '', (string) ($CFG_GLPI['whatsapp_default_country_code'] ?? ''));
            if ($country_code !== '') {
                $phone = ltrim($phone, '0');
                $phone = $country_code . $phone;
            }
        }

        return preg_match('/^[1-9][0-9]{7,14}$/', $phone) === 1 ? $phone : '';
    }

    public static function testNotification(): array
    {
        global $CFG_GLPI;

        $recipient = self::normalizePhoneNumber((string) ($CFG_GLPI['whatsapp_admin_recipient'] ?? ''));
        if ($recipient === '') {
            return [
                'success' => false,
                'error'   => __('A valid WhatsApp test recipient is required.'),
            ];
        }

        return self::sendMessage($recipient, '[GLPI] ' . __('This is a test WhatsApp notification.'));
    }

    #[Override]
    public function sendNotification($options = [])
    {
        $data = [];
        $data['itemtype'] = $options['_itemtype'];
        $data['items_id'] = $options['_items_id'];
        $data['notificationtemplates_id'] = $options['_notificationtemplates_id'];
        $data['entities_id'] = $options['_entities_id'];
        $data['sendername'] = $options['fromname'] ?? 'GLPI';
        $data['name'] = $options['subject'];
        $data['body_text'] = $options['content_text'];
        $data['recipient'] = self::normalizePhoneNumber((string) $options['to']);
        $data['recipientname'] = $options['toname'] ?? '';
        $data['event'] = $options['event'] ?? null;
        $data['mode'] = Notification_NotificationTemplate::MODE_WHATSAPP;

        if ($data['recipient'] === '') {
            Session::addMessageAfterRedirect(__s('Error inserting WhatsApp notification to queue: invalid recipient phone number'), true, ERROR);
            return false;
        }

        $queue = new QueuedNotification();

        if (!$queue->add($data)) {
            Session::addMessageAfterRedirect(__s('Error inserting WhatsApp notification to queue'), true, ERROR);
            return false;
        }

        Toolbox::logInFile(
            "notification",
            sprintf(
                __('%1$s: %2$s'),
                sprintf(
                    __('A WhatsApp notification to %s was added to queue'),
                    $data['recipient']
                ),
                $options['subject'] . "\n"
            )
        );

        $itemtype = (string) $queue->fields['itemtype'];
        $event    = (string) $queue->fields['event'];
        if (NotificationTarget::shouldNotificationBeSentImmediately($itemtype, $event)) {
            NotificationEventWhatsapp::send([$queue->fields]);
        }

        return true;
    }

    public static function sendQueuedNotifications(array $data): false|int
    {
        $processed = [];

        foreach ($data as $row) {
            $current = new QueuedNotification();
            $current->getFromResultSet($row);

            $message = trim($current->fields['name'] . "\n\n" . $current->fields['body_text']);
            $result = self::sendMessage($current->fields['recipient'], $message);

            if (!$result['success']) {
                self::handleFailedSend($current, $result['error'] ?? __('Unknown error'));
                continue;
            }

            Toolbox::logInFile(
                "notification",
                sprintf(
                    __('%1$s: %2$s'),
                    sprintf(
                        __('A WhatsApp notification was sent to %s'),
                        $current->fields['recipient']
                    ),
                    $current->fields['name'] . "\n"
                )
            );
            $processed[] = $current->getID();
            $current->update([
                'id'        => $current->fields['id'],
                'sent_time' => $_SESSION['glpi_currenttime'],
            ]);
            $current->delete(['id' => $current->fields['id']]);
        }

        return count($processed);
    }

    public static function sendMessage(string $recipient, string $message): array
    {
        global $CFG_GLPI;

        $api_url = rtrim((string) ($CFG_GLPI['whatsapp_api_url'] ?? ''), '/');
        $phone_number_id = trim((string) ($CFG_GLPI['whatsapp_phone_number_id'] ?? ''));
        $access_token = (string) (new GLPIKey())->decrypt($CFG_GLPI['whatsapp_access_token'] ?? '');
        $template_name = trim((string) ($CFG_GLPI['whatsapp_template_name'] ?? ''));
        $template_language = trim((string) ($CFG_GLPI['whatsapp_template_language'] ?? 'en')) ?: 'en';

        if ($api_url === '' || $phone_number_id === '' || $access_token === '') {
            return [
                'success' => false,
                'error'   => __('WhatsApp notification settings are incomplete.'),
            ];
        }

        $client = new Client([
            'timeout' => 20,
        ]);

        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'                => $recipient,
            ];
            if ($template_name !== '') {
                $payload += [
                    'type'     => 'template',
                    'template' => [
                        'name'     => $template_name,
                        'language' => [
                            'code' => $template_language,
                        ],
                        'components' => [
                            [
                                'type'       => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $message,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            } else {
                $payload += [
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body'        => $message,
                    ],
                ];
            }

            $response = $client->post(
                sprintf('%s/%s/messages', $api_url, rawurlencode($phone_number_id)),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $access_token,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => $payload,
                ]
            );
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }

        $body = (string) $response->getBody();
        $payload = $body !== '' ? json_decode($body, true) : [];

        return [
            'success' => $response->getStatusCode() >= 200 && $response->getStatusCode() < 300,
            'error'   => $payload['error']['message'] ?? null,
            'debug'   => $payload,
        ];
    }

    private static function handleFailedSend(QueuedNotification $notification, string $error): void
    {
        global $CFG_GLPI;

        Session::addMessageAfterRedirect(__s('Error in sending the WhatsApp notification') . "<br/>" . htmlescape($error), true, ERROR);

        $retries = $CFG_GLPI['smtp_max_retries'] - $notification->fields['sent_try'];
        Toolbox::logInFile(
            "notification-error",
            sprintf(
                __('%1$s. Message: %2$s, Error: %3$s') . "\n",
                sprintf(
                    __('Warning: a WhatsApp notification was undeliverable to %s with %d retries remaining'),
                    $notification->fields['recipient'],
                    $retries
                ),
                $notification->fields['name'],
                $error
            )
        );

        if ($retries <= 0) {
            $notification->delete(['id' => $notification->fields['id']]);
            return;
        }

        $input = [
            'id'       => $notification->fields['id'],
            'sent_try' => $notification->fields['sent_try'] + 1,
        ];

        if ($CFG_GLPI["smtp_retry_time"] > 0) {
            $input['send_time'] = date("Y-m-d H:i:s", strtotime('+' . $CFG_GLPI["smtp_retry_time"] . ' minutes'));
        }
        $notification->update($input);
    }
}
