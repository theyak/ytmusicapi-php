<?php

namespace Ytmusicapi;

/**
 * @param object $results;
 */
function get_continuation_token($results): ?string
{
    $CONTINUATION_TOKEN = "continuationItemRenderer.continuationEndpoint.continuationCommand.token";
    $COMMAND_EXECUTOR_COMMANDS = join(
        "continuationItemRenderer",
        "continuationEndpoint",
        "commandExecutorCommand",
        "commands",
    );

    $last_result = end($results);

    $token = nav($last_result, $CONTINUATION_TOKEN, true);
    if ($token) {
        return $token;
    }

    // continuation tokens may be nested in a commandExecutorCommand list
    // (alongside playlistVotingRefreshPopupCommand, for example)
    $commands = nav($last_result, $COMMAND_EXECUTOR_COMMANDS, true) ?? [];
    foreach ($commands as $command) {
        if (nav($command, "continuationCommand.request", true) === "CONTINUATION_REQUEST_TYPE_BROWSE") {
            return nav($command, "continuationCommand.token");
        }
    }

    return null;
}

/**
 * @param object $results
 * @param int|null $limit
 * @param callable $request_func
 * @param callable $parse_func
 * @return array
 */
function get_continuations_2025($results, $limit, $request_func, $parse_func)
{
    $CONTINUATION_ITEMS = "onResponseReceivedActions.0.appendContinuationItemsAction.continuationItems";

    $items = [];
    $continuation_token = get_continuation_token($results->contents);

    while ($continuation_token && ($limit === null || count($items) < $limit)) {
        $response = $request_func(["continuation" => $continuation_token]);
        $continuation_items = nav($response, $CONTINUATION_ITEMS, true);
        if (!$continuation_items) {
            break;
        }

        $contents = $parse_func($continuation_items);
        if (count($contents) <= 0) {
            break;
        }
        $items = array_merge($items, $contents);
        $continuation_token = get_continuation_token($continuation_items);
    }

    return $items;
}

/**
 * Reloadable continuations are a special case that only exists on the playlists page (suggestions).
 *
 * @param object $results
 * @param string $continuation_type
 * @param int|null $limit
 * @param callable $request_func
 * @param callable $parse_func
 * @return array
 */
function get_reloadable_continuations($results, $continuation_type, $limit, $request_func, $parse_func)
{
    $additionalParams = get_reloadable_continuation_params($results);
    return get_continuations($results, $continuation_type, $limit, $request_func, $parse_func, $additionalParams);
}

/**
 * @param object $results result list from request data
 * @param string $continuation_type type of continuation,
 *    determines which subkey will be used to navigate the continuation return data
 * @param int|null $limit determines minimum of how many items to retrieve in total.
 *    Null to retrieve all items until no more continuations are returned
 * @param callable $request_func the request func to use to get the continuations
 * @param callable $parse_func the parse func to apply on the returned continuations
 * @param string $ctoken_path rarely used specifier applied to retrieve the ctoken ("next<ctoken_path>ContinuationData").
 * @param string $additionalParams additional params to pass to the request func. Default: use get_continuation_params
 * @return array list of parsed continuation results
 */
function get_continuations(
    $results,
    $continuation_type,
    $limit,
    $request_func,
    $parse_func,
    $ctoken_path = "",
    $additionalParams = ""
) {
    $items = [];

    while (isset($results->continuations) && ($limit === null || count($items) < $limit)) {
        $additional_params = $additionalParams ?: get_continuation_params($results, $ctoken_path);
        $response = $request_func($additional_params);

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

/**
 * @param object $results
 * @param string $continuation_type
 * @param int $limit
 * @param int $per_page
 * @param callable $request_func
 * @param callable $parse_func
 * @param string $ctoken_path
 */
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

/**
 * @param object $response
 * @param callable $parse_func
 * @param string $continuation_type
 * @return array
 */
function get_parsed_continuation_items($response, $parse_func, $continuation_type)
{
    $results = $response->continuationContents->$continuation_type;
    return ['results' => $results, 'parsed' => get_continuation_contents($results, $parse_func)];
}

/**
 * @param object $results
 * @param string $ctoken_path
 * @return string
 */
function get_continuation_params($results, $ctoken_path = '')
{
    $continuations = nav($results, 'continuations');

    $ctoken = nav($results, ['continuations', 0, 'next' . $ctoken_path . 'ContinuationData', 'continuation'], true);
    if ($ctoken) {
        return get_continuation_string($ctoken);
    }
}

/**
 * @param object $results
 * @return string
 */
function get_reloadable_continuation_params($results)
{
    $ctoken = nav($results, ['continuations', 0, 'reloadContinuationData', 'continuation']);
    return get_continuation_string($ctoken);
}

/**
 * @param string $ctoken
 * @return string
 */
function get_continuation_string($ctoken)
{
    return "&ctoken=" . $ctoken . "&continuation=" . $ctoken;
}

/**
 * @param object $continuation
 * @param callable $parse_func
 * @return array
 */
function get_continuation_contents($continuation, $parse_func)
{
    foreach (['contents', 'items'] as $term) {
        if (isset($continuation->$term)) {
            return $parse_func($continuation->$term);
        }
    }
    return [];
}

/**
 * @param callable $request_func
 * @param string $request_additional_params
 * @param callable $parse_func
 * @param callable $validate_func
 * @param int $max_retries
 * @return object
 */
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

/**
 * @param object $response
 * @param int $per_page
 * @param int $limit
 * @param int $current_count
 * @return bool
 */
function validate_response($response, $per_page, $limit, $current_count)
{
    $response = (object)$response;
    $remaining_items_count = $limit - $current_count;
    $expected_items_count = min($per_page, $remaining_items_count);
    // response is invalid, if it has less items then minimal expected count
    return count($response->parsed) >= $expected_items_count;
}
