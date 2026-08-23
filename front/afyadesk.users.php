<?php

use Glpi\Application\View\TemplateRenderer;

include('../inc/includes.php');

if (!Session::getLoginUserID()) {
    Html::redirect($CFG_GLPI['root_doc'] . '/front/login.php');
}

global $DB;

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkRight('user', CREATE);
    Session::checkCSRF($_POST);

    $login = trim((string) ($_POST['login'] ?? ''));
    $display_name = trim((string) ($_POST['display_name'] ?? ''));
    $submitted_profiles = $_POST['profiles_id'] ?? [];
    if (!is_array($submitted_profiles)) {
        $submitted_profiles = [$submitted_profiles];
    }
    $profiles_ids = array_values(array_unique(array_filter(
        array_map('intval', $submitted_profiles),
        static fn($profiles_id) => $profiles_id > 0
    )));
    $entities_id = (int) ($_POST['entities_id'] ?? 0);
    $is_recursive = (int) ($_POST['_is_recursive'] ?? 1) === 1 ? 1 : 0;
    $password = (string) ($_POST['password'] ?? '');

    if ($login === '' || count($profiles_ids) === 0 || $entities_id <= 0) {
        $error = __('Email / Username, at least one access level, and service area are required.');
    } else {
        $parts = preg_split('/\s+/', $display_name, 2) ?: [];
        $user = new User();
        $input = [
            'name'          => $login,
            'firstname'     => $parts[0] ?? '',
            'realname'      => $parts[1] ?? '',
            'authtype'      => Auth::DB_GLPI,
            'is_active'     => 1,
            '_profiles_id'  => $profiles_ids[0],
            '_entities_id'  => $entities_id,
            '_is_recursive' => $is_recursive,
        ];

        if ($password !== '') {
            $input['password'] = $password;
            $input['password2'] = $password;
        } else {
            $input['_init_password'] = 1;
        }

        $users_id = $user->add($input);
        if ($users_id) {
            foreach (array_slice($profiles_ids, 1) as $profiles_id) {
                $profile_user = new Profile_User();
                $profile_user->add([
                    'users_id'      => $users_id,
                    'profiles_id'   => $profiles_id,
                    'entities_id'   => $entities_id,
                    'is_recursive'  => $is_recursive,
                    'is_dynamic'    => 0,
                ]);
            }

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

$profile_order = [
    'AfyaDesk County Admin'        => 1,
    'AfyaDesk Sub County Admin'    => 2,
    'AfyaDesk Ward Supervisor'     => 3,
    'AfyaDesk Community Unit User' => 4,
];
$profile_lookup = [];
$profiles = [];
$profile_iterator = $DB->request([
    'SELECT' => ['id', 'name'],
    'FROM'   => Profile::getTable(),
    'ORDER'  => 'name',
]);
foreach ($profile_iterator as $row) {
    $profile_lookup[(int) $row['id']] = $row['name'];
    if (str_starts_with($row['name'], 'AfyaDesk ') || in_array($row['name'], ['Self-Service', 'Observer', 'Technician', 'Super-Admin'], true)) {
        $profiles[] = [
            'id'    => (int) $row['id'],
            'name'  => $row['name'],
            '_rank' => $profile_order[$row['name']] ?? 99,
        ];
    }
}
usort($profiles, static fn($a, $b) => [$a['_rank'], $a['name']] <=> [$b['_rank'], $b['name']]);
$profiles = array_map(static fn($profile) => ['id' => $profile['id'], 'name' => $profile['name']], $profiles);

$entity_lookup = [];
$entities = [];
$entity_iterator = $DB->request([
    'SELECT' => ['id', 'completename', 'level'],
    'FROM'   => Entity::getTable(),
    'ORDER'  => 'completename',
]);
foreach ($entity_iterator as $row) {
    if ((int) $row['id'] <= 0) {
        continue;
    }
    $entity_lookup[(int) $row['id']] = $row['completename'];
    $entities[] = [
        'id'           => (int) $row['id'],
        'completename' => $row['completename'],
        'level'        => (int) $row['level'],
    ];
}

$user_access = [];
$profile_user_iterator = $DB->request([
    'SELECT' => ['users_id', 'profiles_id', 'entities_id', 'is_recursive'],
    'FROM'   => Profile_User::getTable(),
]);
foreach ($profile_user_iterator as $row) {
    $users_id = (int) $row['users_id'];
    $profiles_id = (int) $row['profiles_id'];
    $entities_id = (int) $row['entities_id'];
    $user_access[$users_id]['profiles'][$profiles_id] = $profile_lookup[$profiles_id] ?? null;
    $user_access[$users_id]['service_areas'][$entities_id] = $entity_lookup[$entities_id] ?? null;
    $user_access[$users_id]['recursive_scopes'][] = (int) $row['is_recursive'];
}

$all_users = [];
$user_iterator = $DB->request([
    'SELECT' => ['id', 'name', 'firstname', 'realname', 'authtype'],
    'FROM'   => User::getTable(),
    'WHERE'  => ['is_deleted' => 0],
    'ORDER'  => 'name',
]);
foreach ($user_iterator as $row) {
    $profiles_list = array_values(array_filter($user_access[(int) $row['id']]['profiles'] ?? []));
    $areas_list = array_values(array_filter($user_access[(int) $row['id']]['service_areas'] ?? []));
    $recursive_scopes = $user_access[(int) $row['id']]['recursive_scopes'] ?? [];
    $display_name = trim($row['firstname'] . ' ' . $row['realname']);

    $haystack = Toolbox::strtolower(implode(' ', [
        $row['name'],
        $display_name,
        implode(' ', $profiles_list),
        implode(' ', $areas_list),
    ]));
    if ($query !== '' && !str_contains($haystack, Toolbox::strtolower($query))) {
        continue;
    }

    $all_users[] = [
        'id'            => (int) $row['id'],
        'login'         => $row['name'],
        'name'          => $display_name,
        'profiles'      => $profiles_list,
        'service_areas' => array_map(static fn($area) => preg_replace('/^Root entity > /', '', $area), $areas_list),
        'recursive'     => in_array(1, $recursive_scopes, true),
        'auth'          => ((int) $row['authtype']) === Auth::DB_GLPI ? __('Password') : __('External'),
    ];
}

$total = count($all_users);
$pages = max(1, (int) ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;
$users = array_slice($all_users, $offset, $limit);
$logged_user_name = trim((string) ($_SESSION['glpifriendlyname'] ?? ''));
if ($logged_user_name === '') {
    $logged_user_name = trim((string) (($_SESSION['glpifirstname'] ?? '') . ' ' . ($_SESSION['glpirealname'] ?? '')));
}
if ($logged_user_name === '') {
    $logged_user_name = (string) ($_SESSION['glpiname'] ?? __('User'));
}

Html::nullHeader(__('AfyaDesk user management'));
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
    'logged_user_name' => $logged_user_name,
]);
Html::nullFooter();
