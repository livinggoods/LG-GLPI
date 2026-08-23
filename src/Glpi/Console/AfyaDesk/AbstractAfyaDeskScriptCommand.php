<?php

namespace Glpi\Console\AfyaDesk;

use Glpi\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class AbstractAfyaDeskScriptCommand extends AbstractCommand
{
    abstract protected function getScriptName(): string;

    protected function getScriptArguments(InputInterface $input): array
    {
        return [];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = array_merge(
            [PHP_BINARY, GLPI_ROOT . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . $this->getScriptName()],
            $this->getScriptArguments($input)
        );

        $process = new Process($command, GLPI_ROOT);
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        return $process->getExitCode() ?? 1;
    }
}
