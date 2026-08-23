<?php

namespace Glpi\Console\AfyaDesk;

class SeedWhatsappTemplatesCommand extends AbstractAfyaDeskScriptCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('afyadesk:seed-whatsapp-templates');
        $this->setDescription(__('Seed AfyaDesk WhatsApp notification templates'));
    }

    protected function getScriptName(): string
    {
        return 'seed-afyadesk-whatsapp-templates.php';
    }
}
