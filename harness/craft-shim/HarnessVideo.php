<?php

declare(strict_types=1);

namespace Sabin\Harness;

/**
 * Stands in for a Craft video Asset on a motion tile.
 *
 * Deliberately thinner than HarnessAsset: a video is never transformed through
 * ImagerX. The tile's visible still is its poster, which *is* a HarnessAsset and does
 * go through the transform pipeline. Keeping them separate types is what stops a
 * video being handed to an image transform by accident.
 */
final class HarnessVideo
{
    /**
     * @param string $path Absolute path to the video file.
     * @param string $url  Public URL as Craft would report it.
     */
    public function __construct(
        private readonly string $path,
        private readonly string $url,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /** MIME type for the <source> element. */
    public function getMimeType(): string
    {
        return match (strtolower(pathinfo($this->path, PATHINFO_EXTENSION))) {
            'mp4', 'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            default => 'application/octet-stream',
        };
    }

    /** Size on disk, for the page-weight budget. */
    public function getBytes(): int
    {
        return is_file($this->path) ? (int) filesize($this->path) : 0;
    }

    /** @see HarnessAsset::eagerly() */
    public function eagerly(): self
    {
        return $this;
    }

    /** @see HarnessAsset::one() */
    public function one(): self
    {
        return $this;
    }
}
