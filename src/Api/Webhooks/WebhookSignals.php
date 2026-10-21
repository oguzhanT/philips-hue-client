<?php

namespace OguzhanTogay\HueClient\Api\Webhooks;

use OguzhanTogay\HueClient\Signal\SignalType;

/**
 * Turns GitHub and Sentry webhook deliveries into signals, and checks their
 * signatures. Pure functions: no bridge, no HTTP.
 */
final class WebhookSignals
{
    /**
     * @param string   $event    the X-GitHub-Event header
     * @param string[] $branches only signal for these branches; empty means all
     * @return SignalType|null null when the delivery is not worth a light
     */
    public static function github(string $event, array $payload, array $branches = []): ?SignalType
    {
        $branch = $payload['workflow_run']['head_branch']
            ?? $payload['check_suite']['head_branch']
            ?? $payload['deployment']['ref']
            ?? null;

        if ($branches !== [] && $branch !== null && !in_array($branch, $branches, true)) {
            return null;
        }

        // A run triggered from a fork can carry a branch called main too; only
        // runs of this repository's own branches count.
        $headRepository = $payload['workflow_run']['head_repository']['full_name'] ?? null;
        $repository = $payload['repository']['full_name'] ?? null;
        if ($headRepository !== null && $repository !== null && $headRepository !== $repository) {
            return null;
        }

        $action = $payload['action'] ?? null;

        return match ($event) {
            'workflow_run' => match ($action) {
                'requested', 'in_progress' => SignalType::Working,
                'completed' => self::conclusion($payload['workflow_run']['conclusion'] ?? null),
                default => null,
            },
            'check_suite' => $action === 'completed'
                ? self::conclusion($payload['check_suite']['conclusion'] ?? null)
                : null,
            'deployment_status' => match ($payload['deployment_status']['state'] ?? null) {
                'success' => SignalType::Success,
                'failure', 'error' => SignalType::Error,
                'pending', 'queued', 'in_progress' => SignalType::Working,
                default => null,
            },
            default => null,
        };
    }

    /**
     * @param string $resource the Sentry-Hook-Resource header
     */
    public static function sentry(string $resource, array $payload): ?SignalType
    {
        $action = $payload['action'] ?? null;

        return match ($resource) {
            'issue' => match ($action) {
                'created', 'unresolved' => SignalType::Error,
                'resolved' => SignalType::Success,
                default => null,
            },
            'error' => $action === 'created' ? SignalType::Error : null,
            'event_alert' => $action === 'triggered' ? SignalType::Error : null,
            'metric_alert' => match ($action) {
                'critical' => SignalType::Error,
                'warning' => SignalType::Warning,
                'resolved' => SignalType::Success,
                default => null,
            },
            default => null,
        };
    }

    /**
     * X-Hub-Signature-256: "sha256=" + HMAC-SHA256 of the raw body.
     */
    public static function verifyGithub(string $body, string $header, string $secret): bool
    {
        return $secret !== ''
            && str_starts_with($header, 'sha256=')
            && hash_equals(hash_hmac('sha256', $body, $secret), substr($header, 7));
    }

    /**
     * Sentry-Hook-Signature: HMAC-SHA256 of the raw body with the integration's client secret.
     */
    public static function verifySentry(string $body, string $header, string $secret): bool
    {
        return $secret !== '' && $header !== '' && hash_equals(hash_hmac('sha256', $body, $secret), $header);
    }

    private static function conclusion(?string $conclusion): ?SignalType
    {
        return match ($conclusion) {
            'success' => SignalType::Success,
            'failure', 'timed_out', 'startup_failure' => SignalType::Error,
            'cancelled', 'action_required', 'stale' => SignalType::Warning,
            default => null, // skipped, neutral, still running
        };
    }
}
