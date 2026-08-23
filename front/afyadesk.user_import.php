<?php

use Glpi\Application\View\TemplateRenderer;

include('../inc/includes.php');

Session::checkRight('user', CREATE);

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCSRF($_POST);

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = __('Upload a valid CSV file.');
    } else {
        $tmp_file = $_FILES['csv_file']['tmp_name'];
        $command = [
            PHP_BINARY,
            GLPI_ROOT . '/tools/import-afyadesk-users.php',
            $tmp_file,
        ];
        $process = new Symfony\Component\Process\Process($command, GLPI_ROOT);
        $process->setTimeout(null);
        $process->run();
        $result = trim($process->getOutput());
        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: __('Unable to import users.');
        }
    }
}

Html::header(__('AfyaDesk user import'), $_SERVER['PHP_SELF'], 'admin', 'user');
TemplateRenderer::getInstance()->display('pages/admin/afyadesk_user_import.html.twig', [
    'result' => $result,
    'error'  => $error,
]);
Html::footer();
