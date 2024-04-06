<?php

use Ytmusicapi\YTMusic;

test('get_podcast', function () {
    $yt = new YTMusic();
    $podcast_id = $this->podcast_id;
    $results = $yt->get_podcast($podcast_id);
    expect(count($results->episodes))->toBeLessThanOrEqual(100);
    expect($results->saved)->toBeFalse();
});

test('many_podcasts', function () {
    $yt = new YTMusic();
    $results = $yt->search("podcast", filter: "podcasts");
    expect(count($results))->toBeGreaterThan(0);
});

test('get_episode', function () {
    $yt = new YTMusic();
    $episode_id = $this->episode_id;
    $result = $yt->get_episode($episode_id);
    expect(strlen($result->description->text))->toBeGreaterThan(50);
    expect($result->saved)->toBeEmpty();
    expect($result->playlistId)->not->toBeNull();
});

test('many_episodes', function () {
    $yt = new YTMusic();
    $results = $yt->search("episode", filter: "episodes");
    expect(count($results))->toBeGreaterThan(0);
    foreach ($results as $result) {
        expect($result->videoId)->not->toBeEmpty();
    }
});

// https://music.youtube.com/channel/UCGwuxdEeCf0TIA2RbPOj-8g
test("test_get_channel", function () {
    $channel_id = "UCGwuxdEeCf0TIA2RbPOj-8g"; // Stanford Graduate School of Business

    $yt = new YTMusic();
    $channel = $yt->get_channel($channel_id);
    expect(count($channel->episodes->results))->toBe(10);
    expect(count($channel->podcasts->results))->toBeGreaterThan(5);
});

test("test_get_channel_episodes", function () {
    $channel_id = "UCGwuxdEeCf0TIA2RbPOj-8g"; // Stanford Graduate School of Business

    $yt = new YTMusic("oauth.yaml");
    $channel = $yt->get_channel($channel_id);
    $channel_episodes = $yt->get_channel_episodes($channel_id, $channel->episodes->params);
    expect(count($channel_episodes))->toBeGreaterThan(150);
    expect(count($channel_episodes[0]))->toBe(9);
})->only();
