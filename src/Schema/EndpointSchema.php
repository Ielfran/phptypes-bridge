<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Schema;

use PHPTypeS\Bridge\Schema\Nodes\TypeNode;

final class EndpointSchema
{
   
    public function __construct(
        private readonly string $name,
        private readonly string $httpMethod,
        private readonly string $path,
        private readonly string $controllerFqcn,
        private readonly string $methodName,
        private readonly ?TypeNode $requestType = null,
        private readonly ?TypeNode $responseType = null,
        private readonly array $pathParams = [],
        private readonly array $queryParams = [],
        private readonly array $tags = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function httpMethod(): string
    {
        return strtoupper($this->httpMethod);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function controllerFqcn(): string
    {
        return $this->controllerFqcn;
    }

    public function methodName(): string
    {
        return $this->methodName;
    }

    public function requestType(): ?TypeNode
    {
        return $this->requestType;
    }

    public function responseType(): ?TypeNode
    {
        return $this->responseType;
    }

    public function pathParams(): array
    {
        return $this->pathParams;
    }

    public function queryParams(): array
    {
        return $this->queryParams;
    }

    public function tags(): array
    {
        return $this->tags;
    }

    public function hasRequestBody(): bool
    {
        return $this->requestType !== null
            && !in_array($this->httpMethod(), ['GET', 'HEAD', 'DELETE'], true);
    }

    public function isReadOperation(): bool
    {
        return in_array($this->httpMethod(), ['GET', 'HEAD'], true);
    }
}
