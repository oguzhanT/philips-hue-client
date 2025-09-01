<?php

namespace OguzhanTogay\HueClient\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * @OA\Tag(
 *     name="Bridge",
 *     description="Bridge health and configuration endpoints"
 * )
 */
class BridgeController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/api/health",
     *     summary="Check bridge connectivity",
     *     tags={"Bridge"},
     *     @OA\Response(
     *         response=200,
     *         description="Bridge health status",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="healthy"),
     *             @OA\Property(property="bridge_ip", type="string", example="192.168.1.100"),
     *             @OA\Property(property="connected", type="boolean", example=true),
     *             @OA\Property(property="response_time", type="number", example=0.25)
     *         )
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Bridge not reachable",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="unhealthy"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function health(Request $request, Response $response): Response
    {
        $startTime = microtime(true);
        
        try {
            $connected = $this->hueClient->isConnected();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($connected) {
                return $this->jsonResponse($response, [
                    'status' => 'healthy',
                    'bridge_ip' => $this->hueClient->getBridgeIp(),
                    'connected' => true,
                    'response_time_ms' => $responseTime,
                    'timestamp' => date('c')
                ]);
            } else {
                return $this->jsonResponse($response, [
                    'status' => 'unhealthy',
                    'bridge_ip' => $this->hueClient->getBridgeIp(),
                    'connected' => false,
                    'error' => 'Bridge not reachable',
                    'timestamp' => date('c')
                ], 503);
            }
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'status' => 'unhealthy',
                'bridge_ip' => $this->hueClient->getBridgeIp(),
                'connected' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ], 503);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/bridge/info",
     *     summary="Get bridge information",
     *     tags={"Bridge"},
     *     security={{"HueAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Bridge information",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="modelid", type="string"),
     *             @OA\Property(property="swversion", type="string"),
     *             @OA\Property(property="apiversion", type="string"),
     *             @OA\Property(property="mac", type="string"),
     *             @OA\Property(property="bridgeid", type="string")
     *         )
     *     )
     * )
     */
    public function info(Request $request, Response $response): Response
    {
        try {
            $config = $this->hueClient->getConfig();
            return $this->jsonResponse($response, [
                'name' => $config['name'] ?? 'Unknown',
                'modelid' => $config['modelid'] ?? 'Unknown',
                'swversion' => $config['swversion'] ?? 'Unknown',
                'apiversion' => $config['apiversion'] ?? 'Unknown',
                'mac' => $config['mac'] ?? 'Unknown',
                'bridgeid' => $config['bridgeid'] ?? 'Unknown',
                'ip' => $this->hueClient->getBridgeIp()
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/bridge/config",
     *     summary="Get bridge configuration",
     *     tags={"Bridge"},
     *     security={{"HueAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Full bridge configuration",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function config(Request $request, Response $response): Response
    {
        try {
            $config = $this->hueClient->getConfig();
            return $this->jsonResponse($response, $config);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }
}