<?php

namespace OguzhanTogay\HueClient\Retry;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RetryHandler
{
    private int $maxRetries;
    private array $retryDelays;
    private LoggerInterface $logger;
    private array $retryableExceptions;

    public function __construct(
        int $maxRetries = 3,
        array $retryDelays = [1, 2, 4], // Exponential backoff in seconds
        ?LoggerInterface $logger = null
    ) {
        $this->maxRetries = $maxRetries;
        $this->retryDelays = $retryDelays;
        $this->logger = $logger ?? new NullLogger();
        $this->retryableExceptions = [
            ConnectException::class,
            RequestException::class
        ];
    }

    public function execute(callable $operation, string $operationName = 'operation'): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                $result = $operation();

                if ($attempt > 0) {
                    $this->logger->info("Operation succeeded after retry", [
                        'operation' => $operationName,
                        'attempt' => $attempt + 1,
                        'total_attempts' => $attempt + 1
                    ]);
                }

                return $result;
            } catch (\Exception $exception) {
                $lastException = $exception;

                if ($attempt >= $this->maxRetries || !$this->shouldRetry($exception)) {
                    break;
                }

                $delay = $this->retryDelays[$attempt] ?? end($this->retryDelays);

                $this->logger->warning("Operation failed, retrying", [
                    'operation' => $operationName,
                    'attempt' => $attempt + 1,
                    'max_retries' => $this->maxRetries,
                    'delay' => $delay,
                    'error' => $exception->getMessage()
                ]);

                sleep($delay);
                $attempt++;
            }
        }

        $this->logger->error("Operation failed after all retries", [
            'operation' => $operationName,
            'total_attempts' => $attempt + 1,
            'final_error' => $lastException?->getMessage() ?? 'Unknown error'
        ]);

        throw $lastException ?? new \Exception('Operation failed');
    }

    public function executeAsync(callable $operation, string $operationName = 'async_operation'): \Generator
    {
        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $result = yield $operation();

                if ($attempt > 0) {
                    $this->logger->info("Async operation succeeded after retry", [
                        'operation' => $operationName,
                        'attempt' => $attempt + 1
                    ]);
                }

                return $result;
            } catch (\Exception $exception) {
                if ($attempt >= $this->maxRetries || !$this->shouldRetry($exception)) {
                    $this->logger->error("Async operation failed after all retries", [
                        'operation' => $operationName,
                        'total_attempts' => $attempt + 1,
                        'error' => $exception->getMessage()
                    ]);
                    throw $exception;
                }

                $delay = $this->retryDelays[$attempt] ?? end($this->retryDelays);

                $this->logger->warning("Async operation failed, retrying", [
                    'operation' => $operationName,
                    'attempt' => $attempt + 1,
                    'delay' => $delay,
                    'error' => $exception->getMessage()
                ]);

                yield new \React\Promise\Promise(function ($resolve) use ($delay) {
                    \React\EventLoop\Loop::get()->addTimer($delay, $resolve);
                });
            }
        }
    }

    private function shouldRetry(\Exception $exception): bool
    {
        foreach ($this->retryableExceptions as $retryableClass) {
            if ($exception instanceof $retryableClass) {
                return true;
            }
        }

        // Retry on HTTP 5xx errors and some 4xx errors
        if (method_exists($exception, 'getResponse') && $exception->getResponse()) {
            $statusCode = $exception->getResponse()->getStatusCode();
            return $statusCode >= 500 || in_array($statusCode, [408, 429]);
        }

        return false;
    }

    public function getRetryStatistics(): array
    {
        return [
            'max_retries' => $this->maxRetries,
            'retry_delays' => $this->retryDelays,
            'retryable_exceptions' => $this->retryableExceptions
        ];
    }
}
