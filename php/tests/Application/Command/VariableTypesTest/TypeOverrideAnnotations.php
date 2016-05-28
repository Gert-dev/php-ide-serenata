<?php

namespace A;

$a = new \DateTime();
$b = new \DateTime();
$c = new \DateTime();

/** @var \Traversable $a */
$a = new \DateTimeImmutable();

/** @var $b \Traversable */
$b = new \DateTimeImmutable();

/** @var C|null $c This is a description. */
$c = new \DateTimeImmutable();

/**
 * This should not interfere with analysis.
 *
 * @var D $d A description.
 */
$d = new \DateTimeImmutable();

// <MARKER>
