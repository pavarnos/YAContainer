<?php
/**
 * @file
 * @author Lightly Salted Software Ltd
 * @date   05 Feb 2022
 */

declare(strict_types=1);

namespace LSS\YAContainer;

interface ContainerInterface
{
    /**
     * Finds an entry of the container by its identifier and returns it.
     * @template T of object
     * @param class-string<T> $id class name of the entry to look for.
     * @return object
     * @phpstan-return T
     * @throws ContainerException
     */
    public function get(string $id): object;

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     * @param class-string $id Identifier of the entry to look for.
     * @return bool
     */
    public function has(string $id): bool;
}
