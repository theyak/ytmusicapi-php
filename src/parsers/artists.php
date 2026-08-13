<?php

namespace Ytmusicapi;

/**
 * Returns artist names and IDs. Skips every other run to avoid separators.
 *
 * @param array $runs
 */
function parse_artists_runs($runs)
{
    $artists = [];

    for ($j = 0; $j < (int) (count($runs) / 2) + 1; $j++) {
        $index = $j * 2;

        $artists[] = (object)[
            "name" => nav($runs[$index], "text", true),
            "id" => nav($runs[$index], NAVIGATION_BROWSE_ID, true),
        ];
    }

    return $artists;
}