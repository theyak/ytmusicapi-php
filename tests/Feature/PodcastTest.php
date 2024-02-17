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
