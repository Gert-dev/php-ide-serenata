<?php

namespace PhpIntegrator\Analysis;

use Exception;

/**
 * Indicates a circular dependency between classlikes (i.e. a class extending itself or an interface implementing
 * itself).
 */
class CircularDependencyException extends Exception
{

}
