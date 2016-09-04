<?php

namespace PhpIntegrator\UserInterface;

use Exception;
use UnexpectedValueException;

use Doctrine\Common\Cache\FilesystemCache;

use PhpIntegrator\Parsing\CachingParserProxy;

use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\ParserFactory;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Main application class.
 */
class Application
{
    /**
     * @var string
     */
    protected $projectName;

    /**
     * Handles the application process.
     *
     * @param array $arguments The arguments to pass.
     *
     * @return mixed
     */
    public function handle(array $arguments)
    {
        if (count($arguments) < 3) {
            throw new UnexpectedValueException(
                'Not enough argument supplied. Usage: . <project> <command> [<addtional parameters>]'
            );
        }

        $programName = array_shift($arguments);
        $this->projectName = array_shift($arguments);
        $command = array_shift($arguments);

        // This seems to be needed for GetOptionKit.
        array_unshift($arguments, $programName);

        $commandServiceMap = [
            '--initialize'          => 'initializeCommand',
            '--reindex'             => 'reindexCommand',
            '--vacuum'              => 'vacuumCommand',
            '--truncate'            => 'truncateCommand',

            '--class-list'          => 'classListCommand',
            '--class-info'          => 'classInfoCommand',
            '--functions'           => 'globalFunctionsCommand',
            '--constants'           => 'globalConstantsCommand',
            '--resolve-type'        => 'resolveTypeCommand',
            '--localize-type'       => 'localizeTypeCommand',
            '--semantic-lint'       => 'semanticLintCommand',
            '--available-variables' => 'availableVariablesCommand',
            '--deduce-types'        => 'deduceTypesCommand',
            '--invocation-info'     => 'invocationInfoCommand'
        ];

        if (isset($commandServiceMap[$command])) {
            /** @var \PhpIntegrator\UserInterface\Command\CommandInterface $command */
            $command = $this->getContainer()->get($commandServiceMap[$command]);

            if (interface_exists('Throwable')) {
                // PHP >= 7.
                try {
                    return $command->execute($arguments);
                } catch (\Throwable $e) {
                    return $e->getFile() . ':' . $e->getLine() . ' - ' . $e->getMessage();
                }
            } else {
                // PHP < 7
                try {
                    return $command->execute($arguments);
                } catch (Exception $e) {
                    return $e->getFile() . ':' . $e->getLine() . ' - ' . $e->getMessage();
                }
            }
        }

        $supportedCommands = implode(', ', array_keys($commandServiceMap));

        echo "Unknown command {$command}, supported commands: {$supportedCommands}";
    }

    /**
     * @return ContainerBuilder
     */
    protected function getContainer()
    {
        $container = new ContainerBuilder();

        $container
            ->register('application', Application::class)
            ->setSynthetic(true);

        $container->set('application', $this);

        $container
            ->register('lexer', Lexer::class)
            ->addArgument([
                'usedAttributes' => [
                    'comments', 'startLine', 'endLine', 'startFilePos', 'endFilePos'
                ]
            ]);

        $container
            ->register('phpParserFactory', ParserFactory::class);

        $container
            ->register('parser.phpParser', Parser::class)
            ->setFactory([new Reference('phpParserFactory'), 'create'])
            ->setArguments([ParserFactory::PREFER_PHP7, new Reference('lexer'), [
                'throwOnError' => false
            ]]);

        $container
            ->register('parser', CachingParserProxy::class)
            ->addArgument(new Reference('parser.phpParser'));

        $container
            ->register('cache', FilesystemCache::class)
            ->setArguments([$this->getCacheDirectory()]);

        $container
            ->register('initializeCommand', Command\InitializeCommand::class)
            ->setArguments([new Reference('parser'), new Reference('cache')]);

        $container
            ->register('reindexCommand', Command\ReindexCommand::class)
            ->setArguments([new Reference('parser'), new Reference('cache')]);

        $container
            ->register('vacuumCommand', Command\VacuumCommand::class)
            ->setArguments([new Reference('parser'), new Reference('cache')]);

        $container
            ->register('truncateCommand', Command\TruncateCommand::class)
            ->setArguments([new Reference('parser'), new Reference('cache')]);

        $container
            ->register('classListCommand', Command\ClassListCommand::class)
            ->setArguments([new Reference('parser'), new Reference('cache')]);

        $container
            ->register('classInfoCommand', Command\ClassInfoCommand::class)
            ->setArguments([new Reference('parser'), new Reference('cache')]);

        $container
            ->register('globalFunctionsCommand', Command\GlobalFunctionsCommand::class)
            ->setArguments([new Reference('parser'), new Reference('cache')]);

        $container
            ->register('globalConstantsCommand', Command\GlobalConstantsCommand::class)
            ->setArguments([new Reference('parser'), new Reference('cache')]);

        $container
            ->register('resolveTypeCommand', Command\ResolveTypeCommand::class)
            ->setArguments([new Reference('parser'), new Reference('cache')]);

        $container
            ->register('localizeTypeCommand', Command\LocalizeTypeCommand::class)
            ->setArguments([new Reference('parser'), new Reference('cache')]);

        $container
            ->register('semanticLintCommand', Command\SemanticLintCommand::class)
            ->setArguments([new Reference('parser'), new Reference('cache')]);

        $container
            ->register('availableVariablesCommand', Command\AvailableVariablesCommand::class)
            ->setArguments([new Reference('parser'), new Reference('cache')]);

        $container
            ->register('deduceTypesCommand', Command\DeduceTypesCommand::class)
            ->setArguments([new Reference('parser'), new Reference('cache')]);

        $container
            ->register('invocationInfoCommand', Command\InvocationInfoCommand::class)
            ->setArguments([new Reference('parser'), new Reference('cache')]);

        return $container;
    }

    /**
     * @return string
     */
    protected function getCacheDirectory()
    {
        $cachePath = sys_get_temp_dir() .
            '/php-integrator-base/' .
            $this->projectName . '/' .
            Command\AbstractCommand::DATABASE_VERSION .
            '/';

        if (!file_exists($cachePath)) {
            mkdir($cachePath, 0777, true);
        }

        return $cachePath;
    }
}
