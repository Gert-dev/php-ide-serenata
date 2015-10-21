<?php

namespace PhpIntegrator;

class DocParamProvider extends Tools implements ProviderInterface
{
    /**
     * Execute the command
     * @param  array  $args Arguments gived to the command
     * @return array Response
     */
    public function execute($args = array())
    {
        $class = $args[0];
        $name  = $args[1];

        if (strpos($class, '\\') == 0) {
            $class = substr($class, 1);
        }

        $parser = new DocParser();
        return $parser->get($class, 'method', $name, array(DocParser::PARAM_TYPE));
    }
}

?>
