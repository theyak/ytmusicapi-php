<?php

namespace Ytmusicapi;

function prepare_like_endpoint($rating)
{
    if ($rating === 'LIKE') {
        return 'like/like';
    } elseif ($rating === 'DISLIKE') {
        return 'like/dislike';
    } elseif ($rating === 'INDIFFERENT') {
        return 'like/removelike';
    } else {
        return null;
    }
}

function validate_order_parameter($order)
{
    $orders = ['a_to_z', 'z_to_a', 'recently_added'];
    if ($order && !in_array($order, $orders)) {
        throw new YTMusicUserError(
            "Invalid order provided. Please use one of the following orders or leave out the parameter: "
            . implode(', ', $orders)
        );
    }
}

function prepare_order_params($order)
{
    $orders = ['a_to_z', 'z_to_a', 'recently_added'];
    if ($order) {
        // determine order_params via `.contents.singleColumnBrowseResultsRenderer.tabs[0].tabRenderer.content.sectionListRenderer.contents[1].itemSectionRenderer.header.itemSectionTabbedHeaderRenderer.endItems[1].dropdownRenderer.entries[].dropdownItemRenderer.onSelectCommand.browseEndpoint.params` of `/youtubei/v1/browse` response
        $order_params = ['ggMGKgQIARAA', 'ggMGKgQIARAB', 'ggMGKgQIABAB'];
        return $order_params[array_search($order, $orders)];
    }
}

function html_to_txt($html_text)
{
    preg_match_all("/<[^>]+>/", $html_text, $matches);
    foreach ($matches[0] as $tag) {
        $html_text = str_replace($tag, '', $html_text);
    }
    return $html_text;
}

/**
 * Advanced approach with reflection and type casting
 */
function typingCast(string $className, $data) {
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