<?php

namespace Godrade\Trame\Console;

use Godrade\Trame\Trame;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'trame:clear')]
final class ClearCacheCommand extends Command
{
    protected $signature = 'trame:clear';

    protected $description = 'Invalidate every cached Trame render (run after deploying block view changes)';

    public function handle(Trame $trame): int
    {
        $trame->bumpCacheVersion();

        $this->components->info('Trame render cache invalidated.');

        return self::SUCCESS;
    }
}
