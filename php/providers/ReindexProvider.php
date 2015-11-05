<?php

namespace PhpIntegrator;

class ReindexProvider extends Tools implements ProviderInterface
{
    /**
     * @var ClassInfoProvider
     */
    protected $classInfoProvider = null;

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
        $isSuccessful = true;
        $fileToReindex = array_shift($args);

        if (!$fileToReindex) {
            $index = [];

            foreach (get_declared_classes() as $class) {
                if ($value = $this->fetchClassInfo($class, false)) {
                    $index[$class] = $value;
                }
            }

            foreach ($this->buildClassMap() as $class => $filePath) {
                if ($value = $this->fetchClassInfo($class, true)) {
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

                    $value = $this->fetchClassInfo($class, true);

                    if ($value) {
                        $index[$class] = $value;
                    } else {
                        $isSuccessful = false;
                    }
                }
            }
        }

        file_put_contents(Config::get('indexClasses'), json_encode($index));

        return [
            'success' => $isSuccessful,
            'result'  => null
        ];
    }

    /**
     * Fetches class information, optionally through a separate process.
     *
     * @param string $class              The name of the class to fetch information about.
     * @param bool   $useSeparateProcess Whether to spawn a separate PHP process or not (to contain fatal errors).
     */
    protected function fetchClassInfo($class, $useSeparateProcess)
    {
        $data = null;

        if ($useSeparateProcess) {
            $response = exec(sprintf('%s %s %s --class-info %s',
                escapeshellarg(Config::get('php')),
                escapeshellarg(__DIR__ . '/../Main.php'),
                escapeshellarg(Config::get('projectPath')),
                escapeshellarg($class)
            ));

            $response = json_decode($response, true);

            if ($response && $response['result']['wasFound']) {
                $data = $response['result'];
            }
        } else {
            $data = $this->getClassInfoProvider()->execute([$class]);
            $data = $data['result'];
        }

        if ($data) {
            $constructor = isset($data['methods']['__construct']) ? $data['methods']['__construct'] : null;

            unset($data['constants'], $data['methods'], $data['properties']);

            if ($constructor) {
                $data['methods'] = [
                    '__construct' => $constructor
                ];
            }
        }

        return $data;
    }

    /**
     * @return ClassInfoProvider
     */
    protected function getClassInfoProvider()
    {
        if (!$this->classInfoProvider) {
            $this->classInfoProvider = new ClassInfoProvider();
        }

        return $this->classInfoProvider;
    }
}
