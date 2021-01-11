<?php
declare(strict_types=1);

namespace LSS\YAContainer;

class ContainerException extends \Exception implements \Psr\Container\ContainerExceptionInterface
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