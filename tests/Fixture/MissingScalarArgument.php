<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   16/04/17
 */

namespace LSS\YAContainer\Fixture;

class MissingScalarArgument
{
    /**
     * MissingScalarArgument constructor.
     * @param string $unknownScalarValue
     * @param int    $defaultValue
     */
    public function __construct(string $unknownScalarValue, $defaultValue = 42)
    {
    }
}