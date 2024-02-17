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

/**
 * parse common left hand side (header) items of an episode or podcast page
 *
 * @param object $header
 * @return object
 */
function _parse_base_header($header)
{
    $strapline = nav($header, "straplineTextOne");
    return (object)[
        "author" => (object)[
            "name" => nav($strapline, \Ytmusicapi\RUN_TEXT),
            "id" => nav($strapline, join("runs.0", \Ytmusicapi\NAVIGATION_BROWSE_ID)),
        ],
        "title" => nav($header, \Ytmusicapi\TITLE_TEXT),
    ];
}

/**
 * @param object $header
 * @return object
 */
function parse_podcast_header($header)
{
    $metadata = _parse_base_header($header);
    $metadata->description = nav(
        $header,
        join(
            "description",
            \Ytmusicapi\DESCRIPTION_SHELF,
            \Ytmusicapi\DESCRIPTION
        ),
        true
    );
    $metadata->saved = nav($header, join("buttons.1", \Ytmusicapi\TOGGLED_BUTTON));

    return $metadata;
}

/**
 * @param object $header
 * @return object
 */
function parse_episode_header($header)
{
    $metadata = _parse_base_header($header);
    $metadata->date = nav($header, \Ytmusicapi\SUBTITLE2);
    $metadata->duration = nav($header, \Ytmusicapi\SUBTITLE3);
    $metadata->saved = nav($header, join("buttons.0", \Ytmusicapi\TOGGLED_BUTTON), true);

    $metadata->playlistId = null;
    $menu_buttons = nav($header, join("buttons.-1.menuRenderer.items"));
    foreach ($menu_buttons as $button) {
        if (nav($button, join(\Ytmusicapi\MNIR, \Ytmusicapi\ICON_TYPE), true) === "BROADCAST") {
            $metadata->playlistId = nav($button, join(\Ytmusicapi\MNIR, \Ytmusicapi\NAVIGATION_BROWSE_ID));
        }
    }

    return $metadata;
}

/**
 * @param object $results
 * @return Episode[]
 */
function parse_episodes($results)
{
    $episodes = [];
    foreach ($results as $result) {
        $data = nav($result, "musicMultiRowListItemRenderer");
        if (count(nav($data, \Ytmusicapi\SUBTITLE_RUNS)) === 1) {
            $duration = nav($data, \Ytmusicapi\SUBTITLE);
        } else {
            $date = nav($data, \Ytmusicapi\SUBTITLE);
            $duration = nav($data, \Ytmusicapi\SUBTITLE2, true);
        }
        $title = nav($data, join(\Ytmusicapi\TITLE_TEXT));
        $description = nav($data, \Ytmusicapi\DESCRIPTION, true);
        $videoId = nav($data, join("onTap", \Ytmusicapi\WATCH_VIDEO_ID), true);
        $browseId = nav($data, join(\Ytmusicapi\TITLE, \Ytmusicapi\NAVIGATION_BROWSE_ID), true);
        $videoType = nav($data, join("onTap", \Ytmusicapi\NAVIGATION_VIDEO_TYPE), true);
        $index = nav($data, "onTap.watchEndpoint.index");

        $episode = new \Ytmusicapi\Episode();
        $episode->index = $index;
        $episode->title = $title;
        $episode->description = $description;
        $episode->duration = $duration;
        $episode->videoId = $videoId;
        $episode->browseId = $browseId;
        $episode->videoType = $videoType;
        $episode->date = $date;

        $episodes[] = $episode;
    }

    return $episodes;
}
