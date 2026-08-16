<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Services;

/**
 * What a `make:repo` run actually did.
 *
 * v1 returned status strings that nothing ever read and echoed reminders
 * straight to stdout. The command now receives this object and renders it
 * through the console output, so messages respect --quiet, --no-ansi and
 * output redirection.
 */
final class GeneratorResult
{
    /**
     * @param  array<int, string>  $created  Absolute paths written
     * @param  array<int, string>  $skipped  Absolute paths left alone because they already existed
     * @param  array<int, string>  $updated  Absolute paths modified in place
     * @param  array<int, string>  $notes    Follow-up actions for the developer
     */
    public function __construct(
        public readonly array $created = [],
        public readonly array $skipped = [],
        public readonly array $updated = [],
        public readonly array $notes = [],
    ) {}

    /**
     * @param  array<int, string>  $created
     * @param  array<int, string>  $skipped
     * @param  array<int, string>  $updated
     * @param  array<int, string>  $notes
     */
    public function with(array $created = [], array $skipped = [], array $updated = [], array $notes = []): self
    {
        return new self(
            [...$this->created, ...$created],
            [...$this->skipped, ...$skipped],
            [...$this->updated, ...$updated],
            [...$this->notes, ...$notes],
        );
    }

    /**
     * Whether anything was written or changed on disk.
     */
    public function touchedAnything(): bool
    {
        return $this->created !== [] || $this->updated !== [];
    }
}
