<?php

namespace A;

define('DEFINE_CONSTANT', 'some default value');
define('A\DEFINE_CONSTANT_NAMESPACED', 3);

const FIRST_CONSTANT = 5;
const SECOND_CONSTANT = 'Test';

class B
{
    const SHOULD_NOT_SHOW_UP = 3;
}
