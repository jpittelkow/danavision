# Changelog

## [2.0.6]

- Fix crawl4ai Docker build: set HOME and make /var/www writable for crawl4ai-setup

## [2.0.5]

- Fix crawl4ai: install Playwright browsers as www-data user and create required directories

## [2.0.4]

- Add crawl4ai HTTP wrapper server (replaces removed `crawl4ai-server` CLI in crawl4ai >= 0.8)
- Fix supervisord to use new Python-based crawl4ai server
- Update google/protobuf to fix DoS vulnerability (GHSA-p2gh-cfq4-4wjc)

## [2.0.3]

- Fix SerpAPI 400 errors by retrying without location when address format is unrecognized
- Trigger Docker image rebuild to include crawl4ai binary (pip install was missing from prior build)

## [2.0.2]

- Add price search provider connection testing endpoint and UI
- Smart Add: enrich AI suggestions with product images from price search providers
- Add Crawl4AI server as supervised process in Docker container
- Move location preferences to user profile page with geolocation support
- Improve PriceApiService logging for provider usage and search results

## [2.0.1]

- Add single list item endpoint (`GET /items/{item}`) with vendor prices
- Move Shopping Location settings from preferences to profile page
- Smart Add: per-item list selection and "no lists" warning with create link
- Remove unnecessary SSRF validation on internal CrawlAI service URL
- Seed missing notification templates migration

## 0.1.0

- Initial project setup based on Sourdough
- Shopping list management with multi-vendor price comparison (Phases 1-5)
- Store registry with chain hierarchy, user preferences, and nearby discovery
- Price search via SerpAPI, Kroger, Walmart, Best Buy, and CrawlAI providers
- Unit price normalization for cross-vendor comparison
- List sharing with email-based invitations and permission levels (view/edit/admin)
- Deal scanning with AI-powered coupon extraction from images
- Smart Add for intelligent item addition from text/images
- Ask Dana conversational AI assistant with 14 tools and SSE streaming
- Scheduled store crawling with CSS-first extraction and LLM fallback (Phase 6)
- Store-specific scrape instructions with automatic selector maintenance
- Per-store analysis and split-shopping optimization
- Price drop notifications with configurable thresholds
- Custom AI prompts for product identification and price recommendation
