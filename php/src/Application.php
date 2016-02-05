<?php

namespace PhpIntegrator;

use Exception;
use UnexpectedValueException;

/**
 * Main application class.
 */
class Application
{
    /**
     * Handles the application process.
     *
     * @param array $arguments The arguments to pass.
     *
     * @return mixed
     */
    public function handle(array $arguments)
    {
        $command = array_shift($arguments);

        $commands = [
            '--class-list' => 'ClassList',
            '--class-info' => 'ClassInfo',
            '--functions'  => 'GlobalFunctions',
            '--constants'  => 'GlobalConstants',
            '--reindex'    => 'Reindex'
        ];

        if (isset($commands[$command])) {
            $className = "\\PhpIntegrator\\Application\\Command\\{$commands[$command]}";

            /** @var \PhpIntegrator\Application\CommandInterface $command */
            $command = new $className();

            try {
                return $command->execute($arguments);
            } catch (Exception $e) {
                return $e->getFile() . ':' . $e->getLine() . ' - ' . $e->getMessage();
            }
        }

        $supportedCommands = implode(', ', array_keys($commands));

        echo "Unknown command {$command}, supported commands: {$supportedCommands}";
    }
}
