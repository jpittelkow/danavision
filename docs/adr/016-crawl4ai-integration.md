# ADR 016: Crawl4AI Integration for Price Discovery

## Status

**Superseded** — Reverted to Firecrawl (ADR 011) for reliability. Crawl4AI had poor price discovery on major retailers (Amazon, Target, Walmart).

## Date

2026-01-24

## Context

DanaVision previously relied on Firecrawl.dev for web scraping and price discovery (see ADR 011). While Firecrawl provided excellent capabilities, the API costs became a concern:

1. **Cost**: Firecrawl charges per page scraped (~$0.009/page) and per agent request (~$0.05-0.10)
2. **External Dependency**: Reliance on third-party API availability
3. **API Key Requirement**: Users needed to configure their own Firecrawl API key

Monthly costs for active usage could reach $100-500+ depending on usage patterns.

## Decision

We integrated **Crawl4AI**, an open-source web scraping library, directly into the DanaVision Docker container. This eliminates per-page API costs while maintaining the same functionality.

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                   DanaVision Container                       │
│                                                              │
│  ┌──────────┐  ┌──────────┐  ┌────────────────────────────┐ │
│  │  Nginx   │  │ PHP-FPM  │  │    Crawl4AI Service        │ │
│  │   :80    │  │          │  │    (Python FastAPI)        │ │
│  └────┬─────┘  └────┬─────┘  │    localhost:5000          │ │
│       │             │        └─────────────┬──────────────┘ │
│       │             │                      │                │
│       │             │    HTTP calls        │                │
│       │             │◄────────────────────►│                │
│       │             │                      │                │
│  ┌────▼─────────────▼──────────────────────▼──────────────┐ │
│  │                    Supervisor                           │ │
│  │  - nginx           - scheduler                          │ │
│  │  - php-fpm         - queue-worker (x2)                  │ │
│  │  - crawl4ai                                             │ │
│  └─────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### How It Works

1. **Crawl4AI Service**: A Python FastAPI service running on localhost:5000
   - Uses Playwright with system Chromium for browser automation
   - Returns page content as markdown (LLM-friendly format)
   - Managed by Supervisor alongside other processes

2. **PHP Integration**: `Crawl4AIService.php` calls the local Python API
   - Single URL scraping: `POST /scrape`
   - Batch scraping: `POST /batch`
   - Health check: `GET /health`

3. **Price Extraction**: Uses existing AI service (OpenAI/Claude/Gemini)
   - Scraped markdown is sent to LLM for structured price extraction
   - Only LLM API costs remain (~$0.002/extraction with gpt-4o-mini)

### Implementation Details

**New Files:**
- `docker/crawl4ai/service.py` - FastAPI service wrapping Crawl4AI
- `docker/crawl4ai/requirements.txt` - Python dependencies
- `backend/app/Services/Crawler/Crawl4AIService.php` - PHP client

**Modified Files:**
- `docker/Dockerfile` - Added Python, Chromium, Crawl4AI
- `docker/supervisord.conf` - Added crawl4ai program
- `backend/app/Services/Crawler/StoreDiscoveryService.php` - Uses Crawl4AI

**Container Changes:**
- Added Python 3.11 with pip
- Added Chromium browser and dependencies
- ~400-500MB container size increase

## Alternatives Considered

### 1. Keep Firecrawl with Spark 1 Mini

- **Pros**: Minimal code changes, managed service
- **Cons**: Still has API costs (~60% cheaper but not free)
- **Why rejected**: Long-term cost savings preferred

### 2. ScrapeGraphAI (Alternative Open Source)

- **Pros**: Built-in AI extraction, similar to Firecrawl
- **Cons**: Less mature, smaller community
- **Why rejected**: Crawl4AI has larger community (58k+ GitHub stars)

### 3. Separate Sidecar Container

- **Pros**: Cleaner separation, independent scaling
- **Cons**: More complex deployment, inter-container networking
- **Why rejected**: User requested single container for simplicity

## Consequences

### Positive

1. **Cost Reduction**: ~95% reduction in scraping costs (only LLM extraction remains)
2. **No External API**: No Firecrawl API key required
3. **Self-Contained**: Everything runs in single container
4. **Open Source**: Full control over scraping behavior
5. **Privacy**: All scraping happens locally

### Negative

1. **Container Size**: ~400-500MB increase (Chromium + Python)
2. **Memory Usage**: +256-512MB for Chromium processes
3. **Maintenance**: Must update Crawl4AI dependency manually
4. **Anti-Bot**: Some sites may block headless browsers
5. **LLM Dependency**: Still requires AI provider for price extraction

### Mitigations

- Chromium runs in headless mode with stealth flags
- Retry logic with exponential backoff for failed scrapes
- AI provider is already required for other DanaVision features
- Container resources documented (recommend 2GB RAM)

## Cost Comparison

| Operation | Firecrawl | Crawl4AI |
|-----------|----------:|----------:|
| URL scrape (per page) | ~$0.009 | $0 |
| Price discovery (1K) | ~$50-100 | ~$2 (LLM only) |
| Monthly (50K ops) | ~$450+ | ~$10 |

**Estimated Savings**: ~$400/month for active users

## Related ADRs

- ADR 009: AI Background Job System
- ADR 011: Firecrawl Price Discovery (superseded by this ADR)
- ADR 012: Store Registry Architecture

## Future Enhancements

1. **Stealth Mode**: Add browser fingerprint randomization
2. **Proxy Support**: Allow configuring proxy for blocked sites
3. **Caching**: Cache scraped content to reduce redundant requests
4. **Cloud API**: Crawl4AI is releasing a cloud API - could be fallback option
