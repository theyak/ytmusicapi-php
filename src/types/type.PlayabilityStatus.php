<?php

namespace Ytmusicapi;

class PlayabilityStatus
{
    public string $status;
    public bool $playableInEmbed;
    public object $audioPlayability;
    public object $miniplayer;
    public object $contextParams;
}
