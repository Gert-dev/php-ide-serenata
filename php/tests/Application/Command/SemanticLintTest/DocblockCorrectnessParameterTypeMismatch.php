<?php

namespace A;

/**
 * @param mixed          $param1
 * @param \DateTime|null $param2
 * @param int[]          $param3
 */
function some_function_correct($param1, \DateTime $param2, array $param3)
{

}

/**
 * @param \Traversable $param1
 */
function some_function_parameter_incorrect_type(\DateTime $param1)
{

}
