<?php

class Bar
{
    /**
     * @var string|int|Foo|Bar
     */
    public $testProperty;
}

$bar = new Bar();
$a = $bar->testProperty;

// <MARKER>
