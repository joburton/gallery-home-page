<?php

declare(strict_types=1);

/**
 * PHASE 1 DEMO — Craft shim + fixture loader.
 *
 * Proves the sprint's central architectural bet: that the row template can be
 * authored against Craft's own API and still render outside Craft.
 *
 * Run: php docs/sprints/current/demos/phase1-shim.php
 */

namespace Sabin\Harness;

require __DIR__ . '/../../../../harness/render.php';

$root = dirname(__DIR__, 4);

echo "=== 1. Renders the real fixture through Craft-native Twig ===\n\n";

$result = render_harness($root . '/data/gallery.fixture.json');
echo $result['html'];

echo "\n=== 2. Media audit (flagged, never silently upscaled) ===\n\n";
echo $result['audit']->format();
printf("\n  derivative bytes produced: %s\n", number_format($result['derivativeBytes']));

echo "\n=== 3. Validation rules fire before anything renders ===\n\n";

/**
 * Index of the first tile of a given type.
 *
 * Cases address tiles by TYPE rather than by position, so they keep testing what they
 * claim as the fixture grows or is reordered. Addressing by index silently turned the
 * "motion tile without poster" case into a no-op the moment the fixture went from
 * three tiles to thirty-two and tiles[2] stopped being the motion tile.
 *
 * @throws \RuntimeException If the fixture has no tile of that type.
 */
function tile_of_type(array $tiles, string $type): int
{
    foreach ($tiles as $i => $t) {
        if (($t['type'] ?? null) === $type) {
            return $i;
        }
    }

    throw new \RuntimeException("No '{$type}' tile in the fixture");
}

/**
 * Applies a mutation to the good fixture and reports the violations it triggers.
 *
 * @param callable(array): array $mutate
 */
function expect_rejected(string $label, callable $mutate): void
{
    $root = dirname(__DIR__, 4);
    $data = json_decode((string) file_get_contents($root . '/data/gallery.fixture.json'), true);
    $data['row'] = $mutate($data['row']);

    $tmp = sys_get_temp_dir() . '/sabin-fixture-' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($tmp, json_encode($data));

    try {
        render_harness($tmp);
        echo "  FAIL  {$label}: accepted when it should have been rejected\n";
    } catch (FixtureValidationException $e) {
        echo "  ok    {$label}\n";
        foreach ($e->getViolations() as $v) {
            echo "          -> {$v}\n";
        }
    } finally {
        @unlink($tmp);
    }
}

expect_rejected('duplicate tile id', function (array $row): array {
    $row['tiles'][2]['id'] = $row['tiles'][1]['id'];

    return $row;
});

expect_rejected('malformed size', function (array $row): array {
    $row['tiles'][tile_of_type($row['tiles'], 'image')]['size'] = '2';

    return $row;
});

expect_rejected('zero span in size', function (array $row): array {
    $row['tiles'][tile_of_type($row['tiles'], 'image')]['size'] = '0x1';

    return $row;
});

expect_rejected('media tile with empty alt', function (array $row): array {
    $row['tiles'][tile_of_type($row['tiles'], 'image')]['alt'] = '   ';

    return $row;
});

expect_rejected('motion tile without poster', function (array $row): array {
    unset($row['tiles'][tile_of_type($row['tiles'], 'motion')]['poster']);

    return $row;
});

expect_rejected('text tile carrying a buildLink', function (array $row): array {
    $row['tiles'][tile_of_type($row['tiles'], 'text')]['buildLink'] = ['url' => '/x', 'live' => true];

    return $row;
});

expect_rejected('unknown contentType', function (array $row): array {
    $row['tiles'][tile_of_type($row['tiles'], 'image')]['contentType'] = 'lifestyle';

    return $row;
});

expect_rejected('unknown layoutMode', function (array $row): array {
    $row['layoutMode'] = 'bento';

    return $row;
});

expect_rejected('missing tileGap (no code default)', function (array $row): array {
    unset($row['tileGap']);

    return $row;
});

expect_rejected('free-form hex as text background', function (array $row): array {
    $row['tiles'][tile_of_type($row['tiles'], 'text')]['text']['background'] = '#ff0000';

    return $row;
});

expect_rejected('arbitrary pixel padding', function (array $row): array {
    $row['tiles'][tile_of_type($row['tiles'], 'text')]['text']['padding'] = '17px';

    return $row;
});

expect_rejected('live link with no url', function (array $row): array {
    $row['tiles'][tile_of_type($row['tiles'], 'motion')]['destination'] = ['type' => 'about', 'live' => true];

    return $row;
});

expect_rejected('welcome tile not first', function (array $row): array {
    $row['tiles'] = [$row['tiles'][1], $row['tiles'][0], $row['tiles'][2]];

    return $row;
});

expect_rejected('missing media file on disk', function (array $row): array {
    $row['tiles'][tile_of_type($row['tiles'], 'image')]['media'] = '/images/does-not-exist.jpeg';

    return $row;
});

expect_rejected('over the 1000-tile ceiling', function (array $row): array {
    $template = $row['tiles'][tile_of_type($row['tiles'], 'image')];
    $extra = [];
    for ($i = 0; $i < 1001; $i++) {
        $extra[] = ['id' => "overflow-{$i}"] + $template;
    }
    $row['tiles'] = [$row['tiles'][0], ...$extra];

    return $row;
});

echo "\n=== 4. Craft idioms resolve through the shim ===\n\n";

$loader = new FixtureLoader($root);
$row = $loader->load($root . '/data/gallery.fixture.json');
$asset = $row['tiles'][tile_of_type($row['tiles'], 'image')]['media'];

printf("  eagerly().one() is identity:      %s\n", $asset->eagerly()->one() === $asset ? 'yes' : 'NO');
printf("  focal point defaults to centre:   %s\n", json_encode($asset->getFocalPoint()));
printf("  intrinsic size read from file:    %dx%d\n", $asset->getWidth(), $asset->getHeight());
printf("  real format detected:             %s\n", $asset->getRealFormat());

$withFocal = new HarnessAsset($asset->getPath(), 'x', '/x', ['x' => 0.8, 'y' => 0.3]);
printf("  explicit focal point honoured:    %s\n", json_encode($withFocal->getFocalPoint()));

$avif = new HarnessAsset($root . '/images/cable-stainless-steel.png', 'x', '/x');
printf("  AVIF-as-.png detected:            %s (mismatch: %s)\n",
    $avif->getRealFormat(),
    $avif->hasExtensionMismatch() ? 'yes' : 'no',
);
