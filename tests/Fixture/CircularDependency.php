<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

class CircularDependency implements CircularDependencyInterface
{
    /**
     * @param \LSS\YAContainer\Fixture\CircularDependency $oops
     */
    public function __construct(CircularDependency $oops)
    {
    }
}