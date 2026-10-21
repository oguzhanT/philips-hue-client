<?php

namespace OguzhanTogay\HueClient\V2\Resources;

/**
 * A physical product: a bulb, a switch, a sensor, the bridge itself.
 */
class Device extends Resource
{
    public function productName(): string
    {
        return (string) ($this->data['product_data']['product_name'] ?? '');
    }

    /** @return string[] ids of the light services this device provides */
    public function lightIds(): array
    {
        return $this->references('services', 'light');
    }
}
