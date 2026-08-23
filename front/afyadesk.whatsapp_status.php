<?php

use Glpi\Application\View\TemplateRenderer;

include('../inc/includes.php');

Session::checkRight('config', READ);

global $DB;

$queued = [];
$iterator = $DB->request([
    'SELECT' => [
        'id',
        'itemtype',
        'items_id',
        'recipient',
        'name',
        'event',
        'sent_try',
        'send_time',
        'create_time',
    ],
    'FROM'   => QueuedNotification::getTable(),
    'WHERE'  => ['mode' => Notification_NotificationTemplate::MODE_WHATSAPP],
    'ORDER'  => ['id DESC'],
    'LIMIT'  => 50,
]);
foreach ($iterator as $row) {
    $queued[] = $row;
}

$templates = [];
$iterator = $DB->request([
    'SELECT' => [
        'glpi_notificationtemplates.id',
        'glpi_notificationtemplates.name',
        'glpi_notificationtemplates.itemtype',
        'glpi_notifications.event',
        'glpi_notifications.name AS notification_name',
    ],
    'FROM' => 'glpi_notifications_notificationtemplates',
    'INNER JOIN' => [
        'glpi_notificationtemplates' => [
            'ON' => [
                'glpi_notifications_notificationtemplates' => 'notificationtemplates_id',
                'glpi_notificationtemplates' => 'id',
            ],
        ],
        'glpi_notifications' => [
            'ON' => [
                'glpi_notifications_notificationtemplates' => 'notifications_id',
                'glpi_notifications' => 'id',
            ],
        ],
    ],
    'WHERE' => ['glpi_notifications_notificationtemplates.mode' => Notification_NotificationTemplate::MODE_WHATSAPP],
    'ORDER' => ['glpi_notificationtemplates.name'],
]);
foreach ($iterator as $row) {
    $templates[] = $row;
}

Html::header(__('AfyaDesk WhatsApp status'), $_SERVER['PHP_SELF'], 'config', 'notification');
TemplateRenderer::getInstance()->display('pages/setup/notification/afyadesk_whatsapp_status.html.twig', [
    'queued'    => $queued,
    'templates' => $templates,
]);
Html::footer();
