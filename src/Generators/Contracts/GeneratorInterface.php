<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Generators\Contracts;

use PHPTypeS\Bridge\Generators\Output\GeneratorOutput;
use PHPTypeS\Bridge\Schema\ApiSchema;

interface GeneratorInterface
{
    public function generate(ApiSchema $schema): GeneratorOutput;
}
