<?php

class Bar
{
    public function test(\DateTime $data)
    {
        $data = $data->getOffset();

        // <MARKER>
    }
}
