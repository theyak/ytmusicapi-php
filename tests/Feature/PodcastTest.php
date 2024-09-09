<?php

use Ytmusicapi\YTMusic;

test('get_podcast', function () {
    $yt = new YTMusic();
    $podcast_id = $this->podcast_id;
    $podcast = $yt->get_podcast($podcast_id);
    expect(count($podcast->episodes))->toBeLessThanOrEqual(100);
    expect($podcast->saved)->toBeFalse();
});

test('many_podcasts', function () {
    $yt = new YTMusic();
    $podcast = $yt->search("podcast", filter: "podcasts");
    expect(count($podcast))->toBeGreaterThan(0);
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
test("get_channel", function () {
    $channel_id = "UCGwuxdEeCf0TIA2RbPOj-8g"; // Stanford Graduate School of Business

    $yt = new YTMusic();
    $channel = $yt->get_channel($channel_id);
    expect(count($channel->episodes->results))->toBe(10);
    expect(count($channel->podcasts->results))->toBeGreaterThan(4);
});

test("get_channel_episodes", function () {
    $channel_id = "UCGwuxdEeCf0TIA2RbPOj-8g"; // Stanford Graduate School of Business

    $yt = new YTMusic("oauth.yaml");
    $channel = $yt->get_channel($channel_id);
    $channel_episodes = $yt->get_channel_episodes($channel_id, $channel->episodes->params);
    expect(count($channel_episodes))->toBeGreaterThan(150);
    expect($channel_episodes[0]->title)->not->toBeEmpty();
});

// Requires new episodes of subscribed podcasts.
test("get_episodes_playlist", function () {
    $yt = new YTMusic("oauth.json");
    $playlist = $yt->get_episodes_playlist();
    expect(count($playlist->episodes))->toBeGreaterThan(1);
})->skip("Not working - response format seems to have changed.");

test("get_episodes_playlist - unauthorized", function () {
    $yt = new YTMusic();
    $playlist = $yt->get_episodes_playlist();
    expect(count($playlist->episodes))->toBeGreaterThan(1);
})->throws(\Exception::class);
