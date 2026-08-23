<?php

/**
 * Seed AfyaDesk LivingGoods service areas into GLPI entities.
 *
 * The hierarchy is:
 * County > Sub County > Ward > Community Health Unit
 *
 * This script is idempotent. Existing entities with the same parent and name
 * are reused, so it can safely be run more than once.
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

if (!class_exists('DB')) {
    fwrite(STDERR, "Unable to load GLPI database configuration.\n");
    exit(1);
}

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

$areas = [
    'Kisumu County' => [
        'Kisumu Central Sub County' => [
            'Kondele Ward' => [
                'Manyatta A 1 Community Health Unit',
                'Manyatta A 2 Community Health Unit',
                'Manyatta A Community Unit',
                'Manyatta A PGH Community Health Unit',
                'Nyawita Community Health Unit',
                'Nyawita Community Unit',
                'Obunga Community Unit',
                'Tom Mboya Community Health Unit',
                'Tom Mboya Community Unit',
            ],
            'Market Milimani Ward' => [
                'Kedh Community Unit',
                'Kisumu Dh - New Born Unit (old)',
                'Kisumu Dh 1 - Maternity (old)',
                'Kisumu Dh 2 - Peadiatrics (old)',
                'Kisumu Dh 3 - Female Medical (old)',
                'Kisumu Dh 4 - Male Medical',
                'Kisumu Dh 5 - Surgical',
                'Kisumu Dh 6 - Gynaecology',
                'Kisumu Dh 7 - Amenity',
                'Kisumu Dh 8 - Psychiatry',
                'Milimani Community Health Unit',
                'Southern Community Health Unit',
                'Township Community Health Unit',
            ],
            'Migosi Ward' => [
                'Area A 1 Community Health Unit',
                'Area A 2 Community Health Unit',
                'Area A 3 Community Health Unit',
                'Area B1 Community Health Unit',
                'Area B2 Community Health Unit',
                'Area B3 Community Health Unit',
                'Area C 1 Community Health Unit',
                'Area C 2 Community Health Unit',
                'Area C 3 Community Health Unit',
                'Area C Community Health Unit',
                'Closed_Manyattta A Community Health Unit',
                'Kuoyo Community Unit',
                'Lolwe Community Health Unit',
                'Lower Kanyakwar',
                'Manyatta Area A Community Health Unit',
                'Manyatta Area B Community Health Unit',
                'Manyatta Area C Community Unit',
                'Migosi Community Health Unit',
                'Migosi Community Unit',
            ],
            'Nyalenda B Ward' => [
                'Got Owak Community Health Unit',
                'Kilo 1 Community Health Unit',
                'Kilo 2 Community Health Unit',
                'Kilo 3 Community Health Unit',
                'Nanga Dunga Community Health Unit',
                'Nyalenda A Community Health Unit',
                'Western B1',
                'Western B2 Community Health Unit',
                'Western B3 Community Health Unit',
            ],
            'Railways Ward' => [
                'Juakali Community Health Unit',
                'Juakali Mosque Community Unit',
                'Kanyakwar Community Health Unit',
                'Obunga 1 Community Health Unit',
                'Obunga Community Health Unit',
            ],
            'Shauri Moyo Kaloleni Ward' => [
                'Kaloleni Shauri Moyo Community Health Unit',
                'Polyview Community Health Unit',
            ],
        ],
        'Kadibo Sub County' => [
            'Kabonyo/Kanyagwal Ward' => [
                'Anyuro Community Health Unit',
                'Central Bwanda Community Health Unit',
                'Irrigation Community Health Unit',
                'Kadhiambo Community Health Unit',
                'Kapiyo Community Health Unit',
                'Kolal Community Health Unit',
                'Kwakungu Community Health Unit',
                'Nduru Community Health Unit',
                'Ogenya Community Health Unit',
                'Ugwe Community Health Unit',
                'Upper Bwanda Community Health Unit',
            ],
            'Kobura Ward' => [
                'Kamayoga Community Health Unit',
                'Kochieng A Community Health Unit',
                'Kochieng B Community Health Unit',
                'Kotieno Community Health Unit',
                'Lela Community Health Unit',
                'Lela North Community Health Unit',
                'Lela South Community Health Unit',
                'Masogo Community Health Unit',
                'Nyamware North Lower Community Health Unit',
                'Nyamware North Upper Community Health Unit',
                'Nyamware South Community Health Unit',
                'Okana Community Health Unit',
            ],
        ],
    ],
];

$created = 0;
$reused = 0;

function find_or_create_entity(mysqli $db, string $name, int $parent_id): int
{
    global $created, $reused;

    $select = $db->prepare('SELECT id FROM glpi_entities WHERE name = ? AND entities_id <=> ? LIMIT 1');
    $select->bind_param('si', $name, $parent_id);
    $select->execute();
    $result = $select->get_result();
    if ($row = $result->fetch_assoc()) {
        $reused++;
        return (int) $row['id'];
    }

    $parent = $db->prepare('SELECT completename, level FROM glpi_entities WHERE id = ? LIMIT 1');
    $parent->bind_param('i', $parent_id);
    $parent->execute();
    $parent_data = $parent->get_result()->fetch_assoc();
    if (!$parent_data) {
        throw new RuntimeException("Parent entity $parent_id was not found.");
    }

    $complete_name = $parent_data['completename'] . ' > ' . $name;
    $level = ((int) $parent_data['level']) + 1;
    $now = date('Y-m-d H:i:s');
    $comment = 'Seeded by AfyaDesk service area hierarchy import.';

    $insert = $db->prepare(
        'INSERT INTO glpi_entities (name, entities_id, completename, comment, level, date_creation, date_mod)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->bind_param('sississ', $name, $parent_id, $complete_name, $comment, $level, $now, $now);
    if (!$insert->execute()) {
        throw new RuntimeException("Unable to create entity '$complete_name': {$insert->error}");
    }

    $created++;
    return (int) $db->insert_id;
}

try {
    foreach ($areas as $county => $sub_counties) {
        $county_id = find_or_create_entity($db, $county, 0);
        foreach ($sub_counties as $sub_county => $wards) {
            $sub_county_id = find_or_create_entity($db, $sub_county, $county_id);
            foreach ($wards as $ward => $units) {
                $ward_id = find_or_create_entity($db, $ward, $sub_county_id);
                foreach ($units as $unit) {
                    find_or_create_entity($db, $unit, $ward_id);
                }
            }
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo "AfyaDesk service areas seeded. Created: $created. Reused: $reused.\n";
