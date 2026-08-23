<?php

include('../inc/includes.php');

Session::checkRight('user', CREATE);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="afyadesk-users-import-template.csv"');
readfile(GLPI_ROOT . '/tools/afyadesk-users-import-template.csv');
