<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Commands;

use Illuminate\Console\Command;
use Writeshh\Yarp\Exceptions\RepositoryException;
use Writeshh\Yarp\Services\GeneratorResult;
use Writeshh\Yarp\Services\RepositoryGenerator;

class MakeRepositoryCommand extends Command
{
    /** @var string */
    protected $signature = 'make:repo
                            {name* : One or more model names, e.g. User Post Comment}
                            {--type= : extended (default) or standalone}
                            {--interface : Force generation of a matching interface}
                            {--no-interface : Skip the interface and bind the concrete class}
                            {--force : Overwrite files that already exist}';

    /** @var string */
    protected $description = 'Generate repository classes, interfaces and container bindings for Eloquent models';

    public function handle(RepositoryGenerator $generator): int
    {
        $withInterface = $this->resolveInterfaceFlag();

        /** @var array<int, string> $names */
        $names = (array) $this->argument('name');

        $failed = false;

        foreach ($names as $name) {
            try {
                $result = $generator->generate(
                    name: $name,
                    type: $this->option('type') === null ? null : (string) $this->option('type'),
                    withInterface: $withInterface,
                    force: (bool) $this->option('force'),
                );
            } catch (RepositoryException $exception) {
                $this->components->error($exception->getMessage());
                $failed = true;

                continue;
            }

            $this->report($generator, $result);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Reconcile --interface and --no-interface, falling back to config.
     */
    protected function resolveInterfaceFlag(): ?bool
    {
        return match (true) {
            (bool) $this->option('no-interface') => false,
            (bool) $this->option('interface') => true,
            default => null,
        };
    }

    /**
     * Render what the generator did through the console output.
     *
     * v1 echoed a reminder straight to stdout, which ignored --quiet and leaked
     * into piped output. Everything now goes through the command's components.
     */
    protected function report(RepositoryGenerator $generator, GeneratorResult $result): void
    {
        foreach ($result->created as $path) {
            $this->components->info(sprintf('Created [%s]', $generator->relative($path)));
        }

        foreach ($result->updated as $path) {
            $this->components->info(sprintf('Updated [%s]', $generator->relative($path)));
        }

        foreach ($result->skipped as $path) {
            $this->components->warn(sprintf('Skipped [%s] — it already exists. Use --force to overwrite.', $generator->relative($path)));
        }

        foreach ($result->notes as $note) {
            $this->comment($note);
        }
    }
}
