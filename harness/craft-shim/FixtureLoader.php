<?php

declare(strict_types=1);

namespace Sabin\Harness;

use JsonException;

/**
 * Loads the fixture, validates it, and hydrates media paths into HarnessAsset objects.
 *
 * Validation is fail-fast and total: every rule runs and all violations are reported
 * together, before a single tile renders. No rule supplies a fallback value, because
 * a code-side default for a row field would put a gallery setting outside the row
 * (CLAUDE.md Principle #2) and mask a real fixture error.
 */
final class FixtureLoader
{
    /** @var string[] */
    private array $violations = [];

    /**
     * @param string $projectRoot Absolute path the fixture's root-relative media paths resolve against.
     */
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    /**
     * Loads and validates a fixture, returning the row ready to hand to Twig.
     *
     * @param string $fixturePath Path to the fixture JSON.
     *
     * @return array The `row` array, with media/poster/buildIcon hydrated to HarnessAsset.
     *
     * @throws FixtureValidationException On any violation.
     * @throws JsonException             If the fixture is not valid JSON.
     */
    public function load(string $fixturePath): array
    {
        if (!is_file($fixturePath)) {
            throw new FixtureValidationException(["Fixture not found: {$fixturePath}"]);
        }

        $data = json_decode(
            (string) file_get_contents($fixturePath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $row = $data['row'] ?? null;
        if (!is_array($row)) {
            throw new FixtureValidationException(['Fixture must have a top-level "row" object']);
        }

        $this->violations = [];
        $this->validateRow($row);

        $tiles = $row['tiles'] ?? null;
        if (!is_array($tiles) || $tiles === []) {
            $this->violations[] = 'row.tiles must be a non-empty array';
            throw new FixtureValidationException($this->violations);
        }

        $this->validateTiles($tiles);

        if ($this->violations !== []) {
            throw new FixtureValidationException($this->violations);
        }

        $row['tiles'] = array_map($this->hydrateTile(...), $tiles);
        $row['buildIcon'] = isset($row['buildIcon'])
            ? $this->asset($row['buildIcon'], '')
            : null;

        return $row;
    }

    // ---------------------------------------------------------------- row rules

    /** Applies the KnownEnums and PositiveNumbers rules to row-level settings. */
    private function validateRow(array $row): void
    {
        $this->requireEnum($row, 'theme', Tokens::THEMES, 'row');
        $this->requireEnum($row, 'layoutMode', Tokens::LAYOUT_MODES, 'row');

        foreach (['minCanvasDepth', 'tileGap', 'maxConcurrentMotion'] as $field) {
            if (!array_key_exists($field, $row)) {
                $this->violations[] = "row.{$field} is required (no code default is supplied)";
                continue;
            }
            if (!is_numeric($row[$field]) || $row[$field] < 0) {
                $this->violations[] = "row.{$field} must be a non-negative number";
            }
        }

        if (!isset($row['cardTarget']) || !is_string($row['cardTarget']) || $row['cardTarget'] === '') {
            $this->violations[] = 'row.cardTarget is required and must be a CSS length';
        }

        $inertia = $row['scrollInertia'] ?? null;
        if (!is_array($inertia)) {
            $this->violations[] = 'row.scrollInertia is required';

            return;
        }
        if (!isset($inertia['enabled']) || !is_bool($inertia['enabled'])) {
            $this->violations[] = 'row.scrollInertia.enabled must be a boolean';
        }
        $this->requireEnum($inertia, 'speed', Tokens::INERTIA_SPEEDS, 'row.scrollInertia');
        $this->requireEnum($inertia, 'feel', Tokens::INERTIA_FEELS, 'row.scrollInertia');
    }

    // --------------------------------------------------------------- tile rules

    /** Applies every per-tile rule, plus the document-wide uniqueness and welcome-tile rules. */
    private function validateTiles(array $tiles): void
    {
        $seen = [];

        foreach ($tiles as $i => $tile) {
            $id = $tile['id'] ?? null;
            if (!is_string($id) || $id === '') {
                $this->violations[] = "tiles[{$i}]: id is required";
                continue;
            }
            if (isset($seen[$id])) {
                $this->violations[] = "Duplicate tile id '{$id}' at tiles[{$i}]";
            }
            $seen[$id] = true;

            $this->validateSize($tile, $id);
            $this->requireEnum($tile, 'designation', Tokens::DESIGNATIONS, "Tile '{$id}'");

            $type = $tile['type'] ?? null;
            if (!in_array($type, Tokens::TILE_TYPES, true)) {
                $this->violations[] = "Tile '{$id}': unknown type '" . $this->show($type) . "'";
                continue;
            }

            $type === 'text'
                ? $this->validateTextTile($tile, $id)
                : $this->validateMediaTile($tile, $id);
        }

        // WelcomeTileFirst — the welcome message is a text tile pinned to the first cell,
        // so its position in the data is load-bearing rather than incidental.
        $first = $tiles[0] ?? null;
        if (($first['type'] ?? null) !== 'text') {
            $this->violations[] = 'tiles[0] must be the welcome text tile';
        }
    }

    /** SizeFormat — generic cols x rows, so a future 2x3 is a data change. */
    private function validateSize(array $tile, string $id): void
    {
        $size = $tile['size'] ?? null;
        if (!is_string($size) || preg_match('/^(\d+)x(\d+)$/', $size, $m) !== 1) {
            $this->violations[] = "Tile '{$id}': size '" . $this->show($size) . "' is not NxM";

            return;
        }
        if ((int) $m[1] < 1 || (int) $m[2] < 1) {
            $this->violations[] = "Tile '{$id}': size '{$size}' must have both spans >= 1";
        }
    }

    /** MediaPresent, AltPresent, PosterPresent, ContentTypeKnown, destination and buildLink rules. */
    private function validateMediaTile(array $tile, string $id): void
    {
        $this->requireMediaFile($tile, 'media', $id);

        if (!isset($tile['alt']) || !is_string($tile['alt']) || trim($tile['alt']) === '') {
            $this->violations[] = "Tile '{$id}': alt text is required on media tiles";
        }

        $ct = $tile['contentType'] ?? null;
        if (!isset(Tokens::CONTENT_TYPE_DESTINATIONS[$ct])) {
            $this->violations[] = "Tile '{$id}': contentType '" . $this->show($ct) . "' has no destination mapping";
        }

        if (($tile['type'] ?? null) === 'motion') {
            $this->requireMediaFile($tile, 'poster', $id, "Tile '{$id}': motion tiles require a poster");
            $this->requireEnum($tile, 'playback', Tokens::PLAYBACK, "Tile '{$id}'");
        }

        $this->validateLink($tile['destination'] ?? null, $id, 'destination', required: true);

        if (array_key_exists('buildLink', $tile) && $tile['buildLink'] !== null) {
            $this->validateLink($tile['buildLink'], $id, 'buildLink', required: false);
        }
    }

    /** NoBuildLinkOnText, NamedTokensOnly, CtaWhenCtaOnly. */
    private function validateTextTile(array $tile, string $id): void
    {
        if (array_key_exists('buildLink', $tile) && $tile['buildLink'] !== null) {
            $this->violations[] = "Tile '{$id}': text tiles cannot have a buildLink";
        }

        $text = $tile['text'] ?? null;
        if (!is_array($text)) {
            $this->violations[] = "Tile '{$id}': text tiles require a text object";

            return;
        }

        // Heading is required; body is optional. A tile carrying only a statement and a
        // button is a legitimate shape — the welcome tile is exactly that.
        if (!isset($text['heading']) || !is_string($text['heading']) || trim($text['heading']) === '') {
            $this->violations[] = "Tile '{$id}': text.heading is required";
        }

        if (isset($text['body'])) {
            if (!is_string($text['body']) || trim($text['body']) === '') {
                $this->violations[] = "Tile '{$id}': text.body must be non-empty when present";
            }
            // Which face the body uses is a named token, never a font name.
            $this->requireEnum($text, 'bodyStyle', Tokens::BODY_STYLES, "Tile '{$id}': text", 'bodyStyle');
        }

        // NamedTokensOnly — every styling value must resolve in its vocabulary, which
        // is what stops a free-form hex or pixel value reaching a template.
        $this->requireEnum($text, 'headingLevel', Tokens::HEADING_LEVELS, "Tile '{$id}': text", 'headingLevel');
        $this->requireEnum($text, 'background', Tokens::BACKGROUNDS, "Tile '{$id}': text", 'background');
        $this->requireEnum($text, 'padding', Tokens::PADDINGS, "Tile '{$id}': text", 'padding');
        $this->requireEnum($text, 'linkBehavior', Tokens::LINK_BEHAVIORS, "Tile '{$id}': text", 'linkBehavior');

        $cta = $text['cta'] ?? null;

        // 'whole-tile' genuinely needs somewhere to go. 'cta-only' without a cta is an
        // informational tile — it renders no link at all, which is valid content
        // (an event announcement, for instance) rather than a broken button.
        if (($text['linkBehavior'] ?? null) === 'whole-tile' && !is_array($cta)) {
            $this->violations[] = "Tile '{$id}': whole-tile requires a cta to link to";
        }

        if (is_array($cta)) {
            if (!isset($cta['label']) || !is_string($cta['label']) || trim($cta['label']) === '') {
                $this->violations[] = "Tile '{$id}': text.cta.label is required";
            }
            $this->requireEnum($cta, 'style', Tokens::CTA_STYLES, "Tile '{$id}': text.cta", 'style');
            $this->validateLink($cta, $id, 'text.cta', required: true);
        }

        if (isset($text['media']) && $text['media'] !== null) {
            $this->requireMediaFile($text, 'media', $id, "Tile '{$id}': text.media not found");
        }

        // A logo or mark inside the tile. Separate from text.media, which is motion:
        // one is a still graphic sitting in the layout, the other is a video.
        if (isset($text['image']) && $text['image'] !== null) {
            $this->requireMediaFile($text, 'image', $id, "Tile '{$id}': text.image not found");

            if (!isset($text['imageAlt']) || !is_string($text['imageAlt']) || trim($text['imageAlt']) === '') {
                $this->violations[] = "Tile '{$id}': text.imageAlt is required when text.image is set";
            }
        }
    }

    /**
     * Validates a link object's url and live flag.
     *
     * A link marked live must carry a url, because a live link with nowhere to go is
     * the dead interactive element the spec forbids. A link marked not-live may keep
     * its url as documentation of the eventual destination — it simply renders
     * non-interactive.
     */
    private function validateLink(mixed $link, string $id, string $field, bool $required): void
    {
        if ($link === null) {
            if ($required) {
                $this->violations[] = "Tile '{$id}': {$field} is required";
            }

            return;
        }

        if (!is_array($link)) {
            $this->violations[] = "Tile '{$id}': {$field} must be an object";

            return;
        }

        if (!isset($link['live']) || !is_bool($link['live'])) {
            $this->violations[] = "Tile '{$id}': {$field}.live must be a boolean";
        }

        $url = $link['url'] ?? null;
        if (($link['live'] ?? false) === true && (!is_string($url) || trim($url) === '')) {
            $this->violations[] = "Tile '{$id}': {$field} is live but has no url";
        }

        if (isset($link['type']) && !in_array($link['type'], Tokens::DESTINATION_TYPES, true)) {
            $this->violations[] = "Tile '{$id}': {$field}.type '" . $this->show($link['type']) . "' is not a valid destination type";
        }
    }

    // ------------------------------------------------------------------ helpers

    /** Records a violation unless $data[$field] is one of $allowed. */
    private function requireEnum(array $data, string $field, array $allowed, string $context, ?string $label = null): void
    {
        $label ??= $field;
        $value = $data[$field] ?? null;

        if (!in_array($value, $allowed, true)) {
            $this->violations[] = sprintf(
                "%s.%s: '%s' is not a valid option (expected %s)",
                $context,
                $label,
                $this->show($value),
                implode(', ', $allowed),
            );
        }
    }

    /** MediaPresent / PosterPresent — the referenced file must actually exist on disk. */
    private function requireMediaFile(array $data, string $field, string $id, ?string $message = null): void
    {
        $path = $data[$field] ?? null;
        if (!is_string($path) || $path === '') {
            $this->violations[] = $message ?? "Tile '{$id}': {$field} is required";

            return;
        }

        if (!is_file($this->resolve($path))) {
            $this->violations[] = "Tile '{$id}': {$field} '{$path}' not found";
        }
    }

    /**
     * Resolves a root-relative fixture media path to a filesystem path.
     *
     * Root-relative rather than basename-into-images/, so generated media (the
     * transcoded motion loop and its extracted poster, which live under dist/) can be
     * referenced the same way as the untouched sources in images/.
     */
    private function resolve(string $path): string
    {
        return $this->projectRoot . '/' . ltrim($path, '/');
    }

    /** Renders a value for an error message without exploding on arrays or null. */
    private function show(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => get_debug_type($value),
        };
    }

    // ---------------------------------------------------------------- hydration

    /** Converts a validated tile's media references into HarnessAsset instances. */
    private function hydrateTile(array $tile): array
    {
        if (($tile['type'] ?? null) === 'text') {
            if (isset($tile['text']['media'])) {
                $tile['text']['media'] = $this->asset($tile['text']['media'], '');
            }

            if (isset($tile['text']['image'])) {
                $tile['text']['image'] = $this->asset(
                    $tile['text']['image'],
                    $tile['text']['imageAlt'],
                );
            }

            return $tile;
        }

        // A motion tile's media is a video, which is never transformed; its poster is
        // the still that goes through ImagerX. An image tile's media is that still.
        if ($tile['type'] === 'motion') {
            $tile['media'] = new HarnessVideo($this->resolve($tile['media']), $tile['media']);
            $tile['poster'] = $this->asset($tile['poster'], $tile['alt'], $tile['focalPoint'] ?? null);

            return $tile;
        }

        $tile['media'] = $this->asset($tile['media'], $tile['alt'], $tile['focalPoint'] ?? null);

        return $tile;
    }

    /** Builds a HarnessAsset for a fixture media path. */
    private function asset(string $path, string $alt, ?array $focalPoint = null): HarnessAsset
    {
        return new HarnessAsset($this->resolve($path), $alt, $path, $focalPoint);
    }
}
