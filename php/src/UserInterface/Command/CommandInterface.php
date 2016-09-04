<?php

namespace PhpIntegrator\UserInterface\Command;

/**
 * Interface for commands.
 */
interface CommandInterface
{
    /**
     * Executes the command.
     *
     * @param array $processedArguments
     *
     * @return string Output to return to the user.
     */
    public function execute(array $processedArguments);
}
