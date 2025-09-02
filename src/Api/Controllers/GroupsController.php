<?php

namespace OguzhanTogay\HueClient\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * @OA\Tag(
 *     name="Groups",
 *     description="Group, room, and zone management endpoints"
 * )
 */
class GroupsController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/api/groups",
     *     summary="Get all groups",
     *     tags={"Groups"},
     *     security={{"HueAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of all groups",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Living Room"),
     *                 @OA\Property(property="type", type="string", example="Room"),
     *                 @OA\Property(property="lights", type="array", @OA\Items(type="integer")),
     *                 @OA\Property(property="state", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function getAll(Request $request, Response $response): Response
    {
        try {
            $groups = $this->hueClient->groups()->getAll();
            $groupData = [];

            foreach ($groups as $group) {
                $groupData[] = [
                    'id' => $group->getId(),
                    'name' => $group->getName(),
                    'type' => $group->getType(),
                    'lights' => $group->getLights(),
                    'state' => $group->getState()->toArray(),
                    'class' => $group->getClass()
                ];
            }

            return $this->jsonResponse($response, $groupData);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/rooms",
     *     summary="Get all rooms",
     *     tags={"Groups"},
     *     security={{"HueAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of room groups",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="class", type="string"),
     *                 @OA\Property(property="lights", type="array", @OA\Items(type="integer"))
     *             )
     *         )
     *     )
     * )
     */
    public function getRooms(Request $request, Response $response): Response
    {
        try {
            $rooms = $this->hueClient->groups()->getRooms();
            $roomData = [];

            foreach ($rooms as $room) {
                $roomData[] = [
                    'id' => $room->getId(),
                    'name' => $room->getName(),
                    'type' => $room->getType(),
                    'class' => $room->getClass(),
                    'lights' => $room->getLights(),
                    'state' => $room->getState()->toArray()
                ];
            }

            return $this->jsonResponse($response, $roomData);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/zones",
     *     summary="Get all zones",
     *     tags={"Groups"},
     *     security={{"HueAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of zone groups"
     *     )
     * )
     */
    public function getZones(Request $request, Response $response): Response
    {
        try {
            $zones = $this->hueClient->groups()->getZones();
            $zoneData = [];

            foreach ($zones as $zone) {
                $zoneData[] = [
                    'id' => $zone->getId(),
                    'name' => $zone->getName(),
                    'type' => $zone->getType(),
                    'class' => $zone->getClass(),
                    'lights' => $zone->getLights(),
                    'state' => $zone->getState()->toArray()
                ];
            }

            return $this->jsonResponse($response, $zoneData);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/groups/{id}",
     *     summary="Get specific group",
     *     tags={"Groups"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Group ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Group details"
     *     )
     * )
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        try {
            $groupId = (int) $args['id'];
            $group = $this->hueClient->groups()->get($groupId);

            return $this->jsonResponse($response, [
                'id' => $group->getId(),
                'name' => $group->getName(),
                'type' => $group->getType(),
                'lights' => $group->getLights(),
                'state' => $group->getState()->toArray(),
                'class' => $group->getClass()
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/groups",
     *     summary="Create new group",
     *     tags={"Groups"},
     *     security={{"HueAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="My New Group"),
     *             @OA\Property(property="lights", type="array", @OA\Items(type="integer"), example={1,2,3}),
     *             @OA\Property(property="type", type="string", example="LightGroup")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Group created successfully"
     *     )
     * )
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $payload = json_decode($request->getBody()->getContents(), true);

            $validation = $this->validateJsonPayload($payload, ['name', 'lights']);
            if ($validation) {
                return $this->errorResponse($response, $validation);
            }

            $group = $this->hueClient->groups()->create(
                $payload['name'],
                $payload['lights'],
                $payload['type'] ?? 'LightGroup'
            );

            return $this->successResponse($response, [
                'id' => $group->getId(),
                'name' => $group->getName()
            ], 'Group created successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/groups/{id}/action",
     *     summary="Set group action",
     *     tags={"Groups"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Group ID"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="on", type="boolean", example=true),
     *             @OA\Property(property="brightness", type="integer", example=75),
     *             @OA\Property(property="color", type="string", example="#FF5733")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Group action applied successfully"
     *     )
     * )
     */
    public function setAction(Request $request, Response $response, array $args): Response
    {
        try {
            $groupId = (int) $args['id'];
            $payload = json_decode($request->getBody()->getContents(), true);

            if (!$payload) {
                return $this->errorResponse($response, 'Invalid JSON payload');
            }

            $group = $this->hueClient->groups()->get($groupId);

            if (isset($payload['on'])) {
                $payload['on'] ? $group->on() : $group->off();
            }

            if (isset($payload['brightness'])) {
                $group->setBrightness((int) $payload['brightness']);
            }

            if (isset($payload['color'])) {
                $group->setColor($payload['color']);
            }

            return $this->successResponse($response, null, 'Group action applied successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/groups/{id}",
     *     summary="Delete group",
     *     tags={"Groups"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Group ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Group deleted successfully"
     *     )
     * )
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $groupId = (int) $args['id'];
            $this->hueClient->groups()->delete($groupId);

            return $this->successResponse($response, null, 'Group deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }
}
