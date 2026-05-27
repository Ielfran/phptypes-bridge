<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Parser;

use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;
use PHPTypeS\Bridge\Attributes\ExcludeFromSchema;
use PHPTypeS\Bridge\Attributes\Expose;
use PHPTypeS\Bridge\Exceptions\ParserException;
use PHPTypeS\Bridge\Schema\DtoSchema;
use PHPTypeS\Bridge\Schema\PropertySchema;
use PHPTypeS\Bridge\TypeMapper\TypeMapper;
use PHPTypeS\Bridge\Schema\Nodes\ArrayTypeNode;

final class DtoParser
{
    private readonly DocBlockParser $docBlockParser;

    public function __construct(
        private readonly TypeMapper $typeMapper,
    ) {
        $this->docBlockParser = new DocBlockParser();
    }

    public function parse(ReflectionClass $class): DtoSchema
    {
        if ($class->isAbstract()) {
            throw new ParserException(
                "Cannot parse abstract class {$class->getName()} as a DTO."
            );
        }

        $properties = $this->extractProperties($class);

        return new DtoSchema(
            fqcn: $class->getName(),
            shortName: $class->getShortName(),
            properties: $properties,
            docComment: $class->getDocComment() ?: null,
        );
    }

    private function extractProperties(ReflectionClass $class): array
    {
        $constructor = $class->getConstructor();
        $properties  = [];
        $seen        = [];

        $isReadonlyClass = method_exists($class, 'isReadOnly') && $class->isReadOnly();

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                if ($this->isExcluded($param)) {
                    continue;
                }

                $prop = $this->buildFromParameter($param, $class);

                if ($prop !== null) {
                    $properties[]         = $prop;
                    $seen[$param->getName()] = true;
                }
            }
        }

        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $refProp) {
            if (isset($seen[$refProp->getName()])) {
                continue;
            }

            if ($this->isExcluded($refProp)) {
                continue;
            }

            $prop = $this->buildFromProperty($refProp, $class);

            if ($prop !== null) {
                $properties[] = $prop;
            }
        }

        return $properties;
    }

    private function buildFromParameter(
        ReflectionParameter $param,
        ReflectionClass $class
    ): ?PropertySchema {
        $type = $param->getType();

        if ($type === null) {
            return null;
        }

        $expose   = $this->getExposeAttribute($param);
        $name     = $expose?->name ?? $param->getName();
        $optional = $expose?->optional ?? $param->isOptional();

        $context  = "{$class->getName()}::__construct(\${$param->getName()})";

        $typeNode = $this->typeMapper->fromReflectionType($type, $context);

        if ($typeNode instanceof ArrayTypeNode) {
            $docType = null;

            if ($param->isPromoted()) {
                try {
                    $propDoc = $class->getProperty($param->getName())->getDocComment() ?: '';
                    if ($propDoc !== '') {
                        $docType = $this->docBlockParser->extractVar($propDoc)
                            ?? $this->docBlockParser->extractParam($propDoc, $param->getName());
                    }
                } catch (\ReflectionException) {
                }
            }

            if ($docType === null) {
                $ctorDoc = $class->getConstructor()?->getDocComment() ?: '';
                if ($ctorDoc !== '') {
                    $docType = $this->docBlockParser->extractParam($ctorDoc, $param->getName());
                }
            }

            if ($docType !== null) {
                try {
                    $typeNode = $this->typeMapper->fromString($docType, $context);
                } catch (\Throwable) {
                }
            }
        }

        return new PropertySchema(
            name: $name,
            typeNode: $typeNode,
            optional: $optional,
            readonly: $param->isPromoted()
                && ($class->getProperty($param->getName())->isReadOnly()),
            docComment: $expose?->description,
        );
    }

    private function buildFromProperty(
        ReflectionProperty $refProp,
        ReflectionClass $class
    ): ?PropertySchema {
        $type = $refProp->getType();

        if ($type === null) {
            return null;
        }

        $expose   = $this->getExposeAttributeFromProperty($refProp);
        $name     = $expose?->name ?? $refProp->getName();

        $optional = $expose?->optional ?? $refProp->hasDefaultValue();

        $context  = "{$class->getName()}::\${$refProp->getName()}";

        return new PropertySchema(
            name: $name,
            typeNode: $this->typeMapper->fromReflectionType($type, $context),
            optional: $optional,
            readonly: $refProp->isReadOnly(),
            docComment: $expose?->description,
        );
    }

    private function isExcluded(ReflectionParameter|ReflectionProperty $subject): bool
    {
        $attrs = $subject->getAttributes(ExcludeFromSchema::class);
        return $attrs !== [];
    }

    private function getExposeAttribute(ReflectionParameter $param): ?Expose
    {
        $attrs = $param->getAttributes(Expose::class);

        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    private function getExposeAttributeFromProperty(ReflectionProperty $prop): ?Expose
    {
        $attrs = $prop->getAttributes(Expose::class);

        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance();
    }
}