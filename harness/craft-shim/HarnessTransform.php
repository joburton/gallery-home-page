<?php

declare(strict_types=1);

namespace Sabin\Harness;

/**
 * One derivative produced by the ImagerX shim.
 *
 * Mirrors the shape the row template reads off an ImagerX transform: `.url`,
 * `.width` and `.height`. Twig resolves those to the getters.
 */
final class HarnessTransform
{
    /**
     * @param string $url       Public URL of the derivative.
     * @param int    $width     Actual output width, which may be below the requested
     *                          width when the source was too small to honour it.
     * @param int    $height    Actual output height.
     * @param int    $requested The width originally asked for, kept so the caller can
     *                          tell an honoured request from a clamped one.
     * @param int    $bytes     Derivative size on disk, for the page-weight budget.
     */
    public function __construct(
        private readonly string $url,
        private readonly int $width,
        private readonly int $height,
        private readonly int $requested,
        private readonly int $bytes,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getRequested(): int
    {
        return $this->requested;
    }

    public function getBytes(): int
    {
        return $this->bytes;
    }

    /** True when the source could not supply the requested width, so it was clamped. */
    public function wasClamped(): bool
    {
        return $this->width < $this->requested;
    }
}
