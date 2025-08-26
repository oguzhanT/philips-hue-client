<?php

namespace OguzhanTogay\HueClient\Effects;

class Alert extends BaseEffect
{
    public function start($target, string $mode = 'select'): void
    {
        $this->setLightState($target, [
            'alert' => $mode
        ]);
    }

    public function flash($target, int $times = 1, int $interval = 1000): void
    {
        $this->running = true;

        for ($i = 0; $i < $times && $this->running; $i++) {
            $this->setLightState($target, [
                'alert' => 'select'
            ]);

            $this->sleep($interval);
        }

        $this->running = false;
    }

    public function longAlert($target): void
    {
        $this->setLightState($target, [
            'alert' => 'lselect'
        ]);
    }

    public function stop($target): void
    {
        parent::stop();
        
        $this->setLightState($target, [
            'alert' => 'none'
        ]);
    }
}