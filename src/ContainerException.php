<?php
declare(strict_types=1);

namespace LSS\YAContainer;

class ContainerException extends \Exception implements \Psr\Container\ContainerExceptionInterface
{
    public $dependencyChain = [];

    public function __construct(array $building, $message = "", $code = 0, \Exception $previous = null)
    {
        $this->dependencyChain = array_flip($building);
        ksort($this->dependencyChain);

        parent::__construct($message, $code, $previous);
    }
}