<?php

class Bar
{
    public function test(\Traversable $queue, \DateTime $data)
    {
        $data = $data->getOffset();

        die(var_dump($data->getData())); // <MARKER>
    }
}
