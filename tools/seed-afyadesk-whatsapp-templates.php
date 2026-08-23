<?php

/**
 * Seed AfyaDesk WhatsApp notification templates and link them to core events.
 */

$config_file = __DIR__ . '/../config/config_db.php';
if (!class_exists('DBmysql')) {
    class DBmysql {}
}
require_once $config_file;

$db_config = new DB();
$db = new mysqli(
    $db_config->dbhost,
    $db_config->dbuser,
    rawurldecode($db_config->dbpassword),
    $db_config->dbdefault
);
if ($db->connect_error) {
    fwrite(STDERR, "Database connection failed: {$db->connect_error}\n");
    exit(1);
}
$db->set_charset('utf8mb4');

$templates = [
    [
        'name'    => 'AfyaDesk WhatsApp - New ticket',
        'itemtype'=> 'Ticket',
        'event'   => 'new',
        'subject' => '[AfyaDesk] New ticket ##ticket.id##',
        'body'    => 'New AfyaDesk ticket ##ticket.id##: ##ticket.title##. Status: ##ticket.status##. Open AfyaDesk for details.',
    ],
    [
        'name'    => 'AfyaDesk WhatsApp - Ticket assigned',
        'itemtype'=> 'Ticket',
        'event'   => 'assign_user',
        'subject' => '[AfyaDesk] Ticket assigned ##ticket.id##',
        'body'    => 'Ticket ##ticket.id## has been assigned. Title: ##ticket.title##. Please review it in AfyaDesk.',
    ],
    [
        'name'    => 'AfyaDesk WhatsApp - Ticket approval',
        'itemtype'=> 'Ticket',
        'event'   => 'validation',
        'subject' => '[AfyaDesk] Approval needed ##ticket.id##',
        'body'    => 'Approval is needed for ticket ##ticket.id##: ##ticket.title##. Please open AfyaDesk to approve or reject.',
    ],
    [
        'name'    => 'AfyaDesk WhatsApp - Ticket solved',
        'itemtype'=> 'Ticket',
        'event'   => 'solved',
        'subject' => '[AfyaDesk] Ticket solved ##ticket.id##',
        'body'    => 'Ticket ##ticket.id## has been marked solved. Title: ##ticket.title##. Open AfyaDesk if more action is needed.',
    ],
    [
        'name'    => 'AfyaDesk WhatsApp - Password setup',
        'itemtype'=> 'User',
        'event'   => 'passwordinit',
        'subject' => '[AfyaDesk] Password setup',
        'body'    => 'Your AfyaDesk account is ready. Please use the password setup link sent by AfyaDesk to finish access setup.',
    ],
];

function find_notification(mysqli $db, string $itemtype, string $event): ?int
{
    $stmt = $db->prepare('SELECT id FROM glpi_notifications WHERE itemtype = ? AND event = ? LIMIT 1');
    $stmt->bind_param('ss', $itemtype, $event);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int) $row['id'] : null;
}

function ensure_template(mysqli $db, array $template): array
{
    $stmt = $db->prepare('SELECT id FROM glpi_notificationtemplates WHERE name = ? LIMIT 1');
    $stmt->bind_param('s', $template['name']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        return [(int) $row['id'], false];
    }

    $now = date('Y-m-d H:i:s');
    $comment = 'Seeded by AfyaDesk WhatsApp template setup.';
    $css = '';
    $insert = $db->prepare(
        'INSERT INTO glpi_notificationtemplates (name, itemtype, date_mod, comment, css, date_creation)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insert->bind_param('ssssss', $template['name'], $template['itemtype'], $now, $comment, $css, $now);
    if (!$insert->execute()) {
        throw new RuntimeException("Unable to create template {$template['name']}: {$insert->error}");
    }
    return [(int) $db->insert_id, true];
}

function ensure_translation(mysqli $db, int $template_id, array $template): bool
{
    $language = '';
    $stmt = $db->prepare(
        'SELECT id FROM glpi_notificationtemplatetranslations
         WHERE notificationtemplates_id = ? AND language = ? LIMIT 1'
    );
    $stmt->bind_param('is', $template_id, $language);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        return false;
    }

    $html = nl2br(htmlspecialchars($template['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    $insert = $db->prepare(
        'INSERT INTO glpi_notificationtemplatetranslations
         (notificationtemplates_id, language, subject, content_text, content_html)
         VALUES (?, ?, ?, ?, ?)'
    );
    $insert->bind_param('issss', $template_id, $language, $template['subject'], $template['body'], $html);
    if (!$insert->execute()) {
        throw new RuntimeException("Unable to create translation for {$template['name']}: {$insert->error}");
    }
    return true;
}

function ensure_link(mysqli $db, int $notification_id, int $template_id): bool
{
    $mode = 'whatsapp';
    $stmt = $db->prepare(
        'SELECT id FROM glpi_notifications_notificationtemplates
         WHERE notifications_id = ? AND notificationtemplates_id = ? AND mode = ? LIMIT 1'
    );
    $stmt->bind_param('iis', $notification_id, $template_id, $mode);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        return false;
    }

    $insert = $db->prepare(
        'INSERT INTO glpi_notifications_notificationtemplates
         (notifications_id, mode, notificationtemplates_id)
         VALUES (?, ?, ?)'
    );
    $insert->bind_param('isi', $notification_id, $mode, $template_id);
    if (!$insert->execute()) {
        throw new RuntimeException("Unable to link WhatsApp template: {$insert->error}");
    }
    return true;
}

$created_templates = 0;
$created_translations = 0;
$created_links = 0;
$skipped = 0;

foreach ($templates as $template) {
    try {
        [$template_id, $template_created] = ensure_template($db, $template);
        $created_templates += $template_created ? 1 : 0;
        $created_translations += ensure_translation($db, $template_id, $template) ? 1 : 0;

        $notification_id = find_notification($db, $template['itemtype'], $template['event']);
        if (!$notification_id) {
            $skipped++;
            continue;
        }
        $created_links += ensure_link($db, $notification_id, $template_id) ? 1 : 0;
    } catch (Throwable $e) {
        fwrite(STDERR, "{$template['name']} skipped: {$e->getMessage()}\n");
        $skipped++;
    }
}

echo "AfyaDesk WhatsApp templates seeded. Templates: $created_templates. Translations: $created_translations. Links: $created_links. Skipped: $skipped.\n";
