<?php

/**
 * Import AfyaDesk users and service-area authorizations from CSV.
 *
 * Usage:
 *   php tools/import-afyadesk-users.php path\to\users.csv
 *
 * Supported columns:
 *   login,email,first_name,last_name,mobile,access_level,service_area,include_lower_levels
 *
 * service_area may be either a single entity name or a full path:
 *   Kisumu County > Kadibo Sub County > Kobura Ward > Okana Community Health Unit
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/import-afyadesk-users.php users.csv\n");
    exit(1);
}

$csv_file = $argv[1];
if (!is_readable($csv_file)) {
    fwrite(STDERR, "CSV file is not readable: $csv_file\n");
    exit(1);
}

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

function column_exists(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->bind_param('s', $column);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function normalize_header(string $header): string
{
    return strtolower(trim(str_replace([' ', '-'], '_', $header)));
}

function normalize_bool(?string $value): int
{
    return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'y', 'true', 'include'], true) ? 1 : 0;
}

function find_id_by_name(mysqli $db, string $table, string $name): ?int
{
    $stmt = $db->prepare("SELECT id FROM `$table` WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int) $row['id'] : null;
}

function find_entity(mysqli $db, string $service_area): ?int
{
    $service_area = trim($service_area);
    if ($service_area === '') {
        return null;
    }

    $path = array_values(array_filter(array_map('trim', explode('>', $service_area))));
    if (count($path) > 1) {
        $complete = implode(' > ', array_merge(['Root entity'], $path));
        $stmt = $db->prepare('SELECT id FROM glpi_entities WHERE completename = ? LIMIT 1');
        $stmt->bind_param('s', $complete);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            return (int) $row['id'];
        }
    }

    return find_id_by_name($db, 'glpi_entities', end($path) ?: $service_area);
}

function ensure_user(mysqli $db, array $row): int
{
    $login = trim($row['login'] ?? '');
    $email = trim($row['email'] ?? '');
    if ($login === '') {
        $login = $email;
    }
    if ($login === '') {
        throw new RuntimeException('Missing login/email.');
    }

    $stmt = $db->prepare('SELECT id FROM glpi_users WHERE name = ? LIMIT 1');
    $stmt->bind_param('s', $login);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    if ($existing) {
        return (int) $existing['id'];
    }

    $now = date('Y-m-d H:i:s');
    $first = trim($row['first_name'] ?? '');
    $last = trim($row['last_name'] ?? '');
    $mobile = trim($row['mobile'] ?? '');
    $stmt = $db->prepare(
        'INSERT INTO glpi_users (name, firstname, realname, mobile, is_active, authtype, date_creation, date_mod)
         VALUES (?, ?, ?, ?, 1, 1, ?, ?)'
    );
    $stmt->bind_param('ssssss', $login, $first, $last, $mobile, $now, $now);
    if (!$stmt->execute()) {
        throw new RuntimeException("Unable to create user $login: {$stmt->error}");
    }

    $user_id = (int) $db->insert_id;
    if ($email !== '') {
        $default_col = column_exists($db, 'glpi_useremails', 'is_default') ? ', is_default' : '';
        $default_val = column_exists($db, 'glpi_useremails', 'is_default') ? ', 1' : '';
        $insert_email = $db->prepare(
            "INSERT INTO glpi_useremails (users_id, email$default_col) VALUES (?, ?$default_val)"
        );
        $insert_email->bind_param('is', $user_id, $email);
        $insert_email->execute();
    }

    return $user_id;
}

function ensure_authorization(mysqli $db, int $user_id, int $profile_id, int $entity_id, int $recursive): bool
{
    $stmt = $db->prepare(
        'SELECT id FROM glpi_profiles_users
         WHERE users_id = ? AND profiles_id = ? AND entities_id = ? LIMIT 1'
    );
    $stmt->bind_param('iii', $user_id, $profile_id, $entity_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        return false;
    }

    $insert = $db->prepare(
        'INSERT INTO glpi_profiles_users (users_id, profiles_id, entities_id, is_recursive, is_dynamic, is_default_profile)
         VALUES (?, ?, ?, ?, 0, 0)'
    );
    $insert->bind_param('iiii', $user_id, $profile_id, $entity_id, $recursive);
    if (!$insert->execute()) {
        throw new RuntimeException("Unable to add authorization for user $user_id: {$insert->error}");
    }
    return true;
}

$handle = fopen($csv_file, 'r');
$headers = array_map('normalize_header', fgetcsv($handle) ?: []);
if (!$headers) {
    fwrite(STDERR, "CSV file has no header row.\n");
    exit(1);
}

$created_users = 0;
$created_authorizations = 0;
$reused_authorizations = 0;
$row_number = 1;

while (($values = fgetcsv($handle)) !== false) {
    $row_number++;
    $row = array_combine($headers, array_pad($values, count($headers), ''));
    if (!$row) {
        continue;
    }

    try {
        $before = (int) ($db->query('SELECT COUNT(*) AS c FROM glpi_users')->fetch_assoc()['c'] ?? 0);
        $user_id = ensure_user($db, $row);
        $after = (int) ($db->query('SELECT COUNT(*) AS c FROM glpi_users')->fetch_assoc()['c'] ?? 0);
        if ($after > $before) {
            $created_users++;
        }

        $profile_name = trim($row['access_level'] ?? 'AfyaDesk Community Unit User');
        $profile_id = find_id_by_name($db, 'glpi_profiles', $profile_name);
        if (!$profile_id) {
            throw new RuntimeException("Unknown access level '$profile_name'. Run seed-afyadesk-access-levels.php first.");
        }

        $entity_id = find_entity($db, $row['service_area'] ?? '');
        if (!$entity_id) {
            throw new RuntimeException("Unknown service area '{$row['service_area']}'. Run seed-afyadesk-service-areas.php first.");
        }

        if (ensure_authorization($db, $user_id, $profile_id, $entity_id, normalize_bool($row['include_lower_levels'] ?? ''))) {
            $created_authorizations++;
        } else {
            $reused_authorizations++;
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "Row $row_number skipped: {$e->getMessage()}\n");
    }
}

echo "AfyaDesk user import complete. Users created: $created_users. Authorizations created: $created_authorizations. Authorizations reused: $reused_authorizations.\n";
