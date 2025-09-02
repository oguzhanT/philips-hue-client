<?php

namespace OguzhanTogay\HueClient\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * @OA\Tag(
 *     name="Schedules",
 *     description="Schedule management endpoints"
 * )
 */
class SchedulesController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/api/schedules",
     *     summary="Get all schedules",
     *     tags={"Schedules"},
     *     security={{"HueAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of all schedules"
     *     )
     * )
     */
    public function getAll(Request $request, Response $response): Response
    {
        try {
            $schedules = $this->hueClient->schedules()->getAll();
            $scheduleData = [];

            foreach ($schedules as $schedule) {
                $scheduleData[] = [
                    'id' => $schedule->getId(),
                    'name' => $schedule->getName(),
                    'description' => $schedule->getDescription(),
                    'command' => $schedule->getCommand(),
                    'localtime' => $schedule->getLocalTime(),
                    'created' => $schedule->getCreated(),
                    'status' => $schedule->getStatus()
                ];
            }

            return $this->jsonResponse($response, $scheduleData);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/schedules/{id}",
     *     summary="Get specific schedule",
     *     tags={"Schedules"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Schedule ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Schedule details"
     *     )
     * )
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        try {
            $scheduleId = (int) $args['id'];
            $schedule = $this->hueClient->schedules()->get($scheduleId);

            return $this->jsonResponse($response, [
                'id' => $schedule->getId(),
                'name' => $schedule->getName(),
                'description' => $schedule->getDescription(),
                'command' => $schedule->getCommand(),
                'localtime' => $schedule->getLocalTime(),
                'created' => $schedule->getCreated(),
                'status' => $schedule->getStatus()
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/schedules",
     *     summary="Create new schedule",
     *     tags={"Schedules"},
     *     security={{"HueAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Morning Routine"),
     *             @OA\Property(property="description", type="string", example="Turn on bedroom lights"),
     *             @OA\Property(property="command", type="object"),
     *             @OA\Property(property="localtime", type="string", example="2024-01-01T07:00:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Schedule created successfully"
     *     )
     * )
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $payload = json_decode($request->getBody()->getContents(), true);

            $validation = $this->validateJsonPayload($payload, ['name', 'command', 'localtime']);
            if ($validation) {
                return $this->errorResponse($response, $validation);
            }

            $schedule = $this->hueClient->schedules()->create(
                $payload['name'],
                $payload['command'],
                $payload['localtime']
            );

            return $this->successResponse($response, [
                'id' => $schedule->getId(),
                'name' => $schedule->getName()
            ], 'Schedule created successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/schedules/{id}",
     *     summary="Update schedule",
     *     tags={"Schedules"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Schedule ID"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="command", type="object"),
     *             @OA\Property(property="localtime", type="string"),
     *             @OA\Property(property="status", type="string", enum={"enabled", "disabled"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Schedule updated successfully"
     *     )
     * )
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $scheduleId = (int) $args['id'];
            $payload = json_decode($request->getBody()->getContents(), true);

            if (!$payload) {
                return $this->errorResponse($response, 'Invalid JSON payload');
            }

            $schedule = $this->hueClient->schedules()->get($scheduleId);
            $schedule->modify($payload);

            return $this->successResponse($response, null, 'Schedule updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/schedules/{id}",
     *     summary="Delete schedule",
     *     tags={"Schedules"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Schedule ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Schedule deleted successfully"
     *     )
     * )
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $scheduleId = (int) $args['id'];
            $this->hueClient->schedules()->delete($scheduleId);

            return $this->successResponse($response, null, 'Schedule deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }
}
