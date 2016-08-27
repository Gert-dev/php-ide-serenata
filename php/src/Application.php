<?php

namespace PhpIntegrator;

use Exception;
use UnexpectedValueException;

use Doctrine\Common\Cache\FilesystemCache;

use PhpIntegrator\Parsing\CachingParserProxy;

use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Main application class.
 */
class Application
{
    /**
     * @var FilesystemCache
     */
    protected $filesystemCache;

    /**
     * @var CachingParserProxy
     */
    protected $cachingParserProxy;

    /**
     * @var Parser
     */
    protected $parser;

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
            throw new UnexpectedValueException('Not enough argument supplied. Usage: . <project> <command> [<addtional parameters>]');
        }

        $programName = array_shift($arguments);
        $projectName = array_shift($arguments);
        $command = array_shift($arguments);

        // This seems to be needed for GetOptionKit.
        array_unshift($arguments, $programName);

        $commands = [
            '--class-list'          => 'ClassList',
            '--class-info'          => 'ClassInfo',
            '--functions'           => 'GlobalFunctions',
            '--constants'           => 'GlobalConstants',
            '--reindex'             => 'Reindex',
            '--resolve-type'        => 'ResolveType',
            '--localize-type'       => 'LocalizeType',
            '--semantic-lint'       => 'SemanticLint',
            '--available-variables' => 'AvailableVariables',
            '--deduce-types'        => 'DeduceTypes',
            '--invocation-info'     => 'InvocationInfo',
            '--truncate'            => 'Truncate'
        ];

        if (isset($commands[$command])) {
            $className = "\\PhpIntegrator\\Application\\Command\\{$commands[$command]}";

            /** @var \PhpIntegrator\Application\Command\CommandInterface $command */
            $command = new $className(
                $this->getCachingParserProxy(),
                $this->getFilesystemCache($projectName)
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
     * Retrieves an instance of FilesystemCache. The object will only be created once if needed.
     *
     * @param string $project
     *
     * @return FilesystemCache
     */
    protected function getFilesystemCache($project)
    {
        if (!$this->filesystemCache instanceof FilesystemCache) {
            $cachePath = sys_get_temp_dir() .
                '/php-integrator-base/' .
                $project . '/' .
                Application\Command\AbstractCommand::DATABASE_VERSION .
                '/';

            if (!file_exists($cachePath)) {
                mkdir($cachePath, 0777, true);
            }

            $this->filesystemCache = new FilesystemCache($cachePath);
        }

        return $this->filesystemCache;
    }

    /**
     * @return CachingParserProxy
     */
    protected function getCachingParserProxy()
    {
        if (!$this->cachingParserProxy instanceof CachingParserProxy) {
            $this->cachingParserProxy = new CachingParserProxy($this->getParser());
        }

        return $this->cachingParserProxy;
    }

    /**
     * @return Parser
     */
    protected function getParser()
    {
        if (!$this->parser) {
            $lexer = new Lexer([
                'usedAttributes' => [
                    'comments', 'startLine', 'endLine', 'startFilePos', 'endFilePos'
                ]
            ]);

            $parserFactory = new ParserFactory();

            $this->parser = $parserFactory->create(ParserFactory::PREFER_PHP7, $lexer, [
                'throwOnError' => false
            ]);
        }

        return $this->parser;
    }
}
