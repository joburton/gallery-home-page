<?php

declare(strict_types=1);

namespace Sabin\Harness;

use RuntimeException;

/**
 * Stands in for a Craft Asset, exposing only the surface the row template touches.
 *
 * The row template is authored exactly as Foster will receive it, so it reads
 * `asset.url`, `asset.alt`, `asset.focalPoint` and calls `eagerly().one()`. Twig
 * resolves `asset.url` to getUrl() via its property/getter fallback, so those
 * idioms work unchanged here without the template knowing it isn't in Craft.
 */
final class HarnessAsset
{
    private ?int $width = null;
    private ?int $height = null;
    private ?bool $alpha = null;
    private ?string $realFormat = null;

    /**
     * @param string     $path       Absolute path to the source file in images/.
     * @param string     $alt        Descriptive alt text from the fixture.
     * @param string     $url        Public URL as Craft would report it.
     * @param array|null $focalPoint ['x' => float, 'y' => float] in 0..1, or null for centre.
     */
    public function __construct(
        private readonly string $path,
        private readonly string $alt,
        private readonly string $url,
        private readonly ?array $focalPoint = null,
    ) {
    }

    /** Absolute filesystem path — used by the transform pipeline, not by templates. */
    public function getPath(): string
    {
        return $this->path;
    }

    /** Public URL of the source asset, as Craft would report it. */
    public function getUrl(): string
    {
        return $this->url;
    }

    /** Descriptive alt text. Craft exposes this on the asset, so the template reads it there. */
    public function getAlt(): string
    {
        return $this->alt;
    }

    /** Intrinsic width in pixels. */
    public function getWidth(): int
    {
        $this->probe();

        return $this->width;
    }

    /** Intrinsic height in pixels. */
    public function getHeight(): int
    {
        $this->probe();

        return $this->height;
    }

    /**
     * Normalised focal point, defaulting to centre when unset.
     *
     * @return array{x: float, y: float}
     */
    public function getFocalPoint(): array
    {
        return $this->focalPoint ?? ['x' => 0.5, 'y' => 0.5];
    }

    /** True when a focal point was explicitly set, so callers can distinguish it from centre. */
    public function hasFocalPoint(): bool
    {
        return $this->focalPoint !== null;
    }

    /** True when the source carries an alpha channel, so the tile needs a defined background. */
    public function hasAlpha(): bool
    {
        $this->probe();

        return $this->alpha;
    }

    /**
     * The format detected from file content, which is not always the extension.
     *
     * Three supplied assets are AVIF carrying .png/.jpg extensions. Reporting the
     * real format is what lets the media pipeline decode them correctly and flag
     * the mismatch rather than trusting the filename.
     */
    public function getRealFormat(): string
    {
        $this->probe();

        return $this->realFormat;
    }

    /** True when the file's real format contradicts its extension. */
    public function hasExtensionMismatch(): bool
    {
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
        $ext = $ext === 'jpg' ? 'jpeg' : $ext;

        return $ext !== $this->getRealFormat();
    }

    /**
     * Emulates Craft's eager-loading chain so the template reads identically in Craft.
     *
     * In Craft `row.image.eagerly().one()` narrows an asset query to a single element.
     * Here the asset is already singular, so both calls are identity.
     */
    public function eagerly(): self
    {
        return $this;
    }

    /** @see eagerly() */
    public function one(): self
    {
        return $this;
    }

    /**
     * Reads intrinsic dimensions, alpha and real format once, from file content.
     *
     * @throws RuntimeException If the source cannot be decoded.
     */
    private function probe(): void
    {
        if ($this->width !== null) {
            return;
        }

        $info = @getimagesize($this->path);
        if ($info === false) {
            throw new RuntimeException("Cannot decode image: {$this->path}");
        }

        [$this->width, $this->height] = $info;
        $this->realFormat = match ($info[2]) {
            IMAGETYPE_JPEG => 'jpeg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_AVIF => 'avif',
            IMAGETYPE_GIF => 'gif',
            default => 'unknown',
        };
        $this->alpha = $this->detectAlpha($info[2]);
    }

    /**
     * Detects an alpha channel from format-specific markers.
     *
     * JPEG never carries alpha. PNG declares it in the IHDR colour type. AVIF and
     * WebP need the decoded image inspected, which GD will do for us.
     */
    private function detectAlpha(int $imageType): bool
    {
        if ($imageType === IMAGETYPE_JPEG) {
            return false;
        }

        if ($imageType === IMAGETYPE_PNG) {
            $ihdr = (string) file_get_contents($this->path, false, null, 25, 1);

            return $ihdr !== '' && in_array(ord($ihdr), [4, 6], true);
        }

        $image = @imagecreatefromstring((string) file_get_contents($this->path));
        if ($image === false) {
            return false;
        }

        // GD reports palette images as non-truecolor; only truecolor images can
        // carry a per-pixel alpha channel, and imagesavealpha reflects whether
        // one is present in the decoded result.
        return imageistruecolor($image) && $this->scanForTransparency($image);
    }

    /**
     * Samples the decoded image for any non-opaque pixel.
     *
     * A full scan is wasteful for large sources, and a corner-only check misses
     * interior transparency, so this samples on a coarse grid — enough to decide
     * whether the tile needs a defined background behind it.
     */
    private function scanForTransparency(\GdImage $image): bool
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $step = max(1, (int) floor(min($w, $h) / 32));

        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
                if ($alpha > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
