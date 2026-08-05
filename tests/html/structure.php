<?php

declare(strict_types=1);

/**
 * Structural assertions against the served HTML.
 *
 * These are the spec's acceptance criteria expressed as checks rather than as a
 * subjective read of whether the build "looks right". Everything here is asserted
 * against the document as served, with no JavaScript executed — which is exactly the
 * condition a crawler sees.
 *
 * Run: php tests/html/structure.php
 */

namespace Sabin\Harness;

require dirname(__DIR__, 2) . '/harness/render.php';

$root = dirname(__DIR__, 2);

$failures = 0;
$checks = 0;

/** Asserts a condition, printing a pass/fail line. */
function check(string $label, bool $passed, string $detail = ''): void
{
    global $failures, $checks;
    ++$checks;
    if (!$passed) {
        ++$failures;
    }
    printf("  %-4s %s%s\n", $passed ? 'ok' : 'FAIL', $label, $detail !== '' ? " — {$detail}" : '');
}

$result = render_harness($root . '/data/gallery.fixture.json');
$html = $result['html'];

$doc = new \DOMDocument();
libxml_use_internal_errors(true);
$doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
$errors = array_filter(
    libxml_get_errors(),
    static fn ($e): bool => $e->level === LIBXML_ERR_FATAL,
);
libxml_clear_errors();

$xp = new \DOMXPath($doc);
$fixture = json_decode((string) file_get_contents($root . '/data/gallery.fixture.json'), true);
$tiles = $fixture['row']['tiles'];

echo "\n=== Document integrity ===\n";

check('parses with no fatal HTML errors', $errors === [], count($errors) . ' fatal');

$ids = [];
foreach ($xp->query('//*[@id]') as $node) {
    $ids[] = $node->getAttribute('id');
}
check(
    'no duplicate DOM ids',
    count($ids) === count(array_unique($ids)),
    count($ids) . ' ids, ' . count(array_unique($ids)) . ' unique',
);

$tileIds = [];
foreach ($xp->query('//*[@data-tile-id]') as $node) {
    $tileIds[] = $node->getAttribute('data-tile-id');
}
check(
    'every unique tile present exactly once',
    count($tileIds) === count($tiles) && count($tileIds) === count(array_unique($tileIds)),
    count($tileIds) . ' rendered, ' . count($tiles) . ' in fixture',
);

echo "\n=== Anchors (crawlability) ===\n";

check(
    'no <button> inside the row',
    $xp->query('//section[@data-gallery-row]//button')->length === 0,
);

check(
    'no click-handler attributes on any element',
    $xp->query('//section[@data-gallery-row]//*[@onclick or @data-href]')->length === 0,
);

// Nested anchors are invalid HTML that browsers restructure unpredictably. Asserted
// directly rather than trusted to the template's structure.
check(
    'no nested anchors',
    $xp->query('//a//a')->length === 0,
);

check(
    'no anchor with a dead href',
    $xp->query('//a[@href="#" or @href="" or not(@href)]')->length === 0,
);

// Tiles whose primary destination is not live must be non-interactive: rendered as
// content, but never announced as a link and never in the tab order.
$livePrimary = array_filter($tiles, static fn (array $t): bool => ($t['destination']['live'] ?? false) === true
    || ($t['type'] === 'text' && ($t['text']['cta']['live'] ?? false) === true));
$primaryAnchors = $xp->query('//a[contains(@class,"gallery-tile__link")]')->length
    + $xp->query('//a[contains(@class,"gallery-tile__cta")]')->length;
check(
    'primary anchors only where destination is live',
    $primaryAnchors === count($livePrimary),
    "{$primaryAnchors} anchors, " . count($livePrimary) . ' live destinations',
);

echo "\n=== Build icon ===\n";

$expectedIcons = array_filter(
    $tiles,
    static fn (array $t): bool => $t['type'] !== 'text' && ($t['buildLink']['live'] ?? false) === true,
);
$icons = $xp->query('//a[contains(@class,"gallery-tile__build")]');
check(
    'icon count matches tiles with a live buildLink',
    $icons->length === count($expectedIcons),
    "{$icons->length} icons, " . count($expectedIcons) . ' expected',
);

check(
    'no text tile renders an icon',
    $xp->query('//li[@data-tile-type="text"]//a[contains(@class,"gallery-tile__build")]')->length === 0,
);

$siblingOk = true;
foreach ($icons as $icon) {
    // The icon must be a SIBLING of the primary link, never a descendant of it.
    $ancestorAnchors = $xp->query('ancestor::a', $icon);
    if ($ancestorAnchors->length > 0) {
        $siblingOk = false;
    }
}
check('icon is a sibling anchor, not nested', $siblingOk);

echo "\n=== Accessible labels ===\n";

$labelled = 0;
$distinct = true;
foreach ($xp->query('//li[@data-tile-id]') as $tile) {
    $anchors = $xp->query('.//a', $tile);
    $labels = [];
    foreach ($anchors as $a) {
        $label = trim($a->getAttribute('aria-label'));
        if ($label === '') {
            // Fall back to the anchor's accessible name from its text content.
            $label = trim($a->textContent);
        }
        if ($label === '') {
            $distinct = false;
        }
        $labels[] = $label;
    }
    if (count($labels) !== count(array_unique($labels))) {
        $distinct = false;
    }
    $labelled += count($labels);
}
check('every anchor has a non-empty, tile-distinct accessible name', $distinct, "{$labelled} anchors checked");

echo "\n=== Content presence (no JS) ===\n";

$missingAlt = 0;
foreach ($tiles as $t) {
    if ($t['type'] === 'text') {
        continue;
    }
    $needle = $t['alt'];
    if (!str_contains($html, htmlspecialchars($needle, ENT_QUOTES))
        && !str_contains($html, $needle)) {
        ++$missingAlt;
    }
}
check('every media tile alt text appears in the served HTML', $missingAlt === 0, "{$missingAlt} missing");

// Every text tile, not just the first: body, CTA and logo are all optional, so this
// checks whatever each tile actually declares. Hardcoding tiles[0] meant the test broke
// the moment the welcome tile dropped its body — and would have missed a second text
// tile entirely.
$textTiles = array_values(array_filter($tiles, static fn (array $t): bool => $t['type'] === 'text'));
check('at least one text tile in the fixture', $textTiles !== [], count($textTiles) . ' found');

$present = static fn (string $needle): bool => $needle !== ''
    && (str_contains($html, $needle) || str_contains($html, htmlspecialchars($needle, ENT_QUOTES)));

foreach ($textTiles as $t) {
    $text = $t['text'];
    $label = $t['id'];

    check("{$label}: heading in served HTML", $present($text['heading']));

    if (isset($text['body'])) {
        check("{$label}: body in served HTML", $present($text['body']));
    }
    if (isset($text['cta']['label'])) {
        check("{$label}: CTA label in served HTML", $present($text['cta']['label']));
    }
    // A logo is content, so its alt text must reach the document like any other image.
    if (isset($text['image'])) {
        check("{$label}: logo alt text in served HTML", $present($text['imageAlt'] ?? ''));
    }
}

// The secondary brand face is selected by named token, never by a font name in the data.
$secondaryBodies = array_filter(
    $textTiles,
    static fn (array $t): bool => ($t['text']['bodyStyle'] ?? 'primary') === 'secondary',
);
if ($secondaryBodies !== []) {
    check(
        'secondary body copy renders via the named type class',
        $xp->query('//p[@class="copy-secondary-250"]')->length === count($secondaryBodies),
        count($secondaryBodies) . ' expected',
    );
}

check(
    'every img has non-empty alt',
    $xp->query('//img[@class="gallery-tile__media"][not(@alt) or @alt=""]')->length === 0,
);

check(
    'every img has width and height (no reflow as media loads)',
    $xp->query('//img[@class="gallery-tile__media"][not(@width) or not(@height)]')->length === 0,
);

echo "\n=== Media ===\n";

$videos = $xp->query('//video');
$videoOk = true;
foreach ($videos as $v) {
    foreach (['muted', 'loop', 'playsinline'] as $attr) {
        if (!$v->hasAttribute($attr)) {
            $videoOk = false;
        }
    }
    if ($v->getAttribute('poster') === '') {
        $videoOk = false;
    }
}
check('every video is muted, looping, inline and poster-bearing', $videoOk, "{$videos->length} videos");

$lazy = $xp->query('//img[@loading="lazy"]')->length;
$eager = $xp->query('//img[@loading="eager"]')->length;
check('below-fold media is lazy, only the first few eager', $eager <= 4 && $lazy > 0, "{$eager} eager, {$lazy} lazy");

check(
    'every img has a srcset and sizes',
    $xp->query('//img[@class="gallery-tile__media"][not(@srcset) or not(@sizes)]')->length === 0,
);

// No derivative may exceed its source: an upscaled asset would be soft, and the spec
// requires re-export rather than stretching.
$upscaled = 0;
foreach ($xp->query('//img[@class="gallery-tile__media"]') as $img) {
    foreach (explode(',', $img->getAttribute('srcset')) as $candidate) {
        $parts = preg_split('/\s+/', trim($candidate));
        $file = $root . '/' . ltrim($parts[0] ?? '', '/');
        if (is_file($file)) {
            $size = getimagesize($file);
            $declared = (int) rtrim($parts[1] ?? '0', 'w');
            if ($size !== false && $size[0] !== $declared) {
                ++$upscaled;
            }
        }
    }
}
check('srcset descriptors match actual derivative widths', $upscaled === 0, "{$upscaled} mismatched");

echo "\n=== Row independence ===\n";

$bare = render_harness($root . '/data/gallery.fixture.json', false)['html'];
$withSiblings = render_harness($root . '/data/gallery.fixture.json', true)['html'];

$extract = static function (string $doc): string {
    preg_match('/<section[^>]*data-gallery-row.*?<\/section>/s', $doc, $m);

    return $m[0] ?? '';
};

check(
    'row markup is byte-identical with placeholder rows above and below',
    $extract($bare) === $extract($withSiblings) && $extract($bare) !== '',
);

// Asserted as "one root element, anywhere in the page" rather than "a direct child of
// body" — the row must render correctly inside whatever container the page provides,
// so pinning it to a specific depth would test the harness rather than the row.
$roots = $xp->query('//*[@data-gallery-row]');
check('row is exactly one root element', $roots->length === 1);
check(
    'row root has no gallery siblings (self-contained)',
    $roots->length === 1
        && $xp->evaluate('count(../*[@data-gallery-row])', $roots->item(0)) === 1.0,
);

check(
    'row references no navigation, footer or sibling rows',
    !preg_match('/<(nav|footer|header)\b/i', $extract($bare)),
);

echo "\n=== Settings come from the row ===\n";

$section = $xp->query('//section[@data-gallery-row]')->item(0);
$style = $section->getAttribute('style');
$row = $fixture['row'];

check("gutter from row.tileGap ({$row['tileGap']}px)", str_contains($style, "--gallery-gap: {$row['tileGap']}px"));
check("card target from row.cardTarget ({$row['cardTarget']})", str_contains($style, "--gallery-card-target: {$row['cardTarget']}"));
check("canvas depth from row.minCanvasDepth ({$row['minCanvasDepth']})", str_contains($style, "--gallery-min-depth: {$row['minCanvasDepth']}"));
check("layout mode from row.layoutMode ({$row['layoutMode']})", $section->getAttribute('data-layout-mode') === $row['layoutMode']);
check('motion cap exposed from row.maxConcurrentMotion', $section->getAttribute('data-max-concurrent-motion') === (string) $row['maxConcurrentMotion']);

check(
    'welcome tile pinned to the first cell',
    $xp->query('//li[@data-pinned="first"][@data-tile-type="text"]')->length === 1,
);

printf("\n%s  %d checks, %d failures\n\n", $failures === 0 ? 'PASS' : 'FAIL', $checks, $failures);
exit($failures === 0 ? 0 : 1);
