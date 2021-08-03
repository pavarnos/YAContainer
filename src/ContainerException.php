<?php
declare(strict_types=1);

namespace LSS\YAContainer;

use Psr\Container\ContainerExceptionInterface;

class ContainerException extends \Exception implements ContainerExceptionInterface
{
    /** @var string[] */
    public array $dependencyChain = [];

    public function __construct(array $building, string $message = '', int $code = 0, \Exception $previous = null)
    {
        $this->dependencyChain = array_flip($building);
        ksort($this->dependencyChain);
        $message .= ': while building ' . implode(' - ', $this->dependencyChain);

        parent::__construct($message, $code, $previous);
    }
}