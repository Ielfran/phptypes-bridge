<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Parser;

final class DocBlockParser
{
    public function extractVar(string $docComment): ?string
    {
        if (preg_match('/@var\s+([^\s\n*]+)/', $docComment, $m)) {
            return $this->clean($m[1]);
        }

        return null;
    }

    public function extractReturn(string $docComment): ?string
    {
        if (preg_match('/@return\s+([^\s\n*]+)/', $docComment, $m)) {
            return $this->clean($m[1]);
        }

        return null;
    }

    public function extractParam(string $docComment, string $paramName): ?string
    {
        $pattern = '/@param\s+([^\s]+)\s+\$' . preg_quote($paramName, '/') . '(?:\s|$)/';

        if (preg_match($pattern, $docComment, $m)) {
            return $this->clean($m[1]);
        }

        return null;
    }

    public function hasTypeInfo(string $docComment): bool
    {
        return (bool) preg_match('/@(?:var|return|param)\s/', $docComment);
    }

    private function clean(string $type): string
    {
        return ltrim(trim($type), '\\');
    }
}