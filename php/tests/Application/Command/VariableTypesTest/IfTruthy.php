<?php

namespace A;

/**
 * @param B|null $b
 */
function foo(B $b = null)
{
    if ($b) {
        // <MARKER>
    }
}
