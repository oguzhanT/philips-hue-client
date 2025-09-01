<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\ConnectionPool;

/**
 * Example Symfony Controller for Philips Hue Integration
 * 
 * This controller demonstrates how to integrate Hue controls
 * into your Symfony application with validation and error handling.
 */
#[Route('/api/hue', name: 'hue_')]
class HueController extends AbstractController
{
    public function __construct(
        private HueClient $hueClient,
        private ConnectionPool $connectionPool,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        try {
            return $this->json([
                'bridge' => [
                    'ip' => $this->hueClient->getBridgeIp(),
                    'connected' => $this->hueClient->isConnected(),
                    'info' => $this->hueClient->getConfig(),
                ],
                'resources' => $this->hueClient->getResourceCounts(),
                'capabilities' => $this->hueClient->getBridgeCapabilities(),
                'connection_info' => $this->hueClient->getConnectionInfo(),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/lights', name: 'lights', methods: ['GET'])]
    public function lights(): JsonResponse
    {
        try {
            $lights = $this->hueClient->lights()->getAll();
            
            return $this->json([
                'lights' => array_map(function($light) {
                    return [
                        'id' => $light->getId(),
                        'name' => $light->getName(),
                        'type' => $light->getType(),
                        'state' => [
                            'on' => $light->getState()->isOn(),
                            'brightness' => $light->getState()->getBrightness(),
                            'reachable' => $light->isReachable(),
                        ]
                    ];
                }, $lights)
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/lights/{lightId}/control', name: 'control_light', methods: ['POST'])]
    public function controlLight(Request $request, int $lightId): JsonResponse
    {
        // Validate input
        $data = json_decode($request->getContent(), true);
        $violations = $this->validator->validate($data, [
            'action' => new Assert\NotBlank(),
            'action' => new Assert\Choice(['on', 'off', 'toggle']),
            'brightness' => new Assert\Optional([
                new Assert\Type('integer'),
                new Assert\Range(['min' => 0, 'max' => 100])
            ]),
            'color' => new Assert\Optional([
                new Assert\Regex('/^#[0-9A-Fa-f]{6}$/')
            ]),
        ]);

        if (count($violations) > 0) {
            return $this->json([
                'error' => 'Validation failed',
                'violations' => array_map(fn($v) => $v->getMessage(), iterator_to_array($violations))
            ], 400);
        }

        try {
            $light = $this->hueClient->lights()->get($lightId);
            
            match($data['action']) {
                'on' => $light->on(),
                'off' => $light->off(),
                'toggle' => $light->toggle(),
            };

            if (isset($data['brightness'])) {
                $light->setBrightness($data['brightness']);
            }

            if (isset($data['color'])) {
                $light->setColor($data['color']);
            }

            return $this->json([
                'success' => true,
                'light' => $light->getName(),
                'action' => $data['action'],
                'state' => [
                    'on' => $light->getState()->isOn(),
                    'brightness' => $light->getState()->getBrightness(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to control light',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/rooms', name: 'rooms', methods: ['GET'])]
    public function rooms(): JsonResponse
    {
        try {
            $rooms = $this->hueClient->groups()->getRooms();
            
            return $this->json([
                'rooms' => array_map(function($room) {
                    return [
                        'id' => $room->getId(),
                        'name' => $room->getName(),
                        'class' => $room->getClass(),
                        'lights' => count($room->getLights()),
                        'any_on' => $room->getState()->anyOn(),
                        'all_on' => $room->getState()->allOn(),
                    ];
                }, $rooms)
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/rooms/{roomName}/control', name: 'control_room', methods: ['POST'])]
    public function controlRoom(Request $request, string $roomName): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        try {
            $room = $this->hueClient->groups()->getByName($roomName);
            
            if (!$room) {
                return $this->json(['error' => "Room '{$roomName}' not found"], 404);
            }

            if (isset($data['scene'])) {
                $this->hueClient->scenes()->activate($data['scene']);
                $action = "activated scene '{$data['scene']}'";
            } else {
                match($data['action'] ?? 'on') {
                    'on' => $room->on(),
                    'off' => $room->off(),
                    'toggle' => $room->toggle(),
                };
                $action = $data['action'] ?? 'on';

                if (isset($data['brightness'])) {
                    $room->setBrightness($data['brightness']);
                }

                if (isset($data['color'])) {
                    $room->setColor($data['color']);
                }
            }

            return $this->json([
                'success' => true,
                'room' => $roomName,
                'action' => $action
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to control room',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/scenes', name: 'scenes', methods: ['GET'])]
    public function scenes(): JsonResponse
    {
        try {
            $scenes = $this->hueClient->scenes()->getAll();
            
            return $this->json([
                'scenes' => array_map(function($scene) {
                    return [
                        'id' => $scene->getId(),
                        'name' => $scene->getName(),
                        'type' => $scene->getType(),
                        'group' => $scene->getGroup(),
                        'lights' => count($scene->getLights()),
                    ];
                }, $scenes)
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/scenes/{sceneId}/activate', name: 'activate_scene', methods: ['POST'])]
    public function activateScene(string $sceneId): JsonResponse
    {
        try {
            $this->hueClient->scenes()->activate($sceneId);
            
            return $this->json([
                'success' => true,
                'scene_id' => $sceneId,
                'message' => 'Scene activated successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to activate scene',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/effects/{effectType}', name: 'run_effect', methods: ['POST'])]
    public function runEffect(Request $request, string $effectType): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        
        try {
            $target = isset($data['room']) 
                ? $this->hueClient->groups()->getByName($data['room'])
                : $this->hueClient->groups()->all();

            $duration = $data['duration'] ?? 30;

            match($effectType) {
                'party' => $target->party($duration),
                'sunrise' => $target->sunrise($duration),
                'sunset' => $target->sunset($duration),
                'colorloop' => $target->colorloop($duration),
                default => throw new \InvalidArgumentException("Unknown effect: {$effectType}")
            };

            return $this->json([
                'success' => true,
                'effect' => $effectType,
                'duration' => $duration,
                'target' => $data['room'] ?? 'all lights'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to run effect',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/multi-bridge/{action}', name: 'multi_bridge', methods: ['POST'])]
    public function multiBridge(string $action): JsonResponse
    {
        try {
            $results = match($action) {
                'status' => $this->connectionPool->healthCheck(),
                'all_off' => $this->connectionPool->broadcastToAll(
                    fn($client) => $client->groups()->all()->off()
                ),
                'party' => $this->connectionPool->broadcastToAll(
                    fn($client) => $client->groups()->all()->party(60)
                ),
                default => throw new \InvalidArgumentException("Unknown action: {$action}")
            };

            return $this->json([
                'success' => true,
                'action' => $action,
                'bridges' => $this->connectionPool->getBridgeCount(),
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Multi-bridge operation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}