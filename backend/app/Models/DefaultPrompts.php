<?php

namespace App\Models;

class DefaultPrompts
{
    /**
     * Default prompt for product identification from images.
     */
    public const PRODUCT_IDENTIFICATION = <<<'PROMPT'
Analyze this image and identify the product shown. Please provide:

1. **Product Name**: The specific product name and model if visible
2. **Brand**: The manufacturer or brand name
3. **Category**: The product category (e.g., electronics, kitchen appliance, clothing)
4. **Key Features**: Notable features visible in the image
5. **Condition**: New, used, or unable to determine

Format your response as a structured JSON object with these fields:
{
  "product_name": "string",
  "brand": "string",
  "category": "string",
  "features": ["string"],
  "condition": "new|used|unknown",
  "confidence": 0.0-1.0,
  "search_query": "suggested search query for price comparison"
}

If you cannot identify the product, explain what you can see and provide your best guess.
PROMPT;

    /**
     * Default prompt for price recommendations.
     */
    public const PRICE_RECOMMENDATION = <<<'PROMPT'
Based on the following product information and current market data, provide a price recommendation:

Product: {product_name}
Current Price: ${current_price}
Historical Low: ${lowest_price}
Historical High: ${highest_price}
Retailer: {retailer}

Please analyze and provide:

1. **Fair Market Value**: What this product typically sells for
2. **Target Price**: A reasonable price to wait for (based on historical data)
3. **Buy Now Threshold**: Price at which this is a great deal
4. **Price Assessment**: Is the current price good, fair, or overpriced?
5. **Recommendation**: Should the user buy now or wait?

Consider seasonal trends, typical sale patterns, and product lifecycle.

Format your response as JSON:
{
  "fair_market_value": number,
  "target_price": number,
  "buy_now_threshold": number,
  "assessment": "great_deal|good_price|fair|overpriced",
  "recommendation": "buy_now|wait|avoid",
  "reasoning": "string explaining your analysis"
}
PROMPT;

    /**
     * Default prompt for aggregating responses from multiple AI providers.
     */
    public const AGGREGATION = <<<'PROMPT'
You are an intelligent AI aggregator. Multiple AI models have been asked the same question and provided different responses. Your task is to synthesize these responses into a single, comprehensive, and accurate answer.

Guidelines:
1. **Find Consensus**: Identify points where multiple AIs agree
2. **Resolve Conflicts**: When AIs disagree, use reasoning to determine the most likely correct answer
3. **Highlight Uncertainty**: If there's significant disagreement, note the uncertainty
4. **Preserve Unique Insights**: Include valuable unique observations from any model
5. **Maintain Structure**: If the original question asked for structured data, preserve that format

The individual AI responses are provided below. Synthesize them into a single, authoritative response:

{responses}

Provide your synthesized response, maintaining any requested format (JSON, etc.) from the original question. If the responses are in JSON format, output valid JSON.
PROMPT;

    /**
     * Get a default prompt by type.
     */
    public static function get(string $type): string
    {
        return match ($type) {
            AIPrompt::TYPE_PRODUCT_IDENTIFICATION => self::PRODUCT_IDENTIFICATION,
            AIPrompt::TYPE_PRICE_RECOMMENDATION => self::PRICE_RECOMMENDATION,
            AIPrompt::TYPE_AGGREGATION => self::AGGREGATION,
            default => throw new \InvalidArgumentException("Unknown prompt type: {$type}"),
        };
    }

    /**
     * Get human-readable name for a prompt type.
     */
    public static function getName(string $type): string
    {
        return match ($type) {
            AIPrompt::TYPE_PRODUCT_IDENTIFICATION => 'Product Identification',
            AIPrompt::TYPE_PRICE_RECOMMENDATION => 'Price Recommendation',
            AIPrompt::TYPE_AGGREGATION => 'Response Aggregation',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Get description for a prompt type.
     */
    public static function getDescription(string $type): string
    {
        return match ($type) {
            AIPrompt::TYPE_PRODUCT_IDENTIFICATION => 'Used when analyzing images to identify products for price tracking',
            AIPrompt::TYPE_PRICE_RECOMMENDATION => 'Used to analyze pricing data and provide buying recommendations',
            AIPrompt::TYPE_AGGREGATION => 'Used to synthesize responses from multiple AI providers into one answer',
            default => 'Custom AI prompt',
        };
    }
}
