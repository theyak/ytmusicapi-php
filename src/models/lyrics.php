<?php

namespace Ytmusicapi\Models;

/**
 * Represents a line of lyrics with timestamps (in milliseconds).
 */
class LyricLine
{
    /**
     * @var string The song text.
     */
    public string $text;

    /**
     * @var int Begin of the lyric in milliseconds.
     */
    public int $start_time;

    /**
     * @var int End of the lyric in milliseconds.
     */
    public int $end_time;

    /**
     * @var int A Metadata-Id that probably uniquely identifies each lyric line.
     */
    public int $id;

    public function __construct(string $text, int $start_time, int $end_time, int $id)
    {
        $this->text = $text;
        $this->start_time = $start_time;
        $this->end_time = $end_time;
        $this->id = $id;
    }

    /**
     * Converts lyrics in the format from the api to a more reasonable format
     * 
     * @param object $raw_lyric The raw lyric-data returned by the mobile api.
     * @return LyricLine A LyricLine instance
     */
    public static function fromRaw(object $raw_lyric): LyricLine
    {
        $text = $raw_lyric->lyricLine;
        $cue_range = $raw_lyric->cueRange;
        $start_time = (int) $cue_range->startTimeMilliseconds;
        $end_time = (int) $cue_range->endTimeMilliseconds;
        $id = (int) $cue_range->metadata->id;
        
        return new self($text, $start_time, $end_time, $id);
    }
}

/**
 * Represents basic lyrics without timestamps.
 */
class Lyrics
{
    public string $lyrics;
    public ?string $source;
    public bool $hasTimestamps = false;

    public function __construct(string $lyrics, ?string $source = null)
    {
        $this->lyrics = $lyrics;
        $this->source = $source;
    }
}

/**
 * Represents timed lyrics with timestamp information.
 */
class TimedLyrics
{
    /** @var LyricLine[] */
    public array $lyrics;
    public ?string $source;
    public bool $hasTimestamps = true;

    /**
     * @param LyricLine[] $lyrics
     * @param string|null $source
     */
    public function __construct(array $lyrics, ?string $source = null)
    {
        $this->lyrics = $lyrics;
        $this->source = $source;
    }
}
