<?php

$a1 = new A();
$a2 = new A();

$a = true ? $a1 : $a2;

$b1 = new B();
$b2 = new \B();

$b = $b1 ?: $b2;

// <MARKER>
