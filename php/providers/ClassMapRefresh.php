<?php

namespace PhpIntegrator;

class ClassMapRefresh extends Tools implements ProviderInterface
{
    /**
     * Execute the command
     * @param  array  $args Arguments gived to the command
     * @return array Response
     */
    public function execute($args = array())
    {
        $fileExists = false;

        // If we specified a file
        if (count($args) > 0 && $file = $args[0]) {
            if (file_exists(Config::get('indexClasses'))) {
                $index = json_decode(file_get_contents(Config::get('indexClasses')), true);

                // Invalid json (#24)
                if (false !== $index) {
                    $fileExists = true;

                    $found = false;
                    $fileParser = new FileParser($file);
                    $class = $fileParser->getFullClassName(null, $found);

                    // if (false !== $class = array_search($file, $classMap)) {
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
        if (!$fileExists) {
            // Autoload classes
            foreach ($this->getClassMap(true) as $class => $filePath) {
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
            escapeshellarg(__DIR__ . '/../parser.php'),
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
