<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AIService;

class PriceRecommendationAgent
{
    protected AIService $ai;

    public function __construct(AIService $ai)
    {
        $this->ai = $ai;
    }

    /**
     * Analyze price results and provide recommendations.
     */
    public function analyze(array $priceResults, array $context = []): PriceRecommendation
    {
        if (empty($priceResults)) {
            return new PriceRecommendation(
                bestValue: null,
                lowestPrice: null,
                recommendation: 'No price results available to analyze.',
                insights: [],
                confidence: 0,
                buyNow: false,
                waitReason: 'No pricing data available.',
            );
        }

        $priceDataJson = json_encode($priceResults, JSON_PRETTY_PRINT);
        $contextJson = json_encode($context, JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
Analyze these product prices and provide shopping recommendations.

Price Results:
{$priceDataJson}

Additional Context:
{$contextJson}

Provide your analysis in this JSON format:
{
    "best_value_index": 0,
    "lowest_price_index": 0,
    "recommendation": "A personalized recommendation explaining the best option and why",
    "insights": [
        "Observation 1 about pricing patterns",
        "Observation 2 about retailers",
        "Any other helpful insights"
    ],
    "confidence": 85,
    "buy_now": true,
    "wait_reason": null
}

Consider:
- Price vs value (shipping, retailer reliability)
- Historical pricing patterns if available
- Stock availability
- Best time to buy

If wait_reason is needed, explain why waiting might be beneficial.
Only return the JSON, no other text.
PROMPT;

        try {
            $response = $this->ai->complete($prompt);
            $data = $this->parseJsonResponse($response);

            // Find best value and lowest price from indices
            $bestValueIndex = $data['best_value_index'] ?? 0;
            $lowestPriceIndex = $data['lowest_price_index'] ?? 0;

            return new PriceRecommendation(
                bestValue: $priceResults[$bestValueIndex] ?? null,
                lowestPrice: $priceResults[$lowestPriceIndex] ?? null,
                recommendation: $data['recommendation'] ?? 'Unable to provide recommendation.',
                insights: $data['insights'] ?? [],
                confidence: $data['confidence'] ?? 50,
                buyNow: $data['buy_now'] ?? false,
                waitReason: $data['wait_reason'] ?? null,
            );
        } catch (\Exception $e) {
            // Fallback to simple analysis
            $lowestPrice = null;
            $lowestPriceResult = null;

            foreach ($priceResults as $result) {
                $price = $result['price'] ?? PHP_INT_MAX;
                if ($lowestPrice === null || $price < $lowestPrice) {
                    $lowestPrice = $price;
                    $lowestPriceResult = $result;
                }
            }

            return new PriceRecommendation(
                bestValue: $lowestPriceResult,
                lowestPrice: $lowestPriceResult,
                recommendation: 'Based on available data, the lowest price option is recommended.',
                insights: ['AI analysis unavailable - showing lowest price option.'],
                confidence: 30,
                buyNow: true,
                waitReason: null,
            );
        }
    }

    /**
     * Parse JSON from AI response.
     */
    protected function parseJsonResponse(string $response): array
    {
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        return [];
    }
}

class PriceRecommendation
{
    public function __construct(
        public ?array $bestValue,
        public ?array $lowestPrice,
        public string $recommendation,
        public array $insights,
        public int $confidence,
        public bool $buyNow,
        public ?string $waitReason,
    ) {}

    public function toArray(): array
    {
        return [
            'best_value' => $this->bestValue,
            'lowest_price' => $this->lowestPrice,
            'recommendation' => $this->recommendation,
            'insights' => $this->insights,
            'confidence' => $this->confidence,
            'buy_now' => $this->buyNow,
            'wait_reason' => $this->waitReason,
        ];
    }
}
