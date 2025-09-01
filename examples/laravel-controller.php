<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OguzhanTogay\HueClient\Laravel\Facades\Hue;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\ConnectionPool;

/**
 * Example Laravel Controller for Philips Hue Integration
 * 
 * This controller demonstrates how to integrate Hue controls
 * into your Laravel application with proper error handling
 * and response formatting.
 */
class HueController extends Controller
{
    public function __construct(private HueClient $hueClient)
    {
        // Apply Hue authentication middleware
        $this->middleware('hue.auth');
    }

    /**
     * Get dashboard data with all Hue resources
     */
    public function dashboard(): JsonResponse
    {
        try {
            return response()->json([
                'bridge' => [
                    'ip' => $this->hueClient->getBridgeIp(),
                    'connected' => $this->hueClient->isConnected(),
                    'info' => $this->hueClient->getConfig(),
                ],
                'resources' => $this->hueClient->getResourceCounts(),
                'capabilities' => $this->hueClient->getBridgeCapabilities(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all lights with their current state
     */
    public function lights(): JsonResponse
    {
        $lights = Hue::lights()->getAll();
        
        return response()->json([
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
    }

    /**
     * Control a specific light
     */
    public function controlLight(Request $request, int $lightId): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:on,off,toggle',
            'brightness' => 'sometimes|integer|min:0|max:100',
            'color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'transition' => 'sometimes|integer|min:0|max:10000'
        ]);

        try {
            $light = Hue::lights()->get($lightId);
            
            match($request->action) {
                'on' => $light->on(),
                'off' => $light->off(),
                'toggle' => $light->toggle(),
            };

            if ($request->has('brightness')) {
                $light->setBrightness($request->brightness);
            }

            if ($request->has('color')) {
                $light->setColor($request->color);
            }

            if ($request->has('transition')) {
                $light->transition($request->transition);
            }

            return response()->json([
                'success' => true,
                'light' => $light->getName(),
                'action' => $request->action,
                'state' => [
                    'on' => $light->getState()->isOn(),
                    'brightness' => $light->getState()->getBrightness(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to control light',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Control room lighting
     */
    public function controlRoom(Request $request, string $roomName): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:on,off,toggle',
            'brightness' => 'sometimes|integer|min:0|max:100',
            'color' => 'sometimes|string',
            'scene' => 'sometimes|string'
        ]);

        try {
            $room = Hue::groups()->getByName($roomName);
            
            if (!$room) {
                return response()->json(['error' => "Room '{$roomName}' not found"], 404);
            }

            if ($request->has('scene')) {
                Hue::scenes()->activate($request->scene);
            } else {
                match($request->action) {
                    'on' => $room->on(),
                    'off' => $room->off(),
                    'toggle' => $room->toggle(),
                };

                if ($request->has('brightness')) {
                    $room->setBrightness($request->brightness);
                }

                if ($request->has('color')) {
                    $room->setColor($request->color);
                }
            }

            return response()->json([
                'success' => true,
                'room' => $roomName,
                'action' => $request->scene ? "activated scene '{$request->scene}'" : $request->action
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to control room',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Run lighting effects
     */
    public function effect(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:party,sunrise,sunset,breathing,colorloop',
            'room' => 'sometimes|string',
            'duration' => 'sometimes|integer|min:1|max:3600'
        ]);

        try {
            $target = $request->has('room') 
                ? Hue::groups()->getByName($request->room)
                : Hue::groups()->all();

            $duration = $request->get('duration', 30);

            match($request->type) {
                'party' => $target->party($duration),
                'sunrise' => $target->sunrise($duration),
                'sunset' => $target->sunset($duration),
                'breathing' => $target->breathing('#FFFFFF', 'medium', $duration),
                'colorloop' => $target->colorloop($duration),
            };

            return response()->json([
                'success' => true,
                'effect' => $request->type,
                'duration' => $duration,
                'target' => $request->get('room', 'all lights')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to run effect',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Schedule management
     */
    public function schedules(): JsonResponse
    {
        $schedules = Hue::schedules()->getAll();
        
        return response()->json([
            'schedules' => array_map(function($schedule) {
                return [
                    'id' => $schedule->getId(),
                    'name' => $schedule->getName(),
                    'time' => $schedule->getLocalTime(),
                    'status' => $schedule->getStatus(),
                    'command' => $schedule->getCommand(),
                ];
            }, $schedules)
        ]);
    }

    /**
     * Create a new schedule
     */
    public function createSchedule(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'time' => 'required|string',
            'command' => 'required|array',
            'description' => 'sometimes|string'
        ]);

        try {
            $schedule = Hue::schedules()->create(
                $request->name,
                $request->command,
                $request->time,
                $request->get('description', '')
            );

            return response()->json([
                'success' => true,
                'schedule_id' => $schedule->getId(),
                'name' => $schedule->getName()
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create schedule',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Multi-bridge operations using Connection Pool
     */
    public function multiBridge(Request $request, ConnectionPool $pool): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:status,lights_off,party_mode'
        ]);

        try {
            $results = match($request->action) {
                'status' => $pool->healthCheck(),
                'lights_off' => $pool->broadcastToAll(fn($client) => $client->groups()->all()->off()),
                'party_mode' => $pool->broadcastToAll(fn($client) => $client->groups()->all()->party(60)),
            };

            return response()->json([
                'success' => true,
                'action' => $request->action,
                'bridges' => count($results),
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Multi-bridge operation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}