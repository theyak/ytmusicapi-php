<?php

namespace Ytmusicapi;

function get_continuations(
    $results,
    $continuation_type,
    $limit,
    $request_func,
    $parse_func,
    $ctoken_path = "",
    $reloadable = false
) {
    $items = [];

    while (isset($results->continuations) && ($limit === null || count($items) < $limit)) {
        $additionalParams = $reloadable
            ? get_reloadable_continuation_params($results)
            : get_continuation_params($results, $ctoken_path);
        $response = $request_func($additionalParams);

        if (isset($response->continuationContents)) {
            $results = $response->continuationContents->$continuation_type;
        } else {
            break;
        }
        $contents = get_continuation_contents($results, $parse_func);
        if (count($contents) === 0) {
            break;
        }
        $items = array_merge($items, $contents);
    }

    return $items;
}

function get_validated_continuations(
    $results,
    $continuation_type,
    $limit,
    $per_page,
    $request_func,
    $parse_func,
    $ctoken_path = ""
) {
    $items = [];
    while (isset($results->continuations) && count($items) < $limit) {
        $additionalParams = get_continuation_params($results, $ctoken_path);
        $wrapped_parse_func = function ($raw_response) use ($parse_func, $continuation_type) {
            return  get_parsed_continuation_items($raw_response, $parse_func, $continuation_type);
        };
        $validate_func = function ($parsed) use ($per_page, $limit, $items) {
            return validate_response($parsed, $per_page, $limit, count($items));
        };

        $response = resend_request_until_parsed_response_is_valid(
            $request_func,
            $additionalParams,
            $wrapped_parse_func,
            $validate_func,
            3
        );
        $results = $response->results;
        $items = array_merge($items, $response->parsed);
    }

    return $items;
}

function get_parsed_continuation_items($response, $parse_func, $continuation_type)
{
    $results = $response->continuationContents->$continuation_type;
    return ['results' => $results, 'parsed' => get_continuation_contents($results, $parse_func)];
}

function get_reloadable_continuation_params($results)
{
    $ctoken = nav($results, ['continuations', 0, 'reloadContinuationData', 'continuation']);
    return get_continuation_string($ctoken);
}

function get_continuation_string($ctoken)
{
    return "&ctoken=" . $ctoken . "&continuation=" . $ctoken;
}

function get_continuation_params($results, $ctoken_path = '')
{
    $ctoken = nav($results, ['continuations', 0, 'next' . $ctoken_path . 'ContinuationData', 'continuation']);
    return get_continuation_string($ctoken);
}

function get_continuation_contents($continuation, $parse_func)
{
    foreach (['contents', 'items'] as $term) {
        if (isset($continuation->$term)) {
            return $parse_func($continuation->$term);
        }
    }
    return [];
}

function resend_request_until_parsed_response_is_valid(
    $request_func,
    $request_additional_params,
    $parse_func,
    $validate_func,
    $max_retries
) {
    $response = $request_func($request_additional_params);
    $parsed_object = $parse_func($response);
    $retry_counter = 0;
    while (!$validate_func($parsed_object) && $retry_counter < $max_retries) {
        $response = $request_func($request_additional_params);
        $attempt = $parse_func($response);
        if (count($attempt->parsed) > count($parsed_object->parsed)) {
            $parsed_object = $attempt;
        }
        $retry_counter++;
    }
    return (object)$parsed_object;
}

function validate_response($response, $per_page, $limit, $current_count)
{
    $response = (object)$response;
    $remaining_items_count = $limit - $current_count;
    $expected_items_count = min($per_page, $remaining_items_count);
    // response is invalid, if it has less items then minimal expected count
    return count($response->parsed) >= $expected_items_count;
}
