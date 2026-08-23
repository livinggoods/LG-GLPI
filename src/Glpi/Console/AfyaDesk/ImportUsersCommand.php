<?php

namespace Glpi\Console\AfyaDesk;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

class ImportUsersCommand extends AbstractAfyaDeskScriptCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('afyadesk:import-users');
        $this->setDescription(__('Import AfyaDesk users and service-area authorizations from CSV'));
        $this->addArgument('csv_file', InputArgument::REQUIRED, __('CSV file to import'));
    }

    protected function getScriptName(): string
    {
        return 'import-afyadesk-users.php';
    }

    protected function getScriptArguments(InputInterface $input): array
    {
        return [(string) $input->getArgument('csv_file')];
    }
}
