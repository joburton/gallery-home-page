<?php

declare(strict_types=1);

namespace Sabin\Harness;

use RuntimeException;

/**
 * Raised when the fixture violates a rule, before any rendering happens.
 *
 * Carries every violation rather than only the first, so a fixture with several
 * problems is fixed in one pass instead of one error per run.
 */
final class FixtureValidationException extends RuntimeException
{
    /** @param string[] $violations */
    public function __construct(private readonly array $violations)
    {
        $count = count($violations);
        $noun = $count === 1 ? 'violation' : 'violations';
        parent::__construct(
            "Fixture invalid ({$count} {$noun}):\n  - " . implode("\n  - ", $violations)
        );
    }

    /** @return string[] */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
