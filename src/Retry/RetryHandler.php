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
    /** @var \Closure(string): string */
    private \Closure $sanitize;

    /**
     * @param (callable(string): string)|null $sanitize Cleans exception messages before they are logged
     */
    public function __construct(
        int $maxRetries = 3,
        array $retryDelays = [1, 2, 4], // Exponential backoff in seconds
        ?LoggerInterface $logger = null,
        ?callable $sanitize = null
    ) {
        $this->maxRetries = $maxRetries;
        $this->retryDelays = $retryDelays;
        $this->logger = $logger ?? new NullLogger();
        $this->sanitize = $sanitize !== null
            ? \Closure::fromCallable($sanitize)
            : static fn (string $message): string => $message;
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
                    'error' => ($this->sanitize)($exception->getMessage())
                ]);

                sleep($delay);
                $attempt++;
            }
        }

        $this->logger->error("Operation failed after all retries", [
            'operation' => $operationName,
            'total_attempts' => $attempt + 1,
            'final_error' => $lastException ? ($this->sanitize)($lastException->getMessage()) : 'Unknown error'
        ]);

        throw $lastException ?? new \Exception('Operation failed');
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
