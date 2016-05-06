<?php

namespace A;

function test(D $d)
{
    $a = 5;
    $b = new B();
    $e = new E();

    $closure = function () use ($b, $d, $e) {
        $c = new C();

        $test = function () use ($b, $c, $d) {
            // <MARKER>
        };
    };
}
