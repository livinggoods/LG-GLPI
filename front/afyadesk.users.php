<?php

use Glpi\Application\View\TemplateRenderer;

include('../inc/includes.php');

Session::checkRight('user', READ);

global $DB;

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkRight('user', CREATE);
    Session::checkCSRF($_POST);

    $login = trim((string) ($_POST['login'] ?? ''));
    $display_name = trim((string) ($_POST['display_name'] ?? ''));
    $profiles_id = (int) ($_POST['profiles_id'] ?? 0);
    $entities_id = (int) ($_POST['entities_id'] ?? 0);
    $password = (string) ($_POST['password'] ?? '');

    if ($login === '' || $profiles_id <= 0 || $entities_id <= 0) {
        $error = __('Email / Username, access level, and service area are required.');
    } else {
        $parts = preg_split('/\s+/', $display_name, 2) ?: [];
        $user = new User();
        $input = [
            'name'          => $login,
            'firstname'     => $parts[0] ?? '',
            'realname'      => $parts[1] ?? '',
            'authtype'      => Auth::DB_GLPI,
            'is_active'     => 1,
            '_profiles_id'  => $profiles_id,
            '_entities_id'  => $entities_id,
            '_is_recursive' => 1,
        ];

        if ($password !== '') {
            $input['password'] = $password;
            $input['password2'] = $password;
        } else {
            $input['_init_password'] = 1;
        }

        $users_id = $user->add($input);
        if ($users_id) {
            if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
                $email = new UserEmail();
                $email->add([
                    'users_id'   => $users_id,
                    'email'      => $login,
                    'is_default' => 1,
                ]);
            }
            $message = __('User added successfully.');
        } else {
            $error = __('Unable to add user. Check whether the account already exists or password rules failed.');
        }
    }
}

$query = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$profiles = [];
$profile_result = $DB->query(
    "SELECT id, name FROM glpi_profiles
     WHERE name LIKE 'AfyaDesk %' OR name IN ('Self-Service', 'Observer', 'Technician', 'Super-Admin')
     ORDER BY FIELD(name, 'AfyaDesk County Admin', 'AfyaDesk Sub County Admin', 'AfyaDesk Ward Supervisor', 'AfyaDesk Community Unit User') DESC, name"
);
while ($row = $profile_result->fetch_assoc()) {
    $profiles[] = $row;
}

$entities = [];
$entity_result = $DB->query(
    "SELECT id, completename, level FROM glpi_entities
     WHERE id > 0
     ORDER BY completename"
);
while ($row = $entity_result->fetch_assoc()) {
    $entities[] = $row;
}

$where = "u.is_deleted = 0";
$safe_query = $DB->escape($query);
if ($query !== '') {
    $where .= " AND (u.name LIKE '%$safe_query%' OR u.firstname LIKE '%$safe_query%' OR u.realname LIKE '%$safe_query%' OR e.completename LIKE '%$safe_query%' OR p.name LIKE '%$safe_query%')";
}

$count_result = $DB->query("SELECT COUNT(DISTINCT u.id) AS total FROM glpi_users u
    LEFT JOIN glpi_profiles_users pu ON pu.users_id = u.id
    LEFT JOIN glpi_profiles p ON p.id = pu.profiles_id
    LEFT JOIN glpi_entities e ON e.id = pu.entities_id
    WHERE $where");
$total = (int) ($count_result->fetch_assoc()['total'] ?? 0);
$pages = max(1, (int) ceil($total / $limit));

$users = [];
$result = $DB->query("SELECT
        u.id,
        u.name AS login,
        u.firstname,
        u.realname,
        u.authtype,
        GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR '||') AS profiles,
        GROUP_CONCAT(DISTINCT e.completename ORDER BY e.completename SEPARATOR '||') AS service_areas
    FROM glpi_users u
    LEFT JOIN glpi_profiles_users pu ON pu.users_id = u.id
    LEFT JOIN glpi_profiles p ON p.id = pu.profiles_id
    LEFT JOIN glpi_entities e ON e.id = pu.entities_id
    WHERE $where
    GROUP BY u.id
    ORDER BY u.name
    LIMIT $limit OFFSET $offset");
while ($row = $result->fetch_assoc()) {
    $profiles_list = array_filter(explode('||', (string) $row['profiles']));
    $areas_list = array_filter(explode('||', (string) $row['service_areas']));
    $users[] = [
        'id'            => (int) $row['id'],
        'login'         => $row['login'],
        'name'          => trim($row['firstname'] . ' ' . $row['realname']),
        'profiles'      => $profiles_list,
        'service_areas' => array_map(static fn($area) => preg_replace('/^Root entity > /', '', $area), $areas_list),
        'auth'          => ((int) $row['authtype']) === Auth::DB_GLPI ? __('Password') : __('External'),
    ];
}

Html::header(__('AfyaDesk user management'), $_SERVER['PHP_SELF'], 'admin', 'user');
TemplateRenderer::getInstance()->display('pages/admin/afyadesk_users.html.twig', [
    'message'  => $message,
    'error'    => $error,
    'profiles' => $profiles,
    'entities' => $entities,
    'users'    => $users,
    'query'    => $query,
    'page'     => $page,
    'pages'    => $pages,
    'total'    => $total,
]);
Html::footer();
