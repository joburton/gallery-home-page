<?php

declare(strict_types=1);

/**
 * Harness bootstrap — THROWAWAY. Never delivered to Foster.
 *
 * Its whole job is to make the row template's Craft idioms resolve outside Craft, so
 * the template itself can be authored exactly as Foster will receive it. Everything
 * Craft-shaped that the template touches is registered here:
 *
 *   craft.imagerx.transformImage / .srcset   ->  HarnessImagerX (GD-backed)
 *   asset.url / .alt / .focalPoint           ->  HarnessAsset getters
 *   asset.eagerly().one()                    ->  identity on HarnessAsset
 *   _macros/color                            ->  local stand-in macro
 *
 * If anything Craft-shaped has to be added to the row template later, it gets shimmed
 * here rather than worked around in the template.
 */

namespace Sabin\Harness;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require dirname(__DIR__) . '/vendor/autoload.php';

const PROJECT_ROOT = __DIR__ . '/..';

/**
 * Builds the Twig environment with the Craft surface registered.
 *
 * strict_variables is deliberately on: a typo in a row field name should be a loud
 * error, not a silently empty string that looks like a styling bug.
 */
function harness_twig(HarnessImagerX $imagerx): Environment
{
    $twig = new Environment(
        new FilesystemLoader([
            PROJECT_ROOT . '/templates',
            __DIR__,
        ]),
        [
            'strict_variables' => true,
            'autoescape' => 'html',
            'cache' => false,
            'debug' => true,
        ],
    );

    // The template calls craft.imagerx.transformImage(...) exactly as it will in
    // production. In Craft this global is provided by the CMS; here we provide it.
    $twig->addGlobal('craft', new class ($imagerx) {
        public function __construct(public readonly HarnessImagerX $imagerx)
        {
        }
    });

    return $twig;
}

/**
 * Loads and validates the fixture, then renders the row through Twig.
 *
 * @param string $fixturePath  Path to the fixture JSON.
 * @param bool   $withSiblings Wrap the row in placeholder blocks above and below,
 *                             proving it depends on nothing around it.
 * @param array  $overrides    Harness-only row-field overrides, applied after validation
 *                             so the prototype can be driven from the URL. Never a
 *                             visitor-facing mechanism — nothing equivalent ships.
 *
 * @return array{html: string, audit: MediaAudit, derivativeBytes: int}
 *
 * @throws FixtureValidationException On any fixture violation.
 */
function render_harness(string $fixturePath, bool $withSiblings = false, array $overrides = []): array
{
    $imagerx = new HarnessImagerX(
        cacheDir: PROJECT_ROOT . '/dist/transforms',
        cacheUrl: '/dist/transforms',
        quality: 72,   // large rungs (>=700px) drop to 64 — see HarnessImagerX::write()
    );

    $loader = new FixtureLoader(projectRoot: PROJECT_ROOT);

    $row = $loader->load($fixturePath);

    foreach ($overrides as $field => $value) {
        // Merge rather than replace for nested settings, so ?inertia=off does not
        // discard the speed and feel the fixture set.
        $row[$field] = is_array($value) && is_array($row[$field] ?? null)
            ? array_replace($row[$field], $value)
            : $value;
    }

    $audit = new MediaAudit();
    $audit->inspect($row['tiles']);
    // Surfaces media sitting in images/ that no tile references — the folder is not
    // scanned, so without this the files simply never appear and nothing says why.
    $audit->inspectUnreferenced(PROJECT_ROOT . '/images', $row['tiles']);

    $twig = harness_twig($imagerx);

    $html = $twig->render('harness.twig', [
        'row' => $row,
        'withSiblings' => $withSiblings,
    ]);

    return [
        'html' => $html,
        'audit' => $audit,
        'derivativeBytes' => $imagerx->bytesProduced(),
    ];
}
