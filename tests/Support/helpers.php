<?php

function loadFixture(string $name)
{
    return file_get_contents(__DIR__ . "/../data/{$name}");
}

function loadJsonFixture(string $name)
{
    return json_decode(
        file_get_contents(__DIR__ . "/../data/{$name}.json"),
        false,
        512,
        JSON_THROW_ON_ERROR
    );
}