<?php

namespace App\Services\AI;

use App\Models\AIRequestLog;
use Illuminate\Support\Facades\Log;

/**
 * AILoggingService
 * 
 * Wraps AI service calls to automatically log all requests and responses.
 * Provides transparency into AI API usage and helps with debugging.
 */
class AILoggingService
{
    protected AIService $aiService;
    protected int $userId;
    protected ?int $aiJobId;

    /**
     * Create a new AILoggingService instance.
     *
     * @param AIService $aiService The underlying AI service
     * @param int $userId The user ID for logging
     * @param int|null $aiJobId Optional AI job ID to associate logs with
     */
    public function __construct(AIService $aiService, int $userId, ?int $aiJobId = null)
    {
        $this->aiService = $aiService;
        $this->userId = $userId;
        $this->aiJobId = $aiJobId;
    }

    /**
     * Create a logging service for a user.
     *
     * @param int $userId The user ID
     * @param int|null $aiJobId Optional AI job ID
     */
    public static function forUser(int $userId, ?int $aiJobId = null): ?self
    {
        $aiService = AIService::forUser($userId);
        
        if (!$aiService) {
            return null;
        }

        return new self($aiService, $userId, $aiJobId);
    }

    /**
     * Get the underlying AI service.
     */
    public function getAIService(): AIService
    {
        return $this->aiService;
    }

    /**
     * Check if the service is available.
     */
    public function isAvailable(): bool
    {
        return $this->aiService->isAvailable();
    }

    /**
     * Complete a prompt with logging.
     *
     * @param string $prompt The prompt to complete
     * @param array $options Optional parameters
     * @param array|null $serpData Optional SERP data for price aggregation requests
     * @return string The AI response
     * @throws \RuntimeException If the request fails
     */
    public function complete(string $prompt, array $options = [], ?array $serpData = null): string
    {
        $requestType = $serpData !== null
            ? AIRequestLog::TYPE_PRICE_AGGREGATION
            : AIRequestLog::TYPE_COMPLETION;

        $log = $this->createLogEntry($requestType, [
            'prompt' => $this->truncateForStorage($prompt),
            'options' => $options,
        ]);

        $startTime = microtime(true);

        try {
            $result = $this->aiService->completeWithMetadata($prompt, $options);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($result['error']) {
                $log->markAsFailed($result['error'], $durationMs);
                throw new \RuntimeException($result['error']);
            }

            $log->markAsSuccess(
                responseData: ['response' => $this->truncateForStorage($result['response'])],
                durationMs: $durationMs,
                tokensInput: $this->estimateTokens($prompt),
                tokensOutput: $this->estimateTokens($result['response']),
                serpData: $serpData
            );

            return $result['response'];
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            
            if ($log->status === AIRequestLog::STATUS_PENDING) {
                $log->markAsFailed($e->getMessage(), $durationMs);
            }

            throw $e;
        }
    }

    /**
     * Complete a prompt with metadata and logging.
     *
     * @param string $prompt The prompt to complete
     * @param array $options Optional parameters
     * @param array|null $serpData Optional SERP data for price aggregation requests
     * @return array Response with metadata
     */
    public function completeWithMetadata(string $prompt, array $options = [], ?array $serpData = null): array
    {
        $requestType = $serpData !== null
            ? AIRequestLog::TYPE_PRICE_AGGREGATION
            : AIRequestLog::TYPE_COMPLETION;

        $log = $this->createLogEntry($requestType, [
            'prompt' => $this->truncateForStorage($prompt),
            'options' => $options,
        ]);

        $startTime = microtime(true);

        try {
            $result = $this->aiService->completeWithMetadata($prompt, $options);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($result['error']) {
                $log->markAsFailed($result['error'], $durationMs);
            } else {
                $log->markAsSuccess(
                    responseData: ['response' => $this->truncateForStorage($result['response'] ?? '')],
                    durationMs: $durationMs,
                    tokensInput: $this->estimateTokens($prompt),
                    tokensOutput: $this->estimateTokens($result['response'] ?? ''),
                    serpData: $serpData
                );
            }

            return $result;
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $log->markAsFailed($e->getMessage(), $durationMs);
            throw $e;
        }
    }

    /**
     * Analyze an image with logging.
     *
     * @param string $base64Image Base64 encoded image data
     * @param string $mimeType The image MIME type
     * @param string $prompt The analysis prompt
     * @return string The AI analysis response
     * @throws \RuntimeException If the request fails
     */
    public function analyzeImage(string $base64Image, string $mimeType, string $prompt): string
    {
        $log = $this->createLogEntry(AIRequestLog::TYPE_IMAGE_ANALYSIS, [
            'prompt' => $this->truncateForStorage($prompt),
            'mime_type' => $mimeType,
            'image_size' => strlen($base64Image),
        ]);

        $startTime = microtime(true);

        try {
            $response = $this->aiService->analyzeImage($base64Image, $mimeType, $prompt);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $log->markAsSuccess(
                responseData: ['response' => $this->truncateForStorage($response)],
                durationMs: $durationMs,
                tokensInput: $this->estimateTokens($prompt) + 500, // Image tokens estimate
                tokensOutput: $this->estimateTokens($response)
            );

            return $response;
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $log->markAsFailed($e->getMessage(), $durationMs);
            throw $e;
        }
    }

    /**
     * Test the connection with logging.
     *
     * @return array Test result with success status and message
     */
    public function testConnection(): array
    {
        $log = $this->createLogEntry(AIRequestLog::TYPE_TEST_CONNECTION, [
            'action' => 'test_connection',
        ]);

        $startTime = microtime(true);

        try {
            $result = $this->aiService->testConnection();
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($result['success']) {
                $log->markAsSuccess(
                    responseData: $result,
                    durationMs: $durationMs
                );
            } else {
                $log->markAsFailed($result['message'] ?? 'Connection test failed', $durationMs);
            }

            return $result;
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $log->markAsFailed($e->getMessage(), $durationMs);
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'response' => null,
            ];
        }
    }

    /**
     * Create a log entry for an AI request.
     *
     * @param string $requestType The type of request
     * @param array $requestData The request data
     */
    protected function createLogEntry(string $requestType, array $requestData): AIRequestLog
    {
        return AIRequestLog::createLog(
            userId: $this->userId,
            provider: $this->aiService->getProviderType(),
            requestType: $requestType,
            requestData: $requestData,
            aiJobId: $this->aiJobId,
            model: $this->aiService->getModel()
        );
    }

    /**
     * Truncate content for storage (to avoid excessively large logs).
     *
     * @param string $content The content to truncate
     * @param int $maxLength Maximum length
     */
    protected function truncateForStorage(string $content, int $maxLength = 50000): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }

        return substr($content, 0, $maxLength) . "\n\n[TRUNCATED - " . strlen($content) . " total characters]";
    }

    /**
     * Estimate token count for a string.
     * This is a rough estimate - actual token count varies by model.
     *
     * @param string $text The text to estimate
     */
    protected function estimateTokens(string $text): int
    {
        // Rough estimate: ~4 characters per token for English text
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Get the provider type.
     */
    public function getProviderType(): string
    {
        return $this->aiService->getProviderType();
    }

    /**
     * Get the model being used.
     */
    public function getModel(): string
    {
        return $this->aiService->getModel();
    }

    /**
     * Set the AI job ID for associating logs.
     *
     * @param int|null $aiJobId The AI job ID
     */
    public function setAIJobId(?int $aiJobId): self
    {
        $this->aiJobId = $aiJobId;
        return $this;
    }
}
