<?php

namespace PhpIntegrator;

class ReindexProvider extends Tools implements ProviderInterface
{
    /**
     * Attempts to rebuild the entire Composer class map and return it.
     *
     * @return array
     */
    protected function buildClassMap()
    {
        $classMapScript = Config::get('classMapScript');

        if (!$classMapScript || !file_exists($classMapScript)) {
            return [];
        }

        if (is_executable(Config::get('composer'))) {
            exec(sprintf('%s dump-autoload --optimize --quiet --no-interaction --working-dir=%s 2>&1',
                escapeshellarg(Config::get('composer')),
                escapeshellarg(Config::get('projectPath'))
            ));
        } else {
            exec(sprintf('%s %s dump-autoload --optimize --quiet --no-interaction --working-dir=%s 2>&1',
                escapeshellarg(Config::get('php')),
                escapeshellarg(Config::get('composer')),
                escapeshellarg(Config::get('projectPath'))
            ));
        }

        return require($classMapScript);
    }

    /**
     * {@inheritDoc}
     */
    public function execute(array $args = [])
    {
        $fileToReindex = array_shift($args);

        if (!$fileToReindex) {
            $index = [];
            $provider = new ClassInfoProvider();

            foreach (get_declared_classes() as $class) {
                if ($value = $provider->execute([$class])) {
                    $index[$class] = $value;
                }
            }

            foreach ($this->buildClassMap() as $class => $filePath) {
                if ($value = $this->execClassInfoFetch($class)) {
                    $index[$class] = $value;
                }
            }
        } elseif (file_exists(Config::get('indexClasses'))) {
            $index = json_decode(file_get_contents(Config::get('indexClasses')), true);

            if ($index !== false) {
                $found = false;
                $fileParser = new FileParser($fileToReindex);
                $class = $fileParser->getFullClassName(null, $found);

                if ($found) {
                    if (isset($index[$class])) {
                        unset($index[$class]);
                    }

                    if ($value = $this->execClassInfoFetch($class)) {
                        $index[$class] = $value;
                    }
                }
            }
        }

        file_put_contents(Config::get('indexClasses'), json_encode($index));

        return [
            'success' => true
        ];
    }

    /**
     * Fetches class information through a separate PHP process to ensure that errors inside the file being scanned do
     * not propagate to the current process.
     *
     * @param string $class The name of the class to fetch information about.
     */
    protected function execClassInfoFetch($class)
    {
        $response = exec(sprintf('%s %s %s --class-info %s',
            escapeshellarg(Config::get('php')),
            escapeshellarg(__DIR__ . '/../Main.php'),
            escapeshellarg(Config::get('projectPath')),
            escapeshellarg($class)
        ));

        $response = json_decode($response, true);

        if ($response === false || !$response['wasFound']) {
            return null;
        }

        return $response;
    }
}
