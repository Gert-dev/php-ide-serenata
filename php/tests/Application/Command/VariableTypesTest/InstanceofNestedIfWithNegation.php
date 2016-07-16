<?php

namespace A;

/**
 * @param B|A $b
 */
function foo($b)
{
    if ($b instanceof B) {
        if (!$b instanceof A) {
            // <MARKER>
        }
    }
}
