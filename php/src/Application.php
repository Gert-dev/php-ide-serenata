<?php

namespace PhpIntegrator;

use Exception;

use Doctrine\DBAL\Exception\DriverException;

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
            '--class-list'          => 'ClassList',
            '--class-info'          => 'ClassInfo',
            '--functions'           => 'GlobalFunctions',
            '--constants'           => 'GlobalConstants',
            '--reindex'             => 'Reindex',
            '--resolve-type'        => 'ResolveType',
            '--semantic-lint'       => 'SemanticLint',
            '--available-variables' => 'AvailableVariables',
            '--variable-type'       => 'VariableType'
        ];

        if (isset($commands[$command])) {
            $className = "\\PhpIntegrator\\Application\\Command\\{$commands[$command]}";

            /** @var \PhpIntegrator\Application\CommandInterface $command */
            $command = new $className();

            try {
                return $command->execute($arguments);
            } catch (DriverException $e) {
                $message = "A driver exception occurred. Please check if support for sqlite is present.";
                $message .= "\n \n";
                $message .= $e->getFile() . ':' . $e->getLine() . ' - ' . $e->getMessage();

                return $message;
            } catch (Exception $e) {
                return $e->getFile() . ':' . $e->getLine() . ' - ' . $e->getMessage();
            }
        }

        $supportedCommands = implode(', ', array_keys($commands));

        echo "Unknown command {$command}, supported commands: {$supportedCommands}";
    }
}
