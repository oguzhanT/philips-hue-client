<?php

require_once __DIR__ . '/../vendor/autoload.php';

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Api\RestApi;
use OguzhanTogay\HueClient\Discovery\BridgeDiscovery;

// Front controller for the REST API. Configuration comes from the environment
// (getenv, so it works whatever php.ini's variables_order is):
//   HUE_BRIDGE_IP, HUE_USERNAME, HUE_AUTO_DISCOVER,
//   HUE_API_KEY, HUE_API_INSECURE, HUE_CORS_ORIGINS, HUE_DEBUG

function hue_fail(string $message, int $status = 503): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => $message]);
    exit;
}

$bridgeIp = getenv('HUE_BRIDGE_IP') ?: null;
$username = getenv('HUE_USERNAME') ?: null;
$autoDiscover = filter_var(getenv('HUE_AUTO_DISCOVER') ?: 'true', FILTER_VALIDATE_BOOLEAN);

if (!$bridgeIp && $autoDiscover) {
    $bridges = (new BridgeDiscovery())->discover();
    $bridgeIp = $bridges !== [] ? $bridges[0]->getIp() : null;
}

if (!$bridgeIp) {
    hue_fail('Bridge IP not configured. Set HUE_BRIDGE_IP.');
}

if (!$username) {
    hue_fail('Username not configured. Set HUE_USERNAME.');
}

(new RestApi(new HueClient($bridgeIp, $username), RestApi::optionsFromEnvironment()))->run();
