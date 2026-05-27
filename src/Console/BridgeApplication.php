<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Console;

use Symfony\Component\Console\Application;

final class BridgeApplication extends Application
{
    public function __construct()
    {
        parent::__construct('PHPTypeS/bridge', '@dev');

        $this->addCommands([
            new GenerateCommand(),
            new ValidateCommand(),
            new InitCommand(),
        ]);

        // `vendor/bin/bridge` alone runs generate (most common use case)
        $this->setDefaultCommand('generate', isSingleCommand: false);
    }
}
