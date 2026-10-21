<?php

namespace OguzhanTogay\HueClient\V2\Resources;

class Scene extends Resource
{
    /** The room or zone the scene belongs to. */
    public function groupId(): string
    {
        return (string) ($this->data['group']['rid'] ?? '');
    }

    /**
     * @param bool $dynamic play the scene's palette slowly, like the Hue app's dynamic scenes
     */
    public function recall(bool $dynamic = false, ?int $transitionMs = null): static
    {
        $recall = ['action' => $dynamic ? 'dynamic_palette' : 'active'];
        if ($transitionMs !== null) {
            $recall['duration'] = max(0, $transitionMs);
        }

        $this->client->request('PUT', "resource/scene/{$this->id()}", ['recall' => $recall]);

        return $this;
    }
}
