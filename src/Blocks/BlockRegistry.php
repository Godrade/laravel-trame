<?php

namespace Godrade\Trame\Blocks;

use Godrade\Trame\Blocks\Core\ButtonBlock;
use Godrade\Trame\Blocks\Core\HeadingBlock;
use Godrade\Trame\Blocks\Core\ImageBlock;
use Godrade\Trame\Blocks\Core\SpacerBlock;
use Godrade\Trame\Blocks\Core\TextBlock;
use Godrade\Trame\Exceptions\InvalidBlockException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

final class BlockRegistry
{
    public const CORE = [
        HeadingBlock::class,
        TextBlock::class,
        ImageBlock::class,
        ButtonBlock::class,
        SpacerBlock::class,
    ];

    /** Types reserved by the document format. */
    public const RESERVED = ['global', 'section', 'row', 'column'];

    /** @var array<string, class-string<Block>> */
    private array $classes = [];

    /** @var array<string, Block> */
    private array $instances = [];

    private bool $loaded = false;

    public function __construct(
        private readonly Container $container,
        private readonly Repository $config,
    ) {}

    /** @param class-string<Block> $class */
    public function register(string $class): void
    {
        $this->load();
        $this->add($class);
    }

    public function has(mixed $type): bool
    {
        $this->load();

        return is_string($type) && isset($this->classes[$type]);
    }

    public function get(string $type): Block
    {
        if (! $this->has($type)) {
            throw new InvalidBlockException("Unknown block type [{$type}].");
        }

        return $this->instances[$type] ??= $this->container->make($this->classes[$type]);
    }

    /** @return array<string, Block> */
    public function all(): array
    {
        $this->load();

        return array_map(fn (string $type) => $this->get($type), array_combine(array_keys($this->classes), array_keys($this->classes)));
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        foreach ([...self::CORE, ...(array) $this->config->get('trame.blocks.register', [])] as $class) {
            $this->add($class);
        }

        $this->discover(
            (string) $this->config->get('trame.blocks.discover.path', ''),
            trim((string) $this->config->get('trame.blocks.discover.namespace', ''), '\\'),
        );
    }

    private function add(string $class): void
    {
        if (! is_subclass_of($class, Block::class) || (new ReflectionClass($class))->isAbstract()) {
            throw new InvalidBlockException("[{$class}] must be a concrete subclass of ".Block::class.'.');
        }

        $type = $class::$type ?? null;

        if (! is_string($type) || ! preg_match('/^[a-z][a-z0-9-]*$/', $type) || in_array($type, self::RESERVED, true)) {
            throw new InvalidBlockException("[{$class}] has an invalid or reserved \$type.");
        }

        $this->classes[$type] = $class;
        unset($this->instances[$type]);
    }

    private function discover(string $path, string $namespace): void
    {
        if ($path === '' || $namespace === '' || ! is_dir($path)) {
            return;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
        $found = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(rtrim($path, '/\\')) + 1, -4);
            $found[] = $namespace.'\\'.str_replace(['/', '\\'], '\\', $relative);
        }

        sort($found);

        foreach ($found as $class) {
            if (class_exists($class) && is_subclass_of($class, Block::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $this->add($class);
            }
        }
    }
}
