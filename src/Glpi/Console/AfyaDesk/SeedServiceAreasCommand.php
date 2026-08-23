<?php

namespace Glpi\Console\AfyaDesk;

class SeedServiceAreasCommand extends AbstractAfyaDeskScriptCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('afyadesk:seed-service-areas');
        $this->setDescription(__('Seed AfyaDesk County, Sub County, Ward, and Community Health Unit entities'));
    }

    protected function getScriptName(): string
    {
        return 'seed-afyadesk-service-areas.php';
    }
}
