<?php

namespace A;

/**
 * @param A|B $b
 */
function foo($b)
{
    if (!$b instanceof B) {
        // <MARKER>
    }
}
