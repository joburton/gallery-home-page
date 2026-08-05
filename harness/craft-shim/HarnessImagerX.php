<?php

declare(strict_types=1);

namespace Sabin\Harness;

use GdImage;
use RuntimeException;

/**
 * Emulates the slice of ImagerX the row template calls, backed by GD.
 *
 * Exactly two public methods, because exactly two are what the template uses:
 * transformImage() and srcset(). Keeping the surface this narrow is what makes the
 * Phase 3 handoff a data-source swap — the template calls the same two functions
 * against real ImagerX, and nothing else here leaks into it.
 *
 * Judgements about whether an asset is *good enough* live in MediaAudit, not here.
 * Real ImagerX has no such notion, so neither does the shim.
 */
final class HarnessImagerX
{
    /** @var array<string, HarnessTransform> Keyed by cache key, so repeated tiles cost nothing. */
    private array $memo = [];

    /**
     * @param string $cacheDir Absolute path to the derivative cache directory.
     * @param string $cacheUrl Public URL prefix that maps onto $cacheDir.
     * @param int    $quality  WebP quality for derivatives.
     */
    public function __construct(
        private readonly string $cacheDir,
        private readonly string $cacheUrl,
        private readonly int $quality,
    ) {
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0o775, true) && !is_dir($this->cacheDir)) {
            throw new RuntimeException("Cannot create transform cache: {$this->cacheDir}");
        }
    }

    /**
     * Emulates craft.imagerx.transformImage.
     *
     * Crops to the requested ratio honouring the asset's focal point, writes WebP
     * derivatives into the cache, and never upscales: a requested width beyond the
     * source's native cropped size yields a derivative at that native size, recorded
     * for the undersized report rather than stretched.
     *
     * @param HarnessAsset               $asset      Source asset.
     * @param array<int, array{width:int}> $transforms List of width specs.
     * @param array{mode?:string, ratio?:float} $options ['mode' => 'crop', 'ratio' => 1/1].
     *
     * @return HarnessTransform[] One per spec, in the order given.
     *
     * @throws RuntimeException If the source cannot be decoded or the mode is unsupported.
     */
    public function transformImage(HarnessAsset $asset, array $transforms, array $options): array
    {
        $mode = $options['mode'] ?? 'crop';
        if ($mode !== 'crop') {
            throw new RuntimeException("HarnessImagerX supports mode 'crop' only, got '{$mode}'");
        }
        $ratio = (float) ($options['ratio'] ?? 1.0);

        $out = [];
        foreach ($transforms as $spec) {
            $out[] = $this->one($asset, (int) $spec['width'], $ratio);
        }

        return $out;
    }

    /**
     * Emulates craft.imagerx.srcset — builds a width-descriptor srcset string.
     *
     * Clamped derivatives collapse to the same width, so duplicates are removed:
     * an undersized source yields one candidate rather than the same file listed
     * three times under three different descriptors.
     *
     * @param HarnessTransform[] $transforms Output of transformImage().
     */
    public function srcset(array $transforms): string
    {
        $seen = [];
        foreach ($transforms as $t) {
            $seen[$t->getWidth()] = "{$t->getUrl()} {$t->getWidth()}w";
        }
        ksort($seen);

        return implode(', ', $seen);
    }

    /**
     * Total bytes of every derivative produced this render, for the page-weight budget.
     *
     * Counts each distinct derivative once, so a repeated tile does not inflate it.
     */
    public function bytesProduced(): int
    {
        return array_sum(array_map(
            static fn (HarnessTransform $t): int => $t->getBytes(),
            $this->memo,
        ));
    }

    /**
     * Produces one derivative, reusing the cached file when it is current.
     *
     * @throws RuntimeException If the source cannot be decoded or the derivative cannot be written.
     */
    private function one(HarnessAsset $asset, int $requestedWidth, float $ratio): HarnessTransform
    {
        $focal = $asset->getFocalPoint();
        $crop = $this->cropWindow($asset->getWidth(), $asset->getHeight(), $ratio, $focal);

        // Never upscale: the derivative can be no wider than the crop window itself.
        // A request beyond that clamps rather than stretching, and srcset() collapses
        // the duplicate candidates that result.
        $outWidth = min($requestedWidth, $crop['w']);
        $outHeight = (int) round($outWidth / $ratio);

        $key = $this->cacheKey($asset, $outWidth, $ratio, $focal);
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $file = "{$this->cacheDir}/{$key}.webp";
        if (!is_file($file)) {
            $this->write($asset, $crop, $outWidth, $outHeight, $file);
        }

        return $this->memo[$key] = new HarnessTransform(
            "{$this->cacheUrl}/{$key}.webp",
            $outWidth,
            $outHeight,
            $requestedWidth,
            (int) filesize($file),
        );
    }

    /**
     * Largest window of the requested ratio that fits the source, positioned on the focal point.
     *
     * The window is the largest that fits, so no source pixels are invented. Its
     * centre tracks the focal point but is clamped to stay inside the frame, which
     * is what keeps an off-centre subject in shot without letterboxing.
     *
     * @param array{x: float, y: float} $focal
     *
     * @return array{x:int, y:int, w:int, h:int}
     */
    private function cropWindow(int $srcW, int $srcH, float $ratio, array $focal): array
    {
        $w = $srcW;
        $h = (int) round($srcW / $ratio);

        if ($h > $srcH) {
            $h = $srcH;
            $w = (int) round($srcH * $ratio);
        }

        $x = (int) round(($focal['x'] * $srcW) - ($w / 2));
        $y = (int) round(($focal['y'] * $srcH) - ($h / 2));

        return [
            'x' => max(0, min($x, $srcW - $w)),
            'y' => max(0, min($y, $srcH - $h)),
            'w' => $w,
            'h' => $h,
        ];
    }

    /**
     * Crops and scales the source into a WebP derivative.
     *
     * @param array{x:int, y:int, w:int, h:int} $crop
     *
     * @throws RuntimeException If the source cannot be decoded or the file cannot be written.
     */
    private function write(HarnessAsset $asset, array $crop, int $outW, int $outH, string $file): void
    {
        $src = @imagecreatefromstring((string) file_get_contents($asset->getPath()));
        if ($src === false) {
            throw new RuntimeException("Cannot decode image: {$asset->getPath()}");
        }

        $dst = imagecreatetruecolor($outW, $outH);
        $this->preserveAlpha($dst, $asset->hasAlpha());

        $ok = imagecopyresampled(
            $dst, $src,
            0, 0,
            $crop['x'], $crop['y'],
            $outW, $outH,
            $crop['w'], $crop['h'],
        );

        // Larger rungs carry the LCP cost, so they are compressed harder. A flat
        // quality either wastes bytes on the big tiles or softens the small ones.
        $quality = $outW >= 700 ? max(40, $this->quality - 8) : $this->quality;

        if (!$ok || !imagewebp($dst, $file, $quality)) {
            throw new RuntimeException("Cannot write derivative: {$file}");
        }
    }

    /** Keeps a transparent source transparent instead of compositing it onto black. */
    private function preserveAlpha(GdImage $image, bool $hasAlpha): void
    {
        if (!$hasAlpha) {
            return;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
    }

    /**
     * Cache key covering everything that changes the output.
     *
     * Includes the source mtime so re-exporting an asset invalidates its derivatives
     * without anyone having to clear the cache by hand.
     *
     * @param array{x: float, y: float} $focal
     */
    private function cacheKey(HarnessAsset $asset, int $width, float $ratio, array $focal): string
    {
        $slug = pathinfo($asset->getPath(), PATHINFO_FILENAME);
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $slug));
        $slug = trim($slug, '-');

        $hash = substr(hash('xxh128', implode('|', [
            $asset->getPath(),
            (string) filemtime($asset->getPath()),
            (string) $ratio,
            sprintf('%.4f,%.4f', $focal['x'], $focal['y']),
        ])), 0, 8);

        return "{$slug}-{$width}-{$hash}";
    }
}
