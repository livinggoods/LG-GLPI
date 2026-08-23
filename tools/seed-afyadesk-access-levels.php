<?php

/**
 * Seed AfyaDesk access-level profiles.
 *
 * The profiles are cloned from GLPI's Self-Service profile so rights stay
 * compatible with this GLPI version. Administrators can then tune rights in
 * Administration > Profiles without losing the AfyaDesk naming.
 */

$config_file = __DIR__ . '/../config/config_db.php';
if (!file_exists($config_file)) {
    fwrite(STDERR, "Missing config/config_db.php. Install or configure GLPI first.\n");
    exit(1);
}

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

$levels = [
    'AfyaDesk County Admin'       => 'Manages users and tickets at county level.',
    'AfyaDesk Sub County Admin'   => 'Manages users and tickets at sub county level.',
    'AfyaDesk Ward Supervisor'    => 'Manages ward-level users and support workflows.',
    'AfyaDesk Community Unit User'=> 'Works at Community Health Unit level.',
];

$source = $db->query("SELECT * FROM glpi_profiles WHERE name = 'Self-Service' LIMIT 1")->fetch_assoc();
if (!$source) {
    $source = $db->query("SELECT * FROM glpi_profiles ORDER BY id LIMIT 1")->fetch_assoc();
}
if (!$source) {
    fwrite(STDERR, "No source profile found to clone.\n");
    exit(1);
}

$created = 0;
$reused = 0;
$now = date('Y-m-d H:i:s');

foreach ($levels as $name => $comment) {
    $stmt = $db->prepare('SELECT id FROM glpi_profiles WHERE name = ? LIMIT 1');
    $stmt->bind_param('s', $name);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $reused++;
        continue;
    }

    $row = $source;
    unset($row['id']);
    $row['name'] = $name;
    $row['comment'] = $comment;
    $row['is_default'] = 0;
    $row['date_creation'] = $now;
    $row['date_mod'] = $now;
    $row['last_rights_update'] = $now;

    $columns = array_keys($row);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = sprintf(
        'INSERT INTO glpi_profiles (`%s`) VALUES (%s)',
        implode('`, `', $columns),
        $placeholders
    );
    $insert = $db->prepare($sql);
    $types = str_repeat('s', count($columns));
    $values = array_values($row);
    $insert->bind_param($types, ...$values);
    if (!$insert->execute()) {
        fwrite(STDERR, "Unable to create profile $name: {$insert->error}\n");
        exit(1);
    }
    $created++;
}

echo "AfyaDesk access levels seeded. Created: $created. Reused: $reused.\n";
