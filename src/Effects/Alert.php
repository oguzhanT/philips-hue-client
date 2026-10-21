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

    /**
     * Stops the effect and, when a target is given, cancels its running alert.
     */
    public function stop($target = null): void
    {
        parent::stop();

        if ($target !== null) {
            $this->setLightState($target, [
                'alert' => 'none'
            ]);
        }
    }
}
