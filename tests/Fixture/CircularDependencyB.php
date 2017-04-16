<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

class CircularDependencyB implements CircularDependencyInterface
{
    /**
     * @param \LSS\YAContainer\Fixture\CircularDependencyC $oops
     */
    public function __construct(CircularDependencyC $oops)
    {
    }
}