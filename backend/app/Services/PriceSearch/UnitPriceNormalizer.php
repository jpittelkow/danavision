<?php

namespace App\Services\PriceSearch;

use App\Models\User;
use App\Services\LLM\LLMOrchestrator;
use Illuminate\Support\Facades\Log;

class UnitPriceNormalizer
{
    /**
     * Standard unit conversions to a common base unit.
     * Format: [from_unit => [base_unit, divisor]]
     */
    private const UNIT_CONVERSIONS = [
        // Weight → lb
        'oz' => ['lb', 16],
        'ounce' => ['lb', 16],
        'ounces' => ['lb', 16],
        'g' => ['lb', 453.592],
        'gram' => ['lb', 453.592],
        'grams' => ['lb', 453.592],
        'kg' => ['lb', 0.453592],
        'kilogram' => ['lb', 0.453592],
        'kilograms' => ['lb', 0.453592],
        'lb' => ['lb', 1],
        'lbs' => ['lb', 1],
        'pound' => ['lb', 1],
        'pounds' => ['lb', 1],

        // Volume → gallon
        'fl oz' => ['gal', 128],
        'fl_oz' => ['gal', 128],
        'fluid ounce' => ['gal', 128],
        'fluid ounces' => ['gal', 128],
        'cup' => ['gal', 16],
        'cups' => ['gal', 16],
        'pint' => ['gal', 8],
        'pints' => ['gal', 8],
        'pt' => ['gal', 8],
        'quart' => ['gal', 4],
        'quarts' => ['gal', 4],
        'qt' => ['gal', 4],
        'gallon' => ['gal', 1],
        'gallons' => ['gal', 1],
        'gal' => ['gal', 1],
        'ml' => ['gal', 3785.41],
        'milliliter' => ['gal', 3785.41],
        'milliliters' => ['gal', 3785.41],
        'liter' => ['gal', 3.78541],
        'liters' => ['gal', 3.78541],
        'l' => ['gal', 3.78541],

        // Count (stays as count)
        'ct' => ['ct', 1],
        'count' => ['ct', 1],
        'pack' => ['ct', 1],
        'pk' => ['ct', 1],
        'ea' => ['ct', 1],
        'each' => ['ct', 1],
        'piece' => ['ct', 1],
        'pieces' => ['ct', 1],
    ];

    /**
     * Common product size patterns.
     * Each pattern returns [quantity, unit] from matched groups.
     */
    private const SIZE_PATTERNS = [
        // "2 lb", "2lb", "2.5 lbs", "2-lb"
        '/(\d+(?:\.\d+)?)\s*[-]?\s*(lbs?|pounds?|oz|ounces?|kg|kilograms?|g|grams?)\b/i',
        // "64 fl oz", "64fl oz"
        '/(\d+(?:\.\d+)?)\s*[-]?\s*(fl\.?\s*oz|fluid\s*ounces?)\b/i',
        // "1 gallon", "0.5 gal"
        '/(\d+(?:\.\d+)?)\s*[-]?\s*(gal(?:lons?)?|quarts?|qt|pints?|pt|cups?|liters?|l|ml|milliliters?)\b/i',
        // "12 pack", "24 ct", "6pk"
        '/(\d+)\s*[-]?\s*(pack|pk|ct|count|ea|each|pieces?)\b/i',
    ];

    public function __construct(
        private readonly LLMOrchestrator $llmOrchestrator,
    ) {}

    /**
     * Normalize a product's price to a standard unit price.
     *
     * @return array{unit_price: float|null, unit_quantity: float|null, unit_type: string|null, package_size: string|null}
     */
    public function normalize(string $productName, ?float $price, ?string $packageSize = null): array
    {
        $empty = [
            'unit_price' => null,
            'unit_quantity' => null,
            'unit_type' => null,
            'package_size' => $packageSize,
        ];

        if ($price === null || $price <= 0) {
            return $empty;
        }

        // Try regex extraction first (fast, no API cost)
        $extracted = $this->extractWithRegex($productName, $packageSize);

        if ($extracted) {
            return $this->computeUnitPrice($price, $extracted['quantity'], $extracted['unit'], $packageSize ?? $extracted['raw']);
        }

        return $empty;
    }

    /**
     * Normalize with LLM fallback for complex cases.
     * Only call this when regex fails and accurate unit pricing is critical.
     *
     * @return array{unit_price: float|null, unit_quantity: float|null, unit_type: string|null, package_size: string|null}
     */
    public function normalizeWithLlm(User $user, string $productName, ?float $price, ?string $packageSize = null): array
    {
        // Try regex first
        $result = $this->normalize($productName, $price, $packageSize);
        if ($result['unit_price'] !== null) {
            return $result;
        }

        if ($price === null || $price <= 0) {
            return $result;
        }

        // LLM fallback
        $text = $productName;
        if ($packageSize) {
            $text .= " ({$packageSize})";
        }

        try {
            $llmResult = $this->llmOrchestrator->query(
                $user,
                "Extract the package size from this product: \"{$text}\"\n\nRespond ONLY with a JSON object: {\"quantity\": number, \"unit\": \"string\"}\nExamples:\n- \"Strawberries 1lb\" → {\"quantity\": 1, \"unit\": \"lb\"}\n- \"Coca-Cola 12 Pack 12 fl oz\" → {\"quantity\": 144, \"unit\": \"fl oz\"}\n- \"Eggs Large Grade A Dozen\" → {\"quantity\": 12, \"unit\": \"ct\"}\nIf you cannot determine the size, respond with: {\"quantity\": null, \"unit\": null}",
                'You are a product data extraction assistant. Extract package sizes from product names. Be precise and respond only with JSON.',
                'single',
            );

            if ($llmResult['success'] && !empty($llmResult['response'])) {
                $parsed = $this->parseLlmResponse($llmResult['response']);
                if ($parsed) {
                    return $this->computeUnitPrice($price, $parsed['quantity'], $parsed['unit'], $packageSize ?? $text);
                }
            }
        } catch (\Exception $e) {
            Log::debug('UnitPriceNormalizer: LLM fallback failed', [
                'product' => $productName,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Compare an array of vendor prices and sort by unit price, flagging the best value.
     *
     * @param array $vendorPrices Array of ['price' => float, 'product_name' => string, 'package_size' => ?string, ...]
     * @return array Same array with added 'unit_price', 'unit_type', 'is_best_value' keys, sorted by unit_price
     */
    public function compareUnitPrices(array $vendorPrices): array
    {
        $normalized = [];

        foreach ($vendorPrices as $vp) {
            $result = $this->normalize(
                $vp['product_name'] ?? '',
                $vp['price'] ?? null,
                $vp['package_size'] ?? null,
            );

            $normalized[] = array_merge($vp, [
                'unit_price' => $result['unit_price'],
                'unit_quantity' => $result['unit_quantity'],
                'unit_type' => $result['unit_type'],
                'package_size' => $result['package_size'] ?? ($vp['package_size'] ?? null),
                'is_best_value' => false,
            ]);
        }

        // Sort: items with unit prices first (ascending), then items without
        usort($normalized, function ($a, $b) {
            if ($a['unit_price'] === null && $b['unit_price'] === null) {
                return ($a['price'] ?? 0) <=> ($b['price'] ?? 0);
            }
            if ($a['unit_price'] === null) {
                return 1;
            }
            if ($b['unit_price'] === null) {
                return -1;
            }

            return $a['unit_price'] <=> $b['unit_price'];
        });

        // Flag best value (first item with a unit price)
        foreach ($normalized as &$item) {
            if ($item['unit_price'] !== null) {
                $item['is_best_value'] = true;
                break;
            }
        }
        unset($item);

        return $normalized;
    }

    /**
     * Extract quantity and unit from product name / package size using regex.
     *
     * @return array{quantity: float, unit: string, raw: string}|null
     */
    private function extractWithRegex(string $productName, ?string $packageSize = null): ?array
    {
        // Try package_size first (more reliable when present)
        $texts = array_filter([$packageSize, $productName]);

        foreach ($texts as $text) {
            foreach (self::SIZE_PATTERNS as $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $quantity = (float) $matches[1];
                    $unit = strtolower(trim($matches[2]));
                    // Normalize "fl. oz" → "fl oz"
                    $unit = preg_replace('/fl\.?\s*oz/', 'fl oz', $unit) ?? $unit;

                    if ($quantity > 0) {
                        return [
                            'quantity' => $quantity,
                            'unit' => $unit,
                            'raw' => $matches[0],
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Compute the normalized unit price.
     *
     * @return array{unit_price: float|null, unit_quantity: float|null, unit_type: string|null, package_size: string|null}
     */
    private function computeUnitPrice(float $price, float $quantity, string $unit, ?string $packageSize): array
    {
        $unitLower = strtolower($unit);

        if (isset(self::UNIT_CONVERSIONS[$unitLower])) {
            [$baseUnit, $divisor] = self::UNIT_CONVERSIONS[$unitLower];
            $baseQuantity = $quantity / $divisor;
            $unitPrice = $baseQuantity > 0 ? round($price / $baseQuantity, 4) : null;

            return [
                'unit_price' => $unitPrice,
                'unit_quantity' => round($quantity, 4),
                'unit_type' => $baseUnit,
                'package_size' => $packageSize,
            ];
        }

        // Unknown unit — just compute price per raw quantity
        $unitPrice = $quantity > 0 ? round($price / $quantity, 4) : null;

        return [
            'unit_price' => $unitPrice,
            'unit_quantity' => round($quantity, 4),
            'unit_type' => $unitLower,
            'package_size' => $packageSize,
        ];
    }

    /**
     * Parse LLM JSON response for quantity and unit.
     *
     * @return array{quantity: float, unit: string}|null
     */
    private function parseLlmResponse(string $response): ?array
    {
        // Extract JSON from response (may contain markdown code blocks)
        if (preg_match('/\{[^}]+\}/', $response, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (
                is_array($decoded) &&
                isset($decoded['quantity'], $decoded['unit']) &&
                is_numeric($decoded['quantity']) &&
                is_string($decoded['unit']) &&
                $decoded['quantity'] > 0
            ) {
                return [
                    'quantity' => (float) $decoded['quantity'],
                    'unit' => strtolower($decoded['unit']),
                ];
            }
        }

        return null;
    }
}
