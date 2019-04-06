<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Core\Operation;

/**
 * @internal
 *
 * @author Torrey Tsui <torreytsui@gmail.com>
 */
final class SubresourceOperation
{
    private $property;
    private $collection;
    private $method;
    private $operationName;
    private $identifiers;
    private $routeName;
    private $path;
    private $resourceClass;
    private $shortNames;
    private $defaults;
    private $requirements;
    private $options;
    private $host;
    private $schemes;
    private $condition;
    private $controller;

    public function __construct(
        string $property,
        bool $collection,
        string $method,
        string $operationName,
        array $identifiers,
        string $routeName,
        string $path,
        string $resourceClass,
        array $shortNames,
        array $defaults,
        array $requirements,
        array $options,
        string $host,
        array $schemes,
        string $condition,
        ?string $controller
    ) {
        $this->property = $property;
        $this->collection = $collection;
        $this->method = $method;
        $this->operationName = $operationName;
        $this->identifiers = $identifiers;
        $this->routeName = $routeName;
        $this->path = $path;
        $this->resourceClass = $resourceClass;
        $this->shortNames = $shortNames;
        $this->defaults = $defaults;
        $this->requirements = $requirements;
        $this->options = $options;
        $this->host = $host;
        $this->schemes = $schemes;
        $this->condition = $condition;
        $this->controller = $controller;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function isCollection(): bool
    {
        return $this->collection;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getOperationName(): string
    {
        return $this->operationName;
    }

    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }

    public function getShortNames(): array
    {
        return $this->shortNames;
    }

    public function getDefaults(): array
    {
        return $this->defaults;
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getSchemes(): array
    {
        return $this->schemes;
    }

    public function getCondition(): string
    {
        return $this->condition;
    }

    public function getController(): ?string
    {
        return $this->controller;
    }
}
