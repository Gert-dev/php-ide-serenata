<?php

namespace A;

/**
 * @Annotation
 */
class Foo
{

}

/**
 * @throws string|A\B|A\C[]|int
 * @throws string
 * @throws mixed
 * @throws \A\Foo
 *
 * @api
 *
 * @Foo
 * @MissingAnnotationClass
 * @A\MissingAnnotationClass
 * @\B\MissingAnnotationClass
 *
 * @author Some name <test@testdomain.com>
 */
function foo()
{

}
