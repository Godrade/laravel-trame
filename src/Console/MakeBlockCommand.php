<?php

namespace Godrade\Trame\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:trame-block')]
final class MakeBlockCommand extends Command
{
    protected $signature = 'make:trame-block {name : Block name, e.g. Testimonial} {--force : Overwrite existing files}';

    protected $description = 'Create a Trame block class and its Blade view';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly(preg_replace('/Block$/', '', (string) $this->argument('name')));

        if ($name === '' || ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            $this->components->error('Invalid block name.');

            return self::FAILURE;
        }

        $type = Str::kebab($name);
        $namespace = trim((string) config('trame.blocks.discover.namespace', 'App\\Trame\\Blocks'), '\\');
        $directory = (string) config('trame.blocks.discover.path', app_path('Trame/Blocks'));

        $classPath = $directory.'/'.$name.'Block.php';
        $viewPath = resource_path("views/trame/blocks/{$type}.blade.php");

        foreach ([$classPath, $viewPath] as $path) {
            if ($files->exists($path) && ! $this->option('force')) {
                $this->components->error("[{$path}] already exists. Use --force to overwrite.");

                return self::FAILURE;
            }
        }

        $replace = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $name.'Block',
            '{{ type }}' => $type,
            '{{ label }}' => Str::headline($name),
            '{{ view }}' => "trame.blocks.{$type}",
        ];

        $stubs = __DIR__.'/../../stubs';

        $files->ensureDirectoryExists(dirname($classPath));
        $files->ensureDirectoryExists(dirname($viewPath));
        $files->put($classPath, strtr($files->get("{$stubs}/block.php.stub"), $replace));
        $files->put($viewPath, strtr($files->get("{$stubs}/block.blade.php.stub"), $replace));

        $this->components->info("Block [{$type}] created.");
        $this->components->bulletList([$classPath, $viewPath]);

        return self::SUCCESS;
    }
}
