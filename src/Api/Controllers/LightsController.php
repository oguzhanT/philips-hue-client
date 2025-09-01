<?php

namespace OguzhanTogay\HueClient\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * @OA\Tag(
 *     name="Lights",
 *     description="Light control and management endpoints"
 * )
 */
class LightsController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/api/lights",
     *     summary="Get all lights",
     *     tags={"Lights"},
     *     security={{"HueAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of all lights",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Living Room Lamp"),
     *                 @OA\Property(property="type", type="string", example="Extended color light"),
     *                 @OA\Property(property="state", type="object",
     *                     @OA\Property(property="on", type="boolean", example=true),
     *                     @OA\Property(property="brightness", type="integer", example=254),
     *                     @OA\Property(property="hue", type="integer", example=8402),
     *                     @OA\Property(property="saturation", type="integer", example=140)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getAll(Request $request, Response $response): Response
    {
        try {
            $lights = $this->hueClient->lights()->getAll();
            $lightData = [];
            
            foreach ($lights as $light) {
                $lightData[] = [
                    'id' => $light->getId(),
                    'name' => $light->getName(),
                    'type' => $light->getType(),
                    'state' => $light->getState()->toArray(),
                    'manufacturername' => $light->getManufacturerName(),
                    'modelid' => $light->getModelId(),
                    'swversion' => $light->getSwVersion(),
                    'reachable' => $light->isReachable()
                ];
            }

            return $this->jsonResponse($response, $lightData);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/lights/{id}",
     *     summary="Get specific light",
     *     tags={"Lights"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Light ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Light details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="state", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Light not found"
     *     )
     * )
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        try {
            $lightId = (int) $args['id'];
            $light = $this->hueClient->lights()->get($lightId);
            
            return $this->jsonResponse($response, [
                'id' => $light->getId(),
                'name' => $light->getName(),
                'type' => $light->getType(),
                'state' => $light->getState()->toArray(),
                'manufacturername' => $light->getManufacturerName(),
                'modelid' => $light->getModelId(),
                'swversion' => $light->getSwVersion(),
                'reachable' => $light->isReachable()
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/lights/{id}/state",
     *     summary="Set light state",
     *     tags={"Lights"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Light ID"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="on", type="boolean", example=true),
     *             @OA\Property(property="brightness", type="integer", minimum=1, maximum=254, example=200),
     *             @OA\Property(property="hue", type="integer", minimum=0, maximum=65535, example=8402),
     *             @OA\Property(property="saturation", type="integer", minimum=0, maximum=254, example=140),
     *             @OA\Property(property="transitiontime", type="integer", example=4)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Light state updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function setState(Request $request, Response $response, array $args): Response
    {
        try {
            $lightId = (int) $args['id'];
            $payload = json_decode($request->getBody()->getContents(), true);
            
            if (!$payload) {
                return $this->errorResponse($response, 'Invalid JSON payload');
            }

            $light = $this->hueClient->lights()->get($lightId);
            
            if (isset($payload['on'])) {
                $payload['on'] ? $light->on() : $light->off();
            }
            
            if (isset($payload['brightness'])) {
                $light->setBrightness((int) ($payload['brightness'] / 254 * 100));
            }
            
            if (isset($payload['hue']) || isset($payload['saturation'])) {
                $hue = $payload['hue'] ?? $light->getState()->getHue();
                $sat = $payload['saturation'] ?? $light->getState()->getSaturation();
                $light->setHueSaturation($hue, $sat);
            }
            
            if (isset($payload['transitiontime'])) {
                $light->transition($payload['transitiontime'] * 100);
            }

            return $this->successResponse($response, null, 'Light state updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/lights/{id}/state",
     *     summary="Update light state partially",
     *     tags={"Lights"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Light ID"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="brightness", type="integer", minimum=1, maximum=100, example=75),
     *             @OA\Property(property="color", type="string", example="#FF5733"),
     *             @OA\Property(property="temperature", type="integer", minimum=2000, maximum=6500, example=2700)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Light state updated"
     *     )
     * )
     */
    public function updateState(Request $request, Response $response, array $args): Response
    {
        try {
            $lightId = (int) $args['id'];
            $payload = json_decode($request->getBody()->getContents(), true);
            
            if (!$payload) {
                return $this->errorResponse($response, 'Invalid JSON payload');
            }

            $light = $this->hueClient->lights()->get($lightId);
            
            if (isset($payload['brightness'])) {
                $light->setBrightness((int) $payload['brightness']);
            }
            
            if (isset($payload['color'])) {
                $light->setColor($payload['color']);
            }
            
            if (isset($payload['temperature'])) {
                $light->setColorTemperature((int) $payload['temperature']);
            }

            return $this->successResponse($response, null, 'Light state updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/lights/{id}/name",
     *     summary="Rename light",
     *     tags={"Lights"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Light ID"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="New Light Name")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Light renamed successfully"
     *     )
     * )
     */
    public function setName(Request $request, Response $response, array $args): Response
    {
        try {
            $lightId = (int) $args['id'];
            $payload = json_decode($request->getBody()->getContents(), true);
            
            $validation = $this->validateJsonPayload($payload, ['name']);
            if ($validation) {
                return $this->errorResponse($response, $validation);
            }

            $light = $this->hueClient->lights()->get($lightId);
            $light->setName($payload['name']);

            return $this->successResponse($response, null, 'Light renamed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }
}