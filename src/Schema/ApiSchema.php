<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Schema;

use PHPTypeS\Bridge\Schema\Nodes\EnumTypeNode;

final class ApiSchema
{
    private array $dtos = [];

    private array $enums = [];

    private array $endpoints = [];

    public function addDto(DtoSchema $dto): void
    {
        $this->dtos[$dto->fqcn()] = $dto;
    }

    public function addEnum(EnumTypeNode $enum): void
    {
        $this->enums[$enum->fqcn()] = $enum;
    }

    public function addEndpoint(EndpointSchema $endpoint): void
    {
        $this->endpoints[] = $endpoint;
    }

    public function hasDto(string $fqcn): bool
    {
        return isset($this->dtos[$fqcn]);
    }

    public function hasEnum(string $fqcn): bool
    {
        return isset($this->enums[$fqcn]);
    }

    public function findDto(string $fqcn): ?DtoSchema
    {
        return $this->dtos[$fqcn] ?? null;
    }

    public function dtos(): array
    {
        return $this->dtos;
    }

    public function enums(): array
    {
        return $this->enums;
    }

    public function endpoints(): array
    {
        return $this->endpoints;
    }

    public function isEmpty(): bool
    {
        return $this->endpoints === [];
    }
}
