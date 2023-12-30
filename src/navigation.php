<?php

namespace Ytmusicapi;

// # commonly used navigation paths
define("Ytmusicapi\CONTENT", "contents.0");
define("Ytmusicapi\RUN_TEXT", "runs.0.text");
define("Ytmusicapi\TAB_CONTENT", "tabs.0.tabRenderer.content");
define("Ytmusicapi\TAB_1_CONTENT", "tabs.1.tabRenderer.content");
define("Ytmusicapi\SINGLE_COLUMN", "contents.singleColumnBrowseResultsRenderer");
define("Ytmusicapi\SINGLE_COLUMN_TAB", "contents.singleColumnBrowseResultsRenderer.tabs.0.tabRenderer.content");
define("Ytmusicapi\SECTION_LIST", "sectionListRenderer.contents");
define("Ytmusicapi\SECTION_LIST_ITEM", "sectionListRenderer.contents.0");
define("Ytmusicapi\ITEM_SECTION", "itemSectionRenderer.contents.0");
define("Ytmusicapi\MUSIC_SHELF", "musicShelfRenderer");
define("Ytmusicapi\GRID", "gridRenderer");
define("Ytmusicapi\GRID_ITEMS", "gridRenderer.items");
define("Ytmusicapi\MENU", "menu.menuRenderer");
define("Ytmusicapi\MENU_ITEMS", "menu.menuRenderer.items");
define("Ytmusicapi\MENU_LIKE_STATUS", "menu.menuRenderer.topLevelButtons.0.likeButtonRenderer.likeStatus");
define("Ytmusicapi\MENU_SERVICE", "menuServiceItemRenderer.serviceEndpoint");
define("Ytmusicapi\TOGGLE_MENU", "toggleMenuServiceItemRenderer");
define("Ytmusicapi\PLAY_BUTTON", "overlay.musicItemThumbnailOverlayRenderer.content.musicPlayButtonRenderer");
define("Ytmusicapi\NAVIGATION_BROWSE", "navigationEndpoint.browseEndpoint");
define("Ytmusicapi\NAVIGATION_BROWSE_ID", "navigationEndpoint.browseEndpoint.browseId");
define("Ytmusicapi\PAGE_TYPE", "browseEndpointContextSupportedConfigs.browseEndpointContextMusicConfig.pageType");
define("Ytmusicapi\WATCH_VIDEO_ID", "watchEndpoint.videoId");
define("Ytmusicapi\NAVIGATION_VIDEO_ID", "navigationEndpoint.watchEndpoint.videoId");
define("Ytmusicapi\QUEUE_VIDEO_ID", "queueAddEndpoint.queueTarget.videoId");
define("Ytmusicapi\NAVIGATION_PLAYLIST_ID", "navigationEndpoint.watchEndpoint.playlistId");
define("Ytmusicapi\NAVIGATION_WATCH_PLAYLIST_ID", "navigationEndpoint.watchPlaylistEndpoint.playlistId");
define("Ytmusicapi\NAVIGATION_WATCH_PLAYLIST_ID2", "navigationEndpoint.watchEndpoint.playlistId");
define("Ytmusicapi\NAVIGATION_VIDEO_TYPE", "watchEndpoint.watchEndpointMusicSupportedConfigs.watchEndpointMusicConfig.musicVideoType");
define("Ytmusicapi\TITLE", "title.runs.0");
define("Ytmusicapi\TITLE_TEXT", "title.runs.0.text");
define("Ytmusicapi\TEXT_RUNS", "text.runs");
define("Ytmusicapi\TEXT_RUN", "text.runs.0");
define("Ytmusicapi\TEXT_RUN_TEXT", "text.runs.0.text");
define("Ytmusicapi\SUBTITLE", "subtitle.runs.0.text");
define("Ytmusicapi\SUBTITLE_RUNS", "subtitle.runs");
define("Ytmusicapi\SUBTITLE2", "subtitle.runs.2.text");
define("Ytmusicapi\SUBTITLE3", "subtitle.runs.4.text");
define("Ytmusicapi\THUMBNAIL", "thumbnail.thumbnails");
define("Ytmusicapi\THUMBNAILS", "thumbnail.musicThumbnailRenderer.thumbnail.thumbnails");
define("Ytmusicapi\THUMBNAIL_RENDERER", "thumbnailRenderer.musicThumbnailRenderer.thumbnail.thumbnails");
define("Ytmusicapi\THUMBNAIL_CROPPED", "thumbnail.croppedSquareThumbnailRenderer.thumbnail.thumbnails");
define("Ytmusicapi\FEEDBACK_TOKEN", "feedbackEndpoint.feedbackToken");
define("Ytmusicapi\BADGE_PATH", "0.musicInlineBadgeRenderer.accessibilityData.accessibilityData.label");
define("Ytmusicapi\BADGE_LABEL", "badges.0.musicInlineBadgeRenderer.accessibilityData.accessibilityData.label");
define("Ytmusicapi\SUBTITLE_BADGE_LABEL", "subtitleBadges.0.musicInlineBadgeRenderer.accessibilityData.accessibilityData.label");
define("Ytmusicapi\CATEGORY_TITLE", "musicNavigationButtonRenderer.buttonText.runs.0.text");
define("Ytmusicapi\CATEGORY_PARAMS", "musicNavigationButtonRenderer.clickCommand.browseEndpoint.params");
define("Ytmusicapi\MRLIR", "musicResponsiveListItemRenderer");
define("Ytmusicapi\MTRIR", "musicTwoRowItemRenderer");
define("Ytmusicapi\TASTE_PROFILE_ITEMS", "contents.tastebuilderRenderer.contents");
define("Ytmusicapi\TASTE_PROFILE_ARTIST", "title.runs");
define("Ytmusicapi\SECTION_LIST_CONTINUATION", "continuationContents.sectionListContinuation");
define("Ytmusicapi\MENU_PLAYLIST_ID", "menu.menuRenderer.items.0.menuNavigationItemRenderer.navigationEndpoint.watchPlaylistEndpoint.playlistId");
define("Ytmusicapi\HEADER_DETAIL", "header.musicDetailHeaderRenderer");
define("Ytmusicapi\DESCRIPTION_SHELF", "musicDescriptionShelfRenderer");
define("Ytmusicapi\DESCRIPTION", "description.runs.0.text");
define("Ytmusicapi\CAROUSEL", "musicCarouselShelfRenderer");
define("Ytmusicapi\IMMERSIVE_CAROUSEL", "musicImmersiveCarouselShelfRenderer");
define("Ytmusicapi\CAROUSEL_CONTENTS", "musicCarouselShelfRenderer.contents");
define("Ytmusicapi\CAROUSEL_TITLE", "header.musicCarouselShelfBasicHeaderRenderer.title.runs.0");
define("Ytmusicapi\CARD_SHELF_TITLE", "header.musicCardShelfHeaderBasicRenderer.title.runs.0.text");
define("Ytmusicapi\FRAMEWORK_MUTATIONS", "frameworkUpdates.entityBatchUpdate.mutations");

/**
 * Create a nested array from a string of keys sepatated by dots.
 * Useful for creating testing mocks.
 *
 * @param string $keys
 * @param mixed $value
 * @return array The nested array.
 */
function denav($keys, $value = null)
{
    $keys = explode('.', $keys);
    $result = [];

    // Start from the end and work our way up
    for ($i = count($keys) - 1; $i >= 0; $i--) {
        if ($i == count($keys) - 1) {
            // Insert the value at the deepest level
            $result = [$keys[$i] => $value];
        } else {
            // Nest the previously created array under the current key
            $result = [$keys[$i] => $result];
        }
    }

    return $result;
}

function nav($root, $items, $null_if_absent = false)
{
    if (is_string($items)) {
        $items = explode('.', $items);
    }

    if (!$root) {
        if ($null_if_absent) {
            return null;
        } else {
            throw new \Exception("Root is null");
        }
    }

    foreach ($items as $k) {
        if (is_array($root)) {
            if ($k === "-1" || $k === -1) {
                $root = end($root);
            } elseif (array_key_exists($k, $root)) {
                $root = $root[$k];
            } else {
                if ($null_if_absent) {
                    return null;
                } else {
                    throw new \Exception("Key not found: $k");
                }
            }
        } else {
            if (property_exists($root, $k)) {
                $root = $root->$k;
            } else {
                if ($null_if_absent) {
                    return null;
                } else {
                    print_r($root);
                    print_r(array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 0, 2));
                    throw new \Exception("Key not found: $k");
                }
            }
        }
    }
    return $root;
}

function find_object_by_key($object_list, $key, $nested = null, $is_key = false)
{
    foreach ($object_list as $item) {
        if ($nested) {
            if (is_array($item)) {
                $item = $item[$nested];
            } elseif (is_object($item)) {
                $item = $item->$nested;
            }
        }

        if (is_array($item) && array_key_exists($key, $item)) {
            return $is_key ? $item[$key] : $item;
        }

        if (is_object($item) && property_exists($item, $key)) {
            return $is_key ? $item->$key : $item;
        }
    }
    return null;
}

function find_objects_by_key($object_list, $key)
{
    $objects = [];
    foreach ($object_list as $item) {
        if (is_array($item)) {
            if (array_key_exists($key, $item)) {
                $objects[] = $item;
            }
        } elseif (is_object($item)) {
            if (property_exists($item, $key)) {
                $objects[] = $item;
            }
        }
    }

    return $objects;
}

function join()
{
    $args = func_get_args();

    foreach ($args as $key => $value) {
        if (is_array($value)) {
            $args[$key] = implode(".", $value);
        }
    }

    return implode(".", $args);
}
