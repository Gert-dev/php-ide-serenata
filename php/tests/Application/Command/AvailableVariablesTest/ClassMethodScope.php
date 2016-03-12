<?php

namespace A;

$var1 = 5;

function foo($param)
{
    $nestedVar = null;
}

$var2 = 3;

class Foo
{
    public function test($param1, $param2)
    {
        $closure = function ($closureParam) use ($something) {
            $test = 3;
        };

        // <MARKER>
    }
}
