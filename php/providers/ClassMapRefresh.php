<?php

namespace PhpIntegrator;

class ClassMapRefresh extends Tools implements ProviderInterface
{
    /**
     * Returns the classMap from composer.
     * Fetch it from the command dump-autoload if needed
     * @param bool $force Force to fetch it from the command
     * @return array
     */
    protected function buildClassMap()
    {
        $classMapScript = Config::get('classMapScript');

        if (!$classMapScript || !file_exists($classMapScript)) {
            return [];
        }

        // Check if composer is executable or not
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
     * Execute the command
     * @param  array  $args Arguments gived to the command
     * @return array Response
     */
    public function execute($args = array())
    {
        $indexFileExists = false;

        $fileToReindex = array_shift($args);

        // If we specified a file
        if ($fileToReindex) {
            if (file_exists(Config::get('indexClasses'))) {
                $index = json_decode(file_get_contents(Config::get('indexClasses')), true);

                // Invalid json (#24)
                if (false !== $index) {
                    $indexFileExists = true;

                    $found = false;
                    $fileParser = new FileParser($fileToReindex);
                    $class = $fileParser->getFullClassName(null, $found);

                    if ($found) {
                        if (isset($index['mapping'][$class])) {
                            unset($index['mapping'][$class]);
                        }

                        if (false !== $key = array_search($class, $index['autocomplete'])) {
                            unset($index['autocomplete'][$key]);
                            $index['autocomplete'] = array_values($index['autocomplete']);
                        }

                        if ($value = $this->buildIndexClass($class)) {
                            $index['mapping'][$class] = array('methods' => $value);
                            $index['autocomplete'][] = $class;
                        }
                    }
                }
            }
        }

        // Otherwise, full index
        if (!$indexFileExists) {
            // Autoload classes
            foreach ($this->buildClassMap() as $class => $filePath) {
                if ($value = $this->buildIndexClass($class)) {
                    $index['mapping'][$class] = $value;
                    $index['autocomplete'][] = $class;
                }
            }

            // Internal classes
            foreach (get_declared_classes() as $class) {
                $provider = new ClassProvider();

                if ($value = $provider->execute(array($class, true))) {
                    if (!empty($value)) {
                        $index['mapping'][$class] = $value;
                        $index['autocomplete'][] = $class;
                    }
                }
            }
        }

        file_put_contents(Config::get('indexClasses'), json_encode($index));

        return array();
    }

    protected function buildIndexClass($class)
    {
        $ret = exec(sprintf('%s %s %s --class %s',
            escapeshellarg(Config::get('php')),
            escapeshellarg(__DIR__ . '/../Main.php'),
            escapeshellarg(Config::get('projectPath')),
            escapeshellarg($class)
        ));

        if (false === $value = json_decode($ret, true)) {
            return null;
        }

        if (isset($value['error'])) {
            return null;
        }

        return $value;
    }
}
