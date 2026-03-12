<?php

namespace App\Services\LLM\Providers;

use App\Models\AIProvider;
use App\Services\LLM\LLMProviderInterface;
use App\Services\UrlValidationService;
use Illuminate\Support\Facades\Http;

class BedrockProvider implements LLMProviderInterface
{
    private string $accessKeyId;
    private string $secretAccessKey;
    private string $region;
    private string $model;
    private int $maxTokens;

    public function __construct(AIProvider $config)
    {
        $this->accessKeyId = $config->settings['access_key_id'] ?? env('AWS_ACCESS_KEY_ID');
        $this->secretAccessKey = $config->settings['secret_access_key'] ?? env('AWS_SECRET_ACCESS_KEY');
        $this->region = $config->settings['region'] ?? config('llm.providers.bedrock.region', 'us-east-1');
        $this->model = $config->model ?? config('llm.providers.bedrock.model', 'anthropic.claude-3-sonnet-20240229-v1:0');
        $this->maxTokens = $config->settings['max_tokens'] ?? config('llm.providers.bedrock.max_tokens', 4096);
    }

    /**
     * Build the Bedrock API URL for the specified model.
     */
    private function buildUrl(): string
    {
        return "https://bedrock-runtime.{$this->region}.amazonaws.com/model/{$this->model}/invoke";
    }

    /**
     * Generate AWS Signature Version 4 headers for authentication.
     */
    private function signRequest(string $method, string $url, string $payload): array
    {
        $timestamp = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');
        
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'];
        $path = $parsedUrl['path'] ?? '/';
        
        $service = 'bedrock';
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$datestamp}/{$this->region}/{$service}/aws4_request";
        
        // Create canonical request
        $payloadHash = hash('sha256', $payload);
        $canonicalHeaders = "content-type:application/json\nhost:{$host}\nx-amz-date:{$timestamp}\n";
        $signedHeaders = 'content-type;host;x-amz-date';
        
        $canonicalRequest = "{$method}\n{$path}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        
        // Create string to sign
        $stringToSign = "{$algorithm}\n{$timestamp}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        
        // Calculate signature
        $kDate = hash_hmac('sha256', $datestamp, "AWS4{$this->secretAccessKey}", true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        // Build authorization header
        $authorization = "{$algorithm} Credential={$this->accessKeyId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
        
        return [
            'Content-Type' => 'application/json',
            'Host' => $host,
            'X-Amz-Date' => $timestamp,
            'Authorization' => $authorization,
        ];
    }

    public function query(string $prompt, ?string $systemPrompt = null): array
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        // Bedrock uses the Anthropic format for Claude models
        $payload = [
            'anthropic_version' => 'bedrock-2023-05-31',
            'max_tokens' => $this->maxTokens,
            'messages' => $messages,
        ];

        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        $url = $this->buildUrl();
        $jsonPayload = json_encode($payload);
        $headers = $this->signRequest('POST', $url, $jsonPayload);

        $response = Http::timeout(config('llm.timeout', 120))
            ->withHeaders($headers)
            ->withBody($jsonPayload, 'application/json')
            ->post($url);

        if (!$response->successful()) {
            throw new \RuntimeException('AWS Bedrock API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'tokens' => [
                'input' => $data['usage']['input_tokens'] ?? 0,
                'output' => $data['usage']['output_tokens'] ?? 0,
                'total' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
        ];
    }

    public function visionQuery(string $prompt, string $imageData, string $mimeType = 'image/jpeg', ?string $systemPrompt = null): array
    {
        // Bedrock Claude supports vision with base64 images
        $base64Data = $imageData;
        if (str_starts_with($imageData, 'http')) {
            // Fetch with SSRF protection
            $urlValidator = app(UrlValidationService::class);
            $fetchedContent = $urlValidator->fetchContent($imageData);
            if ($fetchedContent === null) {
                throw new \RuntimeException('Failed to fetch image: URL validation failed or request error');
            }
            $base64Data = base64_encode($fetchedContent);
        }

        $imageContent = [
            'type' => 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => $mimeType,
                'data' => $base64Data,
            ],
        ];

        $messages = [
            [
                'role' => 'user',
                'content' => [
                    $imageContent,
                    ['type' => 'text', 'text' => $prompt],
                ],
            ],
        ];

        $payload = [
            'anthropic_version' => 'bedrock-2023-05-31',
            'max_tokens' => $this->maxTokens,
            'messages' => $messages,
        ];

        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        $url = $this->buildUrl();
        $jsonPayload = json_encode($payload);
        $headers = $this->signRequest('POST', $url, $jsonPayload);

        $response = Http::timeout(config('llm.timeout', 120))
            ->withHeaders($headers)
            ->withBody($jsonPayload, 'application/json')
            ->post($url);

        if (!$response->successful()) {
            throw new \RuntimeException('AWS Bedrock API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'tokens' => [
                'input' => $data['usage']['input_tokens'] ?? 0,
                'output' => $data['usage']['output_tokens'] ?? 0,
                'total' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
        ];
    }

    public function supportsVision(): bool
    {
        // Claude 3 models on Bedrock support vision
        return str_contains($this->model, 'claude-3');
    }

    public function getName(): string
    {
        return 'AWS Bedrock (Claude)';
    }
}
