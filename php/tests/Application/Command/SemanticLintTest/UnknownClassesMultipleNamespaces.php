<?php

namespace A
{
    use SplFileInfo;

    $a = new DateTime();
}

namespace B
{
    use DateTime;

    $a = new SplFileInfo();
}
