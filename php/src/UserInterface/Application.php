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

        $commands = [
            '--initialize'          => 'Initialize',
            '--reindex'             => 'Reindex',
            '--vacuum'              => 'Vacuum',
            '--truncate'            => 'Truncate',

            '--class-list'          => 'ClassList',
            '--class-info'          => 'ClassInfo',
            '--functions'           => 'GlobalFunctions',
            '--constants'           => 'GlobalConstants',
            '--resolve-type'        => 'ResolveType',
            '--localize-type'       => 'LocalizeType',
            '--semantic-lint'       => 'SemanticLint',
            '--available-variables' => 'AvailableVariables',
            '--deduce-types'        => 'DeduceTypes',
            '--invocation-info'     => 'InvocationInfo'
        ];

        if (isset($commands[$command])) {
            $className = "\\PhpIntegrator\\UserInterface\\Command\\{$commands[$command]}Command";

            /** @var \PhpIntegrator\UserInterface\Command\CommandInterface $command */
            $command = new $className(
                $this->getContainer()->get('parser'),
                $this->getContainer()->get('cache')
            );

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

        $supportedCommands = implode(', ', array_keys($commands));

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
            ->register('parser.phpParser', Parser::class)
            ->setFactory([ParserFactory::class, 'create'])
            ->setArguments([ParserFactory::PREFER_PHP7, new Reference('lexer'), [
                'throwOnError' => false
            ]]);

        $container
            ->register('parser', CachingParserProxy::class)
            ->addArgument(new Reference('parser.phpParser'));

        $container
            ->register('cache', FilesystemCache::class)
            ->setArguments([$this->getCacheDirectory()]);

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
