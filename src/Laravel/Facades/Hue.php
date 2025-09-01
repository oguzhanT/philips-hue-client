<?php

namespace OguzhanTogay\HueClient\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use OguzhanTogay\HueClient\HueClient;

class Hue extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HueClient::class;
    }
}