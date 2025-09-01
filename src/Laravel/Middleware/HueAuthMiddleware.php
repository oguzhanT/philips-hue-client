<?php

namespace OguzhanTogay\HueClient\Laravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Exceptions\AuthenticationException;

class HueAuthMiddleware
{
    public function __construct(private HueClient $hueClient)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (!$this->hueClient->isConnected()) {
                return response()->json([
                    'error' => 'Hue Bridge not accessible',
                    'message' => 'Cannot connect to Philips Hue Bridge',
                    'bridge_ip' => $this->hueClient->getBridgeIp()
                ], 503);
            }

            // Add bridge info to request for debugging
            $request->attributes->set('hue_bridge_ip', $this->hueClient->getBridgeIp());
            $request->attributes->set('hue_connected', true);

            return $next($request);
        } catch (AuthenticationException $e) {
            return response()->json([
                'error' => 'Hue Bridge authentication failed',
                'message' => $e->getMessage(),
                'bridge_ip' => $this->hueClient->getBridgeIp()
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Hue Bridge error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}