<?php

namespace Ytmusicapi;

/**
 * @param string|LikeStatus $rating
 * @return string
 */
function prepare_like_endpoint($rating): string
{
    if ($rating === LikeStatus::LIKE) {
        return 'like/like';
    } elseif ($rating === LikeStatus::DISLIKE) {
        return 'like/dislike';
    } elseif ($rating === LikeStatus::INDIFFERENT) {
        return 'like/removelike';
    } else {
        throw new YtMusicUserError("Invalid rating provided. Use one of: "
            . implode(', ', LikeStatus::cases())
        );
    }
}

/**
 * Validate the provided order, if any
 * 
 * @param string $order
 * @return void
 * 
 * @throws YtMusicUserError if the provided order is invalid
 */
function validate_order_parameter($order): void
{
    $orders = ['a_to_z', 'z_to_a', 'recently_added'];
    if ($order && !in_array($order, $orders)) {
        throw new YTMusicUserError(
            "Invalid order provided. Please use one of the following orders or leave out the parameter: "
            . implode(', ', $orders)
        );
    }
}

/**
 * Returns request params belonging to a specific sorting order.
 * 
 * @param string $order
 * @return string
 */
function prepare_order_params($order): string
{
    $orders = ['a_to_z', 'z_to_a', 'recently_added'];
    
    // determine order_params via `.contents.singleColumnBrowseResultsRenderer.tabs[0].tabRenderer.content.sectionListRenderer.contents[1].itemSectionRenderer.header.itemSectionTabbedHeaderRenderer.endItems[1].dropdownRenderer.entries[].dropdownItemRenderer.onSelectCommand.browseEndpoint.params` of `/youtubei/v1/browse` response
    $order_params = ['ggMGKgQIARAA', 'ggMGKgQIARAB', 'ggMGKgQIABAB'];
    return $order_params[array_search($order, $orders)];
}

/**
 * Sanitize tags from html
 * 
 * @param string $html_text Sanitize tags from html
 * @return string
 */
function html_to_txt($html_text)
{
    preg_match_all("/<[^>]+>/", $html_text, $matches);
    foreach ($matches[0] as $tag) {
        $html_text = str_replace($tag, '', $html_text);
    }
    return $html_text;
}

/**
 * Returns the number of days since January 1, 1970.
 * Currently only used for the signature timestamp in `get_song`
 */
function get_datestamp(): int
{
    $today = new \DateTimeImmutable('today');
    $epoch = new \DateTimeImmutable('@0'); // Epoch timestamp
    $interval = $today->diff($epoch);
    return (int)$interval->days;
}

/**
 * Advanced approach with reflection and type casting
 * 
 * @param string $className
 * @param mixed $data
 * @return object
 * 
 * @throws \InvalidArgumentException if the class does not exist
 */
function typingCast($className, $data) {
    if (!class_exists($className)) {
        throw new \InvalidArgumentException("Class {$className} does not exist");
    }
    
    // Convert to array if needed
    if ($data instanceof \stdClass) {
        $data = json_decode(json_encode($data), true);
    }
    
    $reflection = new \ReflectionClass($className);
    $object = $reflection->newInstance();
    
    foreach ($data as $key => $value) {
        if ($reflection->hasProperty($key)) {
            $property = $reflection->getProperty($key);
            
            // Make private/protected properties accessible
            if (!$property->isPublic()) {
                $property->setAccessible(true);
            }
            
            $property->setValue($object, $value);
        }
    }
    
    return $object;
}