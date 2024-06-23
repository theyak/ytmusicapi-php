<?php

namespace Ytmusicapi\Podcasts;

use function Ytmusicapi\nav;
use function Ytmusicapi\join;

class DescriptionElement
{
    /**
     * @var string
     */
    public $text;

    public function __construct($text)
    {
        $this->text = $text;
    }

    public function __toString()
    {
        return $this->text;
    }
}

class Link extends DescriptionElement
{
    /**
     * @var string
     */
    public $url;
}

class Timestamp extends DescriptionElement
{
    /**
     * @var int
     */
    public $seconds;
}

class Description
{
    /**
     * @var DescriptionElement[]
     */
    public $elements;

    public function __construct($elements)
    {
        $this->elements = $elements;
    }

    public function count()
    {
        return count($this->elements);
    }

    public function getItem($index)
    {
        return $this->elements[$index];
    }

    public function getText()
    {
        $text = "";
        foreach ($this->elements as $element) {
            $text .= (string)$element;
        }
        return $text;
    }

    public function __get($item)
    {
        if ($item === "text") {
            return $this->getText();
        }
        if ($item === "count" || $item === "length") {
            return $this->count();
        }
        return $this->getItem($item);
    }

    public function __toString1()
    {
        return $this->getText();
    }

    /**
     * parse the description runs into a usable format
     *
     * @param array $description_runs the original description runs
     * @return array List of text (str), timestamp (int) and link values (Link object)
     */
    public static function from_runs($description_runs)
    {
        $elements = [];

        foreach ($description_runs as $run) {
            $navigationEndpoint = nav($run, "navigationEndpoint", true);
            if ($navigationEndpoint) {
                $element = new DescriptionElement("");
                if (!empty($navigationEndpoint->urlEndpoint)) {
                    $element = new Link($run->text, $navigationEndpoint->urlEndpoint->url);
                } elseif (!empty($navigationEndpoint->watchEndpoint)) {
                    $element = new Timestamp(
                        $run->text,
                        nav($navigationEndpoint, "watchEndpoint.startTimeSeconds")
                    );
                }
            } else {
                $element = new DescriptionElement(nav($run, "text", true));
            }

            $elements[] = $element;
        }

        return new self($elements);
    }
}


namespace Ytmusicapi;

/**
 * parse common left hand side (header) items of an episode or podcast page
 *
 * @param object $header
 * @return object
 */
function parse_base_header($header)
{
    $strapline = nav($header, "straplineTextOne");
    return (object)[
        "author" => (object)[
            "name" => nav($strapline, RUN_TEXT),
            "id" => nav($strapline, join("runs.0", NAVIGATION_BROWSE_ID)),
        ],
        "title" => nav($header, TITLE_TEXT),
    ];
}

/**
 * @param object $header
 * @return object
 */
function parse_podcast_header($header)
{
    $metadata = parse_base_header($header);
    $metadata->description = nav(
        $header,
        join(
            "description",
            DESCRIPTION_SHELF,
            DESCRIPTION
        ),
        true
    );
    $metadata->saved = nav($header, join("buttons.1", TOGGLED_BUTTON));

    return $metadata;
}

/**
 * @param object $header
 * @return object
 */
function parse_episode_header($header)
{
    $metadata = parse_base_header($header);
    $metadata->date = nav($header, SUBTITLE2);
    $metadata->duration = nav($header, SUBTITLE3, true);
    if (!$metadata->duration) { // progress started
        $progress_renderer = nav($header, "progress.musicPlaybackProgressRenderer");
        $metadata->duration = nav($progress_renderer, "durationText.runs.1.text");
        $metadata->progressPercentage = nav($progress_renderer, "playbackProgressPercentage");
    }

    $metadata->saved = nav($header, join("buttons.0", TOGGLED_BUTTON), true);

    $metadata->playlistId = null;
    $menu_buttons = nav($header, join("buttons.-1.menuRenderer.items"));
    foreach ($menu_buttons as $button) {
        if (nav($button, join(MNIR, ICON_TYPE), true) === "BROADCAST") {
            $metadata->playlistId = nav($button, join(MNIR, NAVIGATION_BROWSE_ID));
        }
    }

    return $metadata;
}

/**
 * Parses a single episode under "Episodes" on a channel page or on a podcast page
 *
 * @param object $results
 * @return Episode[]
 */
function parse_episode($data)
{
    $thumbnails = nav($data, THUMBNAILS);
    $date = null;
    if (count(nav($data, SUBTITLE_RUNS)) === 1) {
        $duration = nav($data, SUBTITLE);
    } else {
        $date = nav($data, SUBTITLE);
        $duration = nav($data, SUBTITLE2, true);
    }

    $title = nav($data, join(TITLE_TEXT));
    $description = nav($data, DESCRIPTION, true);
    $videoId = nav($data, join("onTap", WATCH_VIDEO_ID), true);
    $browseId = nav($data, join(TITLE, NAVIGATION_BROWSE_ID), true);
    $videoType = nav($data, join("onTap", NAVIGATION_VIDEO_TYPE), true);
    $index = nav($data, "onTap.watchEndpoint.index", true);

    $episode = new Episode();
    $episode->index = $index;
    $episode->title = $title;
    $episode->description = $description;
    $episode->duration = $duration;
    $episode->videoId = $videoId;
    $episode->browseId = $browseId;
    $episode->videoType = $videoType;
    $episode->date = $date;
    $episode->thumbnails = $thumbnails;

    return $episode;
}

/**
 * Parses a single podcast under "Podcasts" on a channel or library page
 *
 * @param object $results
 * @return PodcastShelfItem
 */
function parse_podcast($data) {

    $shelf = new PodcastShelfItem();
    $shelf->title = nav($data, TITLE_TEXT);
    $shelf->channel = parse_id_name(nav($data, join(SUBTITLE_RUNS, 0), true));
    $shelf->browseId = nav($data, join(TITLE, NAVIGATION_BROWSE_ID));
    $shelf->podcastId = nav($data, THUMBNAIL_OVERLAY, true);
    $shelf->thumbnails = nav($data, THUMBNAIL_RENDERER);

    return $shelf;
}
