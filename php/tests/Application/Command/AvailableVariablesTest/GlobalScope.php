<?php

$var1 = 5;

function foo($param)
{
    $nestedVar = null;
}

$var2 = 3;

function test($param1, $param2)
{
    $closure = function ($closureParam) use ($something) {
        $test = 3;
    };
}

$var3 = 3809;

// <MARKER>
