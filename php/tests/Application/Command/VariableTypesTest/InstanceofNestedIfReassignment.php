<?php

namespace A;

if ($b instanceof B) {
    $b = new Bar();

    if ($b instanceof A) {
        // <MARKER>
    }
}
