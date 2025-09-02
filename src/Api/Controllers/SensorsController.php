<?php

namespace OguzhanTogay\HueClient\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * @OA\Tag(
 *     name="Sensors",
 *     description="Sensor monitoring and management endpoints"
 * )
 */
class SensorsController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/api/sensors",
     *     summary="Get all sensors",
     *     tags={"Sensors"},
     *     security={{"HueAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of all sensors",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Motion Sensor"),
     *                 @OA\Property(property="type", type="string", example="ZLLPresence"),
     *                 @OA\Property(property="state", type="object"),
     *                 @OA\Property(property="config", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function getAll(Request $request, Response $response): Response
    {
        try {
            $sensors = $this->hueClient->sensors()->getAll();
            $sensorData = [];

            foreach ($sensors as $sensor) {
                $sensorData[] = [
                    'id' => $sensor->getId(),
                    'name' => $sensor->getName(),
                    'type' => $sensor->getType(),
                    'modelid' => $sensor->getModelId(),
                    'manufacturername' => $sensor->getManufacturer(),
                    'swversion' => $sensor->getSwVersion(),
                    'state' => $sensor->getState(),
                    'config' => $sensor->getConfig(),
                    'uniqueid' => $sensor->getUniqueId()
                ];
            }

            return $this->jsonResponse($response, $sensorData);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/sensors/{id}",
     *     summary="Get specific sensor",
     *     tags={"Sensors"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Sensor ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sensor details"
     *     )
     * )
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        try {
            $sensorId = (int) $args['id'];
            $sensor = $this->hueClient->sensors()->get($sensorId);

            return $this->jsonResponse($response, [
                'id' => $sensor->getId(),
                'name' => $sensor->getName(),
                'type' => $sensor->getType(),
                'modelid' => $sensor->getModelId(),
                'manufacturername' => $sensor->getManufacturer(),
                'swversion' => $sensor->getSwVersion(),
                'state' => $sensor->getState(),
                'config' => $sensor->getConfig(),
                'uniqueid' => $sensor->getUniqueId()
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/sensors/{id}/state",
     *     summary="Update sensor state",
     *     tags={"Sensors"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Sensor ID"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="state", type="object", description="New sensor state")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sensor state updated successfully"
     *     )
     * )
     */
    public function setState(Request $request, Response $response, array $args): Response
    {
        try {
            $sensorId = (int) $args['id'];
            $payload = json_decode($request->getBody()->getContents(), true);

            if (!$payload) {
                return $this->errorResponse($response, 'Invalid JSON payload');
            }

            $validation = $this->validateJsonPayload($payload, ['state']);
            if ($validation) {
                return $this->errorResponse($response, $validation);
            }

            $sensor = $this->hueClient->sensors()->get($sensorId);
            $sensor->updateState($payload['state']);

            return $this->successResponse($response, null, 'Sensor state updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }
}
