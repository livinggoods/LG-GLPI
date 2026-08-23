<?php

/**
 * Export AfyaDesk user access assignments for audit/review.
 *
 * Usage:
 *   php tools/export-afyadesk-user-access-audit.php > afyadesk-user-access-audit.csv
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

$out = fopen('php://output', 'w');
fputcsv($out, [
    'login',
    'first_name',
    'last_name',
    'email',
    'access_level',
    'service_area',
    'include_lower_levels',
    'active',
]);

$sql = <<<SQL
SELECT
    u.name AS login,
    u.firstname,
    u.realname,
    COALESCE(ue.email, '') AS email,
    p.name AS profile_name,
    e.completename AS service_area,
    pu.is_recursive,
    u.is_active
FROM glpi_profiles_users pu
INNER JOIN glpi_users u ON u.id = pu.users_id
INNER JOIN glpi_profiles p ON p.id = pu.profiles_id
INNER JOIN glpi_entities e ON e.id = pu.entities_id
LEFT JOIN glpi_useremails ue ON ue.users_id = u.id
WHERE u.is_deleted = 0
ORDER BY u.name, p.name, e.completename
SQL;

$result = $db->query($sql);
if (!$result) {
    fwrite(STDERR, "Unable to export user access: {$db->error}\n");
    exit(1);
}

while ($row = $result->fetch_assoc()) {
    fputcsv($out, [
        $row['login'],
        $row['firstname'],
        $row['realname'],
        $row['email'],
        $row['profile_name'],
        $row['service_area'],
        ((int) $row['is_recursive']) === 1 ? 'yes' : 'no',
        ((int) $row['is_active']) === 1 ? 'yes' : 'no',
    ]);
}
