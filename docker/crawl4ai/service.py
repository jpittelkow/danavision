"""
Crawl4AI FastAPI Service

Provides HTTP API for web scraping, called by PHP services.
This service runs inside the DanaVision container and provides
local web scraping capabilities using Crawl4AI with Chromium.

Endpoints:
    POST /scrape - Scrape a single URL and return markdown
    POST /batch - Scrape multiple URLs concurrently
    GET /health - Health check endpoint

Environment Variables:
    CRAWL4AI_MAX_CONCURRENT: Maximum concurrent scrapes in batch mode (default: 3)
"""
import asyncio
import logging
import time
from os import environ
from urllib.parse import urlparse
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, field_validator
from crawl4ai import AsyncWebCrawler, BrowserConfig, CrawlerRunConfig

# Defensive patch: CrawlResult has no .title; some crawl4ai code paths expect it.
# Add a property that reads from metadata['title'] so result.title never raises.
try:
    from crawl4ai.models import CrawlResult as _CrawlResult
    if not hasattr(_CrawlResult, "title"):
        _CrawlResult.title = property(lambda self: (getattr(self, "metadata", None) or {}).get("title"))
except Exception:
    pass  # If import fails, _title_from_result() still handles our code paths

logger = logging.getLogger("crawl4ai.service")
logging.basicConfig(level=logging.INFO, format="%(asctime)s %(name)s %(levelname)s %(message)s")


def _markdown_str(md) -> str | None:
    """Normalize Crawl4AI markdown to string. Can be str or MarkdownGenerationResult (has raw_markdown)."""
    if md is None:
        return None
    if isinstance(md, str):
        return md
    return getattr(md, "raw_markdown", None)


def _title_from_result(result) -> str | None:
    """CrawlResult has no .title; page title is in result.metadata['title']."""
    return (getattr(result, "metadata", None) or {}).get("title")


# Configuration from environment
MAX_CONCURRENT_SCRAPES = int(environ.get('CRAWL4AI_MAX_CONCURRENT', '3'))

app = FastAPI(
    title="Crawl4AI Service",
    description="Internal web scraping service for DanaVision",
    version="1.0.0"
)


class ScrapeRequest(BaseModel):
    """Request model for single URL scraping."""
    url: str
    wait_for: str | None = None  # CSS selector to wait for
    timeout: int = 30000  # milliseconds

    @field_validator('url')
    @classmethod
    def validate_url(cls, v: str) -> str:
        """Validate that the URL is properly formatted."""
        parsed = urlparse(v)
        if not parsed.scheme or not parsed.netloc:
            raise ValueError(f"Invalid URL: {v}")
        if parsed.scheme not in ('http', 'https'):
            raise ValueError(f"URL must use http or https scheme: {v}")
        return v


class ScrapeResponse(BaseModel):
    """Response model for scrape results."""
    success: bool
    markdown: str | None = None
    html: str | None = None
    title: str | None = None
    error: str | None = None


class BatchScrapeRequest(BaseModel):
    """Request model for batch URL scraping."""
    urls: list[str]
    timeout: int = 30000

    @field_validator('urls')
    @classmethod
    def validate_urls(cls, v: list[str]) -> list[str]:
        """Validate that all URLs are properly formatted."""
        for url in v:
            parsed = urlparse(url)
            if not parsed.scheme or not parsed.netloc:
                raise ValueError(f"Invalid URL: {url}")
            if parsed.scheme not in ('http', 'https'):
                raise ValueError(f"URL must use http or https scheme: {url}")
        return v


class BatchScrapeResponse(BaseModel):
    """Response model for batch scrape results."""
    results: list[ScrapeResponse]


def get_browser_config() -> BrowserConfig:
    """
    Create browser configuration for Crawl4AI.
    Uses system Chromium installed in the container.
    """
    return BrowserConfig(
        headless=True,
        browser_type="chromium",
        chrome_channel="chromium",
        extra_args=[
            "--no-sandbox",
            "--disable-dev-shm-usage",
            "--disable-gpu",
            "--disable-software-rasterizer",
            "--disable-extensions",
            "--single-process",
        ]
    )


@app.post("/scrape", response_model=ScrapeResponse)
async def scrape_url(request: ScrapeRequest):
    """
    Scrape a single URL and return markdown content.

    Args:
        request: ScrapeRequest with url, optional wait_for selector, and timeout

    Returns:
        ScrapeResponse with markdown content or error message
    """
    logger.info("Scrape request: url=%s wait_for=%s timeout=%d", request.url, request.wait_for, request.timeout)
    start = time.perf_counter()
    try:
        # Build run config with wait_for selector if provided
        run_config = CrawlerRunConfig(
            wait_until="networkidle",
            page_timeout=request.timeout,
            wait_for=request.wait_for,  # CSS selector to wait for
        )

        browser_config = get_browser_config()

        async with AsyncWebCrawler(config=browser_config) as crawler:
            result = await crawler.arun(
                url=request.url,
                config=run_config
            )

        duration_ms = int((time.perf_counter() - start) * 1000)
        md = _markdown_str(result.markdown)
        md_len = len(md) if md else 0
        title = _title_from_result(result)

        if not result.success:
            logger.warning(
                "Scrape failed: url=%s error=%s duration_ms=%d",
                request.url, result.error_message or "Scrape failed", duration_ms
            )
            return ScrapeResponse(
                success=False,
                error=result.error_message or "Scrape failed"
            )

        logger.info(
            "Scrape completed: url=%s success=true markdown_len=%d title=%s duration_ms=%d",
            request.url, md_len, title or "(none)", duration_ms
        )
        return ScrapeResponse(
            success=True,
            markdown=md,
            html=result.html,
            title=title
        )
    except Exception as e:
        duration_ms = int((time.perf_counter() - start) * 1000)
        logger.exception("Scrape exception: url=%s error=%s duration_ms=%d", request.url, str(e), duration_ms)
        return ScrapeResponse(success=False, error=str(e))


@app.post("/batch", response_model=BatchScrapeResponse)
async def batch_scrape(request: BatchScrapeRequest):
    """
    Scrape multiple URLs concurrently with browser reuse.

    Args:
        request: BatchScrapeRequest with list of urls and timeout

    Returns:
        BatchScrapeResponse with list of results for each URL
    """
    if not request.urls:
        logger.info("Batch scrape: empty URLs list")
        return BatchScrapeResponse(results=[])

    logger.info(
        "Batch scrape request: urls_count=%d timeout=%d urls=%s",
        len(request.urls), request.timeout, request.urls
    )
    batch_start = time.perf_counter()

    browser_config = get_browser_config()
    run_config = CrawlerRunConfig(
        wait_until="networkidle",
        page_timeout=request.timeout,
    )

    responses = []

    # Limit concurrency to avoid overwhelming the system
    # Configurable via CRAWL4AI_MAX_CONCURRENT environment variable
    semaphore = asyncio.Semaphore(MAX_CONCURRENT_SCRAPES)

    async def scrape_one(crawler: AsyncWebCrawler, url: str) -> ScrapeResponse:
        """Scrape a single URL using shared crawler instance."""
        async with semaphore:
            start = time.perf_counter()
            try:
                logger.info("Batch scraping URL: %s", url)
                result = await crawler.arun(url=url, config=run_config)
                duration_ms = int((time.perf_counter() - start) * 1000)
                md = _markdown_str(result.markdown)
                md_len = len(md) if md else 0

                if not result.success:
                    logger.warning(
                        "Batch scrape failed for url=%s error=%s duration_ms=%d",
                        url, result.error_message or "Scrape failed", duration_ms
                    )
                    return ScrapeResponse(
                        success=False,
                        error=result.error_message or "Scrape failed"
                    )

                logger.info(
                    "Batch scrape OK: url=%s markdown_len=%d duration_ms=%d",
                    url, md_len, duration_ms
                )
                return ScrapeResponse(
                    success=True,
                    markdown=md,
                    html=result.html,
                    title=_title_from_result(result)
                )
            except Exception as e:
                duration_ms = int((time.perf_counter() - start) * 1000)
                logger.warning("Batch scrape exception for url=%s error=%s duration_ms=%d", url, str(e), duration_ms)
                return ScrapeResponse(success=False, error=str(e))

    try:
        # Reuse single browser instance for all URLs
        async with AsyncWebCrawler(config=browser_config) as crawler:
            tasks = [scrape_one(crawler, url) for url in request.urls]
            results = await asyncio.gather(*tasks, return_exceptions=True)

            for result in results:
                if isinstance(result, Exception):
                    logger.error("Batch scrape task raised: %s", result)
                    responses.append(ScrapeResponse(
                        success=False,
                        error=str(result)
                    ))
                else:
                    responses.append(result)
    except Exception as e:
        logger.exception("Batch scrape browser/startup failed: %s", e)
        for _ in request.urls:
            responses.append(ScrapeResponse(success=False, error=str(e)))

    batch_duration_ms = int((time.perf_counter() - batch_start) * 1000)
    ok = sum(1 for r in responses if r.success)
    logger.info(
        "Batch scrape completed: urls=%d successful=%d duration_ms=%d",
        len(request.urls), ok, batch_duration_ms
    )
    return BatchScrapeResponse(results=responses)


@app.get("/health")
async def health():
    """
    Health check endpoint.
    
    Returns:
        Status information for the service
    """
    return {
        "status": "ok",
        "service": "crawl4ai",
        "version": "1.0.0"
    }


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=5000)
