<?php

namespace {
    class Foo {}
    class Bar {}
}

namespace A
{
    use Foo;

    $a = new DateTime();
}

namespace B
{
    use Bar;

    $a = new SplFileInfo();
}
