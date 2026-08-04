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
        return $this->undersized === [] && $this->mismatched === [];
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
