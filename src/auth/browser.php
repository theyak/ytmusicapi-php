<?php

namespace Ytmusicapi;

/**
 * Determine if using browser cookie authentication
 *
 * @param object $headers
 * @return bool True if headers are valid browser cookies, false otherwise
 */
function is_browser($headers)
{
    $headers = (array)$headers;
    $headers = array_change_key_case($headers, CASE_LOWER);
    return !empty($headers['cookie']);
}

function setup_browser($filepath = null, $headers_raw = null)
{
    $contents = [];
    if (!$headers_raw) {
        echo "Please paste the request headers from Firefox and press [Enter] twice to continue:\n";
        while (true) {
            try {
                $line = trim(readline());
                if (!$line) {
                    break;
                }
            } catch (\Exception $e) {
                break;
            }
            $contents[] = $line;
        }
    } else {
        $contents = explode("\n", $headers_raw);
    }

    try {
        $user_headers = [];
        $chrome_remembered_key = "";
        foreach ($contents as $content) {
            $header = explode(": ", $content);
            if (substr($header[0], 0, 1) == ":") { // nothing was split or chromium headers
                continue;
            }
            if (substr($header[0], -1) == ":") { // weird new chrome "copy-paste in separate lines" format
                $chrome_remembered_key = str_replace(":", "", $content);
            }
            if (count($header) == 1) {
                if ($chrome_remembered_key) {
                    $user_headers[$chrome_remembered_key] = $header[0];
                }
                continue;
            }

            $user_headers[strtolower($header[0])] = implode(": ", array_slice($header, 1));
        }
    } catch (\Exception $e) {
        throw new \Exception("Error parsing your input, please try again. Full error: {$e->getMessage()}");
    }

    $user_headers = array_change_key_case($user_headers, CASE_LOWER);

    $missing_headers = array_diff(["cookie", "x-goog-authuser"], array_map("strtolower", array_keys($user_headers)));
    if ($missing_headers) {
        throw new \Exception(
            "The following entries are missing in your headers: " . implode(", ", $missing_headers)
            . ". Please try a different request (such as /browse) and make sure you are logged in."
        );
    }

    $ignore_headers = ["host", "content-length", "accept-encoding"];
    foreach ($user_headers as $key => $value) {
        if (substr($key, 0, 3) === "sec" || in_array($key, $ignore_headers)) {
            unset($user_headers[$key]);
        }
    }

    $init_headers = initialize_headers();
    $user_headers = array_merge($user_headers, $init_headers);
    $headers = $user_headers;

    if ($filepath) {
        file_put_contents($filepath, json_encode($headers, JSON_PRETTY_PRINT));
    }

    return json_encode($headers, JSON_PRETTY_PRINT);
}
