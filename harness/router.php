<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in dev server — THROWAWAY. Never delivered to Foster.
 *
 * Serves static files (derivatives, source media, the stylesheet) straight from
 * disk, and renders the harness page for everything else. Rendering on each request
 * means editing the fixture or a template and reloading is enough to see the change,
 * which is the whole point of a prototyping harness.
 */

namespace Sabin\Harness;

require __DIR__ . '/render.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/..' . $path;

// Let the dev server handle real files itself, but keep the request inside the
// project — a dev-only server still should not serve arbitrary paths.
$realBase = realpath(__DIR__ . '/..');
$realFile = realpath($file);

if ($realFile !== false
    && is_file($realFile)
    && str_starts_with($realFile, (string) $realBase)
) {
    // The dev server sends no caching headers, which shows up as a real finding in
    // any audit run against the harness. Production caching is Foster's server's job;
    // these headers just stop the harness misreporting the row.
    if (preg_match('#^/(dist|images)/#', $path)) {
        header('Cache-Control: public, max-age=31536000, immutable');
    }

    return false;
}

// Lighthouse and crawlers request /robots.txt. Without this the catch-all below
// answers with the HTML page, which reads as a malformed robots.txt.
if ($path === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    // Permissive on purpose. A Disallow here would fail an SEO audit's is-crawlable
    // check, making the score measure the harness's robots policy rather than the
    // row's crawlability — which is the thing under test. The harness only ever runs
    // on localhost, so there is nothing to protect.
    echo "# Local prototyping harness — never deployed.\nUser-agent: *\nAllow: /\n";

    return true;
}

// 204 rather than 404: the harness has no favicon, and a 404 logs a console error that
// shows up as an audit finding against the row.
if ($path === '/favicon.ico') {
    http_response_code(204);

    return true;
}

$fixture = __DIR__ . '/../data/gallery.fixture.json';
$withSiblings = ($_SERVER['GALLERY_WITH_SIBLINGS'] ?? getenv('GALLERY_WITH_SIBLINGS')) === '1';

/**
 * HARNESS-ONLY query overrides, so the prototype can be driven without editing the
 * fixture between every reload: ?layout=masonry&gap=16&inertia=off&depth=3
 *
 * These are emphatically NOT visitor-facing controls — they exist only in the
 * throwaway harness and no equivalent ships in the row. In production every one of
 * these values comes from Craft. They are here because the brief asks that the
 * preview let the gallery "have its layout mode flipped, its inertia toggled".
 */
$overrides = array_filter([
    'layoutMode' => $_GET['layout'] ?? null,
    'tileGap' => isset($_GET['gap']) ? (int) $_GET['gap'] : null,
    'minCanvasDepth' => isset($_GET['depth']) ? (int) $_GET['depth'] : null,
    'cardTarget' => $_GET['card'] ?? null,
    'theme' => $_GET['theme'] ?? null,
], static fn ($v): bool => $v !== null);

$inertia = array_filter([
    'enabled' => isset($_GET['inertia']) ? $_GET['inertia'] !== 'off' : null,
    'speed' => $_GET['speed'] ?? null,
    'feel' => $_GET['feel'] ?? null,
], static fn ($v): bool => $v !== null);

if ($inertia !== []) {
    $overrides['scrollInertia'] = $inertia;
}

try {
    $result = render_harness($fixture, $withSiblings, $overrides);
} catch (FixtureValidationException $e) {
    // A fixture error is shown in the browser rather than only in the terminal,
    // because the fixture is the thing being edited during prototyping.
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    printf(
        '<!doctype html><meta charset="utf-8"><title>Fixture invalid</title>'
        . '<body style="font:14px/1.6 ui-monospace,monospace;padding:2rem;max-width:60rem">'
        . '<h1 style="font:600 1.25rem/1.3 system-ui">Fixture invalid</h1>'
        . '<p>%d violation(s) in <code>data/gallery.fixture.json</code>. Nothing rendered.</p><ul>%s</ul>',
        count($e->getViolations()),
        implode('', array_map(
            static fn (string $v): string => '<li>' . htmlspecialchars($v, ENT_QUOTES) . '</li>',
            $e->getViolations(),
        )),
    );

    return true;
}

// The media audit belongs in the terminal, not the page — it is a build report for
// whoever is running the preview, not content for a visitor. error_log() rather than
// STDERR because an unqualified constant would resolve into this namespace.
error_log("\n" . $result['audit']->format());

header('Content-Type: text/html; charset=utf-8');
echo $result['html'];

return true;
