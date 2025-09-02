<?php

namespace OguzhanTogay\HueClient\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * @OA\Tag(
 *     name="Scenes",
 *     description="Scene management and activation endpoints"
 * )
 */
class ScenesController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/api/scenes",
     *     summary="Get all scenes",
     *     tags={"Scenes"},
     *     security={{"HueAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of all scenes",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="string", example="abc123"),
     *                 @OA\Property(property="name", type="string", example="Sunset"),
     *                 @OA\Property(property="type", type="string", example="LightScene"),
     *                 @OA\Property(property="group", type="string", example="1"),
     *                 @OA\Property(property="lights", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function getAll(Request $request, Response $response): Response
    {
        try {
            $scenes = $this->hueClient->scenes()->getAll();
            $sceneData = [];

            foreach ($scenes as $scene) {
                $sceneData[] = [
                    'id' => $scene->getId(),
                    'name' => $scene->getName(),
                    'type' => $scene->getType(),
                    'group' => $scene->getGroup(),
                    'lights' => $scene->getLights(),
                    'owner' => $scene->getOwner(),
                    'recycle' => $scene->isRecycle(),
                    'locked' => $scene->isLocked(),
                    'picture' => $scene->getPicture(),
                    'lastupdated' => $scene->getLastUpdated()
                ];
            }

            return $this->jsonResponse($response, $sceneData);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/scenes/{id}",
     *     summary="Get specific scene",
     *     tags={"Scenes"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Scene ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Scene details"
     *     )
     * )
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        try {
            $sceneId = $args['id'];
            $scene = $this->hueClient->scenes()->get($sceneId);

            return $this->jsonResponse($response, [
                'id' => $scene->getId(),
                'name' => $scene->getName(),
                'type' => $scene->getType(),
                'group' => $scene->getGroup(),
                'lights' => $scene->getLights(),
                'owner' => $scene->getOwner(),
                'recycle' => $scene->isRecycle(),
                'locked' => $scene->isLocked(),
                'picture' => $scene->getPicture(),
                'lastupdated' => $scene->getLastUpdated(),
                'lightstates' => $scene->getLightStates()
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/scenes",
     *     summary="Create new scene",
     *     tags={"Scenes"},
     *     security={{"HueAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="My Scene"),
     *             @OA\Property(property="lights", type="array", @OA\Items(type="integer"), example={1,2,3}),
     *             @OA\Property(property="group", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Scene created successfully"
     *     )
     * )
     */
    public function create(Request $request, Response $response): Response
    {
        try {
            $payload = json_decode($request->getBody()->getContents(), true);

            $validation = $this->validateJsonPayload($payload, ['name']);
            if ($validation) {
                return $this->errorResponse($response, $validation);
            }

            $scene = $this->hueClient->scenes()->create(
                $payload['name'],
                $payload['lights'] ?? [],
                $payload['group'] ?? null
            );

            return $this->successResponse($response, [
                'id' => $scene->getId(),
                'name' => $scene->getName()
            ], 'Scene created successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/scenes/{id}/activate",
     *     summary="Activate scene",
     *     tags={"Scenes"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Scene ID"
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="transitiontime",
     *                 type="integer",
     *                 example=4,
     *                 description="Transition time in deciseconds"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Scene activated successfully"
     *     )
     * )
     */
    public function activate(Request $request, Response $response, array $args): Response
    {
        try {
            $sceneId = $args['id'];
            $payload = json_decode($request->getBody()->getContents(), true) ?? [];

            $this->hueClient->scenes()->activate($sceneId);

            return $this->successResponse($response, null, 'Scene activated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/scenes/{id}",
     *     summary="Delete scene",
     *     tags={"Scenes"},
     *     security={{"HueAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         description="Scene ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Scene deleted successfully"
     *     )
     * )
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $sceneId = $args['id'];
            $this->hueClient->scenes()->delete($sceneId);

            return $this->successResponse($response, null, 'Scene deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }
}
