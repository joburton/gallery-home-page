<?php

declare(strict_types=1);

namespace Sabin\Harness;

/**
 * The named brand tokens and enums a fixture may use.
 *
 * This is the single place the vocabulary is defined, which is what makes
 * "no free-form colour, size or spacing value" enforceable rather than aspirational:
 * validation rejects anything not listed here, so an arbitrary hex or pixel value
 * cannot reach a template.
 *
 * These names are also the rename surface for the Phase 3 handoff — Foster's real
 * type scale and palette token names are not yet known, so confining them here keeps
 * the eventual rename to one file plus the matching CSS.
 */
final class Tokens
{
    /** Row-level enums. */
    public const THEMES = ['light', 'dark'];
    public const LAYOUT_MODES = ['swiss', 'masonry'];
    public const INERTIA_SPEEDS = ['slow', 'medium', 'fast'];
    public const INERTIA_FEELS = ['smooth', 'glide', 'overshoot'];

    /** Tile-level enums. */
    public const TILE_TYPES = ['image', 'motion', 'text'];
    public const DESIGNATIONS = ['static', 'dynamic'];
    public const PLAYBACK = ['autoplay', 'click'];

    /**
     * contentType => primary destination kind.
     *
     * The mapping is the spec's routing rule expressed as data, so a tile's primary
     * destination follows from its content rather than from its filename or folder.
     */
    public const CONTENT_TYPE_DESTINATIONS = [
        'in-situ' => 'product',
        'mid-zoom' => 'product',
        'detail' => 'product',
        'sketch' => 'product',
        'swatch' => 'swatches',
        'factory' => 'about',
    ];

    /** Text-tile background tokens, drawn from the brand palette. */
    public const BACKGROUNDS = [
        'orange', 'yellow', 'dark-gray', 'white', 'light-gray', 'off-white',
    ];

    /** Named padding steps. */
    public const PADDINGS = ['sm', 'md', 'lg'];

    /** Named type-scale levels. */
    public const HEADING_LEVELS = ['display', 'title', 'subtitle'];

    /** Named CTA styles. */
    public const CTA_STYLES = ['primary', 'secondary', 'quiet'];

    /** Text-tile link behaviours. */
    public const LINK_BEHAVIORS = ['cta-only', 'whole-tile'];

    /** Destination kinds a tile may route to. */
    public const DESTINATION_TYPES = ['product', 'swatches', 'about', 'external'];
}
