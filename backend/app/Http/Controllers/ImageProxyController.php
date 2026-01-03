<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ImageProxyController extends Controller
{
    /**
     * Proxy and cache an external image to prevent broken images.
     * 
     * This handles issues like:
     * - Google Shopping thumbnails that expire
     * - CORS restrictions on external images
     * - Rate limiting from image hosts
     */
    public function proxy(Request $request): Response
    {
        $validated = $request->validate([
            'url' => ['required', 'url'],
        ]);

        $url = $validated['url'];
        
        // Generate a unique cache key based on the URL
        $cacheKey = md5($url);
        $extension = $this->getExtensionFromUrl($url);
        $cachePath = "public/image-cache/{$cacheKey}.{$extension}";

        // Check if we have a cached version
        if (Storage::exists($cachePath)) {
            $content = Storage::get($cachePath);
            $mimeType = Storage::mimeType($cachePath) ?: 'image/jpeg';
            
            return response($content)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=604800'); // 7 days
        }

        // Fetch the image from the external URL
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'image/*',
                ])
                ->get($url);

            if (!$response->successful()) {
                return $this->fallbackImage();
            }

            $content = $response->body();
            $contentType = $response->header('Content-Type') ?? 'image/jpeg';

            // Validate it's actually an image
            if (!str_starts_with($contentType, 'image/')) {
                return $this->fallbackImage();
            }

            // Store in cache
            Storage::put($cachePath, $content);

            return response($content)
                ->header('Content-Type', $contentType)
                ->header('Cache-Control', 'public, max-age=604800');
        } catch (\Exception $e) {
            return $this->fallbackImage();
        }
    }

    /**
     * Return a fallback placeholder image.
     */
    protected function fallbackImage(): Response
    {
        // Return a simple SVG placeholder
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
  <rect width="200" height="200" fill="#f3f4f6"/>
  <path d="M100 60c-22.1 0-40 17.9-40 40s17.9 40 40 40 40-17.9 40-40-17.9-40-40-40zm0 70c-16.5 0-30-13.5-30-30s13.5-30 30-30 30 13.5 30 30-13.5 30-30 30z" fill="#9ca3af"/>
  <circle cx="90" cy="95" r="5" fill="#9ca3af"/>
  <path d="M85 115l10-15 8 10 12-16 15 21H85z" fill="#9ca3af"/>
</svg>
SVG;

        return response($svg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Get file extension from URL.
     */
    protected function getExtensionFromUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // Default to jpg if no extension or invalid
        if (!in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return 'jpg';
        }

        return strtolower($extension);
    }

    /**
     * Clear the image cache (admin function).
     */
    public function clearCache(): Response
    {
        $files = Storage::files('public/image-cache');
        
        foreach ($files as $file) {
            Storage::delete($file);
        }

        return response()->json([
            'message' => 'Image cache cleared',
            'files_deleted' => count($files),
        ]);
    }
}
