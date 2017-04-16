<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

class CircularDependencyC implements CircularDependencyInterface
{
    /**
     * @param \LSS\YAContainer\Fixture\CircularDependencyA $oops
     */
    public function __construct(CircularDependencyA $oops)
    {
    }
}