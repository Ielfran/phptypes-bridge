<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Parser\Contracts;

use PHPTypeS\Bridge\Schema\ApiSchema;

interface ParserInterface
{
    public function parse(array $directories): ApiSchema;
}
