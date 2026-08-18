<?php

declare(strict_types=1);

namespace Sabin\Harness;

/**
 * Audits the fixture's source media against what the grid actually needs.
 *
 * Deliberately separate from HarnessImagerX. The transform's job is to emulate
 * ImagerX and nothing else — real ImagerX has no concept of "undersized for this
 * tile", so putting that judgement inside the shim would widen the surface the row
 * template sits on and blur what Foster's pipeline has to provide.
 *
 * The bar comes from the spec: an asset is undersized when its square crop is below
 * what a 2in tile needs on a high-density display. A 2in cell is 192 CSS px by
 * definition, so the threshold is 2x that per column of span. Failing a 3x rung is
 * normal for good assets and is not reported — otherwise the report cries wolf and
 * stops being read.
 */
final class MediaAudit
{
    /** CSS pixels in a 2in cell. CSS defines 1in as exactly 96px. */
    public const CELL_PX = 192;

    /** Device-pixel ratio an asset must satisfy before it counts as adequate. */
    public const REQUIRED_DPR = 2;

    /** @var array<string, array{edge:int, needed:int, size:string}> */
    private array $undersized = [];

    /** @var array<string, string> */
    private array $mismatched = [];

    /** @var array<string, bool> */
    private array $alpha = [];

    /** @var string[] Files present in the source directory but referenced by no tile. */
    private array $unreferenced = [];

    /**
     * Walks every tile's media and records anything that needs a human decision.
     *
     * @param array $tiles Hydrated tiles from FixtureLoader::load().
     */
    public function inspect(array $tiles): void
    {
        foreach ($tiles as $tile) {
            $still = match ($tile['type']) {
                'motion' => $tile['poster'],
                'image' => $tile['media'],
                default => $tile['text']['media'] ?? null,
            };

            if (!$still instanceof HarnessAsset) {
                continue;
            }

            $this->inspectAsset($still, (string) $tile['size']);
        }
    }

    /**
     * Reports source files that no tile references.
     *
     * The fixture is the source of truth, not the folder: nothing scans images/, because
     * contentType, alt text, size and destination cannot be derived from a filename. That
     * is correct — and it also means dropping files into images/ appears to do nothing,
     * which is genuinely confusing the first time. So the gap is reported rather than
     * left silent.
     *
     * @param string $sourceDir Absolute path to the source media directory.
     * @param array  $tiles     Hydrated tiles from FixtureLoader::load().
     */
    public function inspectUnreferenced(string $sourceDir, array $tiles): void
    {
        $referenced = [];
        foreach ($tiles as $tile) {
            foreach (['media', 'poster'] as $field) {
                $asset = $tile[$field] ?? null;
                if ($asset instanceof HarnessAsset || $asset instanceof HarnessVideo) {
                    $referenced[basename($asset->getPath())] = true;
                }
            }
            foreach (['media', 'image'] as $field) {
                $asset = $tile['text'][$field] ?? null;
                if ($asset instanceof HarnessAsset || $asset instanceof HarnessVideo) {
                    $referenced[basename($asset->getPath())] = true;
                }
            }
        }

        $present = glob($sourceDir . '/*.{jpg,jpeg,png,webp,avif,mp4,mov,m4v}', GLOB_BRACE) ?: [];
        foreach ($present as $path) {
            $name = basename($path);
            if (isset($referenced[$name])) {
                continue;
            }
            // A video whose prepared loop IS referenced is not orphaned — the tile points
            // at the derivative in dist/media, which is the intended arrangement.
            if (preg_match('/\.(mp4|mov|m4v)$/i', $name)) {
                $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', pathinfo($name, PATHINFO_FILENAME)));
                $slug = trim($slug, '-');
                if (isset($referenced["{$slug}-loop.mp4"])) {
                    continue;
                }
            }
            $this->unreferenced[] = $name;
        }
        sort($this->unreferenced);
    }

    /** @return string[] */
    public function unreferenced(): array
    {
        return $this->unreferenced;
    }

    /** @return array<string, array{edge:int, needed:int, size:string}> */
    public function undersized(): array
    {
        ksort($this->undersized);

        return $this->undersized;
    }

    /** @return array<string, string> */
    public function mismatched(): array
    {
        ksort($this->mismatched);

        return $this->mismatched;
    }

    /** @return string[] Filenames of assets carrying transparency. */
    public function alphaCarrying(): array
    {
        $names = array_keys(array_filter($this->alpha));
        sort($names);

        return $names;
    }

    /** True when nothing needs attention. */
    public function isClean(): bool
    {
        return $this->undersized === [] && $this->mismatched === [] && $this->unreferenced === [];
    }

    /** Records the three conditions worth reporting for one asset. */
    private function inspectAsset(HarnessAsset $asset, string $size): void
    {
        $name = basename($asset->getPath());

        // The square crop can be no larger than the source's short edge.
        $edge = min($asset->getWidth(), $asset->getHeight());
        $cols = (int) (explode('x', $size)[0] ?? 1);
        $needed = self::CELL_PX * self::REQUIRED_DPR * max(1, $cols);

        if ($edge < $needed) {
            $this->undersized[$name] = ['edge' => $edge, 'needed' => $needed, 'size' => $size];
        }

        if ($asset->hasExtensionMismatch()) {
            $this->mismatched[$name] = $asset->getRealFormat();
        }

        $this->alpha[$name] = $asset->hasAlpha();
    }

    /**
     * Human-readable report, printed by the preview command and sprint verification.
     *
     * Phrased as actions rather than warnings, because the spec's requirement is that
     * these assets get re-exported rather than silently stretched.
     */
    public function format(): string
    {
        $lines = [];

        if ($this->undersized !== []) {
            $lines[] = 'RE-EXPORT NEEDED — square crop below ' . self::REQUIRED_DPR . 'x for the tile size:';
            foreach ($this->undersized() as $name => $d) {
                $lines[] = sprintf(
                    '  %-56s %4dpx available, %4dpx needed for %s',
                    $name, $d['edge'], $d['needed'], $d['size'],
                );
            }
        }

        if ($this->mismatched !== []) {
            $lines[] = 'EXTENSION MISMATCH — real format differs from the filename:';
            foreach ($this->mismatched() as $name => $format) {
                $lines[] = sprintf('  %-56s is actually %s', $name, $format);
            }
        }

        if ($this->unreferenced !== []) {
            $n = count($this->unreferenced);
            $lines[] = "NOT IN THE GALLERY — {$n} file(s) in images/ that no tile references.";
            $lines[] = '  The fixture lists the tiles; the folder is not scanned. Add an entry to';
            $lines[] = '  data/gallery.fixture.json for each of these, or they will not appear:';
            foreach ($this->unreferenced as $name) {
                $lines[] = "    {$name}";
            }
        }

        $alpha = $this->alphaCarrying();
        if ($alpha !== []) {
            $lines[] = 'CARRIES TRANSPARENCY — tile must sit on a defined background:';
            foreach ($alpha as $name) {
                $lines[] = "  {$name}";
            }
        }

        return $lines === []
            ? "Media audit: all assets adequate.\n"
            : implode("\n", $lines) . "\n";
    }
}
