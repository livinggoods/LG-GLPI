<?php

namespace Glpi\Console\AfyaDesk;

class ExportUserAccessAuditCommand extends AbstractAfyaDeskScriptCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('afyadesk:export-user-access-audit');
        $this->setDescription(__('Export AfyaDesk user access assignments as CSV'));
    }

    protected function getScriptName(): string
    {
        return 'export-afyadesk-user-access-audit.php';
    }
}
