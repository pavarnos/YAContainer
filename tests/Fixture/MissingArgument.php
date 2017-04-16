<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

class MissingArgument
{
    /**
     */
    public function __construct(NoSuchClass $causesError)
    {
    }
}