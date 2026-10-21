<?php

namespace OguzhanTogay\HueClient\Api;

use Slim\Factory\AppFactory;
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Api\Middleware\RateLimitMiddleware;
use OguzhanTogay\HueClient\Api\Middleware\CacheMiddleware;
use OguzhanTogay\HueClient\Api\Middleware\ApiKeyMiddleware;
use OguzhanTogay\HueClient\Api\Middleware\CorsMiddleware;
use OguzhanTogay\HueClient\Api\Middleware\JsonBodyMiddleware;
use OguzhanTogay\HueClient\Api\Controllers\LightsController;
use OguzhanTogay\HueClient\Api\Controllers\GroupsController;
use OguzhanTogay\HueClient\Api\Controllers\ScenesController;
use OguzhanTogay\HueClient\Api\Controllers\SchedulesController;
use OguzhanTogay\HueClient\Api\Controllers\SensorsController;
use OguzhanTogay\HueClient\Api\Controllers\BridgeController;

/**
 * @OA\Info(
 *     title="Philips Hue REST API",
 *     version="1.0.0",
 *     description="REST API for controlling Philips Hue lights, rooms, scenes, and more",
 *     @OA\Contact(
 *         email="oguzhan.togay@rgbilisim.com",
 *         name="Oguzhan Togay"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * @OA\Server(
 *     url="http://localhost:8080",
 *     description="Local development server"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="HueAuth",
 *     type="http",
 *     scheme="bearer",
 *     description="The API key set in HUE_API_KEY; also accepted as an X-API-Key header"
 * )
 */
class RestApi
{
    private App $app;
    private HueClient $hueClient;
    private array $options;

    /**
     * @param array{api_key?: ?string, allow_insecure?: bool, cors_origins?: string[], debug?: bool} $options
     *   api_key         required on every request except /api/health and the docs
     *   allow_insecure  run without an API key (trusted networks only)
     *   cors_origins    browser origins allowed to call the API; none by default
     *   debug           show error details in responses
     */
    public function __construct(HueClient $hueClient, array $options = [])
    {
        $this->hueClient = $hueClient;
        $this->options = $options + [
            'api_key' => null,
            'allow_insecure' => false,
            'cors_origins' => [],
            'debug' => false,
        ];
        $this->app = AppFactory::create();
        $this->setupRoutes();
        $this->setupMiddleware();
    }

    /**
     * Options from HUE_API_KEY, HUE_API_INSECURE, HUE_CORS_ORIGINS (comma
     * separated) and HUE_DEBUG.
     */
    public static function optionsFromEnvironment(): array
    {
        $flag = static fn (string $name): bool =>
            filter_var(getenv($name) ?: 'false', FILTER_VALIDATE_BOOLEAN);
        $origins = array_values(array_filter(array_map('trim', explode(',', (string) getenv('HUE_CORS_ORIGINS')))));

        return [
            'api_key' => getenv('HUE_API_KEY') ?: null,
            'allow_insecure' => $flag('HUE_API_INSECURE'),
            'cors_origins' => $origins,
            'debug' => $flag('HUE_DEBUG'),
        ];
    }

    private function setupMiddleware(): void
    {
        // Slim runs middleware last-added first, so this list goes from the
        // route outwards: routing, cache, JSON check, auth, rate limit, CORS,
        // errors. Routing sits innermost so CORS can answer preflights for any
        // path; the rate limit sits outside auth so it also slows key guessing.
        $this->app->addRoutingMiddleware();

        if (class_exists('\Symfony\Component\Cache\Adapter\FilesystemAdapter')) {
            $this->app->add(new CacheMiddleware());
        }

        $this->app->add(new JsonBodyMiddleware());
        $this->app->add(new ApiKeyMiddleware($this->options['api_key'], (bool) $this->options['allow_insecure']));

        if (class_exists('\Symfony\Component\RateLimiter\RateLimiterFactory')) {
            $this->app->add(new RateLimitMiddleware());
        }

        $this->app->add(new CorsMiddleware($this->options['cors_origins']));
        $this->app->addErrorMiddleware((bool) $this->options['debug'], true, true);
    }

    private function setupRoutes(): void
    {
        $lightsController = new LightsController($this->hueClient);
        $groupsController = new GroupsController($this->hueClient);
        $scenesController = new ScenesController($this->hueClient);
        $schedulesController = new SchedulesController($this->hueClient);
        $sensorsController = new SensorsController($this->hueClient);
        $bridgeController = new BridgeController($this->hueClient);

        // Bridge health and info
        $this->app->get('/api/health', [$bridgeController, 'health']);
        $this->app->get('/api/bridge/info', [$bridgeController, 'info']);
        $this->app->get('/api/bridge/config', [$bridgeController, 'config']);

        // Lights endpoints
        $this->app->get('/api/lights', [$lightsController, 'getAll']);
        $this->app->get('/api/lights/{id}', [$lightsController, 'get']);
        $this->app->put('/api/lights/{id}/state', [$lightsController, 'setState']);
        $this->app->patch('/api/lights/{id}/state', [$lightsController, 'updateState']);
        $this->app->put('/api/lights/{id}/name', [$lightsController, 'setName']);

        // Groups/Rooms endpoints
        $this->app->get('/api/groups', [$groupsController, 'getAll']);
        $this->app->get('/api/rooms', [$groupsController, 'getRooms']);
        $this->app->get('/api/zones', [$groupsController, 'getZones']);
        $this->app->get('/api/groups/{id}', [$groupsController, 'get']);
        $this->app->post('/api/groups', [$groupsController, 'create']);
        $this->app->put('/api/groups/{id}/action', [$groupsController, 'setAction']);
        $this->app->delete('/api/groups/{id}', [$groupsController, 'delete']);

        // Scenes endpoints
        $this->app->get('/api/scenes', [$scenesController, 'getAll']);
        $this->app->get('/api/scenes/{id}', [$scenesController, 'get']);
        $this->app->post('/api/scenes', [$scenesController, 'create']);
        $this->app->put('/api/scenes/{id}/activate', [$scenesController, 'activate']);
        $this->app->delete('/api/scenes/{id}', [$scenesController, 'delete']);

        // Schedules endpoints
        $this->app->get('/api/schedules', [$schedulesController, 'getAll']);
        $this->app->get('/api/schedules/{id}', [$schedulesController, 'get']);
        $this->app->post('/api/schedules', [$schedulesController, 'create']);
        $this->app->put('/api/schedules/{id}', [$schedulesController, 'update']);
        $this->app->delete('/api/schedules/{id}', [$schedulesController, 'delete']);

        // Sensors endpoints
        $this->app->get('/api/sensors', [$sensorsController, 'getAll']);
        $this->app->get('/api/sensors/{id}', [$sensorsController, 'get']);
        $this->app->put('/api/sensors/{id}/state', [$sensorsController, 'setState']);

        // Swagger documentation, when zircote/swagger-php is installed
        if (!class_exists(\OpenApi\Generator::class)) {
            return;
        }

        $this->app->get('/api/docs', function (Request $request, Response $response) {
            $swaggerJson = $this->generateSwaggerJson();
            $response->getBody()->write($swaggerJson);
            return $response->withHeader('Content-Type', 'application/json');
        });

        $this->app->get('/docs', function (Request $request, Response $response) {
            $html = $this->generateSwaggerUI();
            $response->getBody()->write($html);
            return $response->withHeader('Content-Type', 'text/html');
        });
    }

    public function run(): void
    {
        $this->app->run();
    }

    public function getApp(): App
    {
        return $this->app;
    }

    private function generateSwaggerJson(): string
    {
        $swagger = \OpenApi\Generator::scan([__DIR__ . '/../']);
        return $swagger->toJson();
    }

    private function generateSwaggerUI(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <title>Philips Hue API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui.css" />
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin:0; background: #fafafa; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            SwaggerUIBundle({
                url: "/api/docs",
                dom_id: "#swagger-ui",
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout"
            });
        };
    </script>
</body>
</html>';
    }
}
