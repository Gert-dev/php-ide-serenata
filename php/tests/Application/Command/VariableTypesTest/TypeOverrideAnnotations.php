<?php

namespace A;

$a = new \DateTime();
$b = new \DateTime();
$c = new \DateTime();

/** @var \Traversable $a */
$a = new \DateTimeImmutable();

/** @var $b \Traversable */
$b = new \DateTimeImmutable();

/** @var C $c */
$c = new \DateTimeImmutable();

// <MARKER>
