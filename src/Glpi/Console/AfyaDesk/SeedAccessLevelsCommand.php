<?php

namespace Glpi\Console\AfyaDesk;

class SeedAccessLevelsCommand extends AbstractAfyaDeskScriptCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('afyadesk:seed-access-levels');
        $this->setDescription(__('Seed AfyaDesk access-level profiles'));
    }

    protected function getScriptName(): string
    {
        return 'seed-afyadesk-access-levels.php';
    }
}
