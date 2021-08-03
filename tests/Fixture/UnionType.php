<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   04 Aug 2021
 */

declare(strict_types=1);

namespace LSS\YAContainer\Fixture;

class UnionType
{
    public function __construct(ElectricEngine|string $theParameter)
    {
    }
}