<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

class CircularDependencyA implements CircularDependencyInterface
{
    /**
     * @param \LSS\YAContainer\Fixture\CircularDependencyB $oops
     */
    public function __construct(CircularDependencyB $oops)
    {
    }
}