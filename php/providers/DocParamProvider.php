<?php

namespace PhpIntegrator;

class DocParamProvider extends Tools implements ProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function execute(array $args = [])
    {
        $class = $args[0];
        $name  = $args[1];

        if (strpos($class, '\\') == 0) {
            $class = substr($class, 1);
        }

        $parser = new DocParser();
        
        return $parser->get($class, 'method', $name, [DocParser::PARAM_TYPE]);
    }
}

?>
