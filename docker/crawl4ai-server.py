"""Minimal HTTP wrapper around crawl4ai library.

Exposes /health and /crawl endpoints compatible with CrawlAIService.php.
Runs inside the main container via supervisord, replacing the removed
crawl4ai-server CLI (dropped in crawl4ai >= 0.8).
"""

import argparse
import json
import logging
import traceback

from aiohttp import web

logging.basicConfig(level=logging.INFO, format="%(asctime)s [crawl4ai-server] %(message)s")
log = logging.getLogger(__name__)

crawler = None


async def init_crawler():
    global crawler
    from crawl4ai import AsyncWebCrawler, BrowserConfig

    browser_config = BrowserConfig(headless=True)
    crawler = AsyncWebCrawler(config=browser_config)
    await crawler.start()
    log.info("Crawler initialized")


async def health(request):
    return web.json_response({"status": "ok"})


async def crawl(request):
    try:
        data = await request.json()

        url = data.get("urls", "")
        if isinstance(url, list):
            url = url[0] if url else ""

        if not url:
            return web.json_response({"error": "No URL provided"}, status=400)

        from crawl4ai import CrawlerRunConfig

        run_kwargs = {}
        if data.get("css_selector"):
            run_kwargs["css_selector"] = data["css_selector"]
        if data.get("wait_for"):
            run_kwargs["wait_for"] = data["wait_for"]

        run_config = CrawlerRunConfig(**run_kwargs)
        result = await crawler.arun(url=url, config=run_config)

        if not result.success:
            return web.json_response(
                {"error": result.error_message or "Crawl failed"},
                status=502,
            )

        markdown = ""
        if result.markdown:
            markdown = result.markdown.raw_markdown or ""

        return web.json_response({
            "result": {
                "markdown": markdown,
                "html": result.html or "",
                "cleaned_html": result.cleaned_html or "",
                "metadata": result.metadata or {},
                "extracted_content": result.extracted_content,
            }
        })
    except Exception as e:
        log.error("Crawl error: %s\n%s", e, traceback.format_exc())
        return web.json_response({"error": str(e)}, status=500)


async def on_startup(app):
    await init_crawler()


async def on_cleanup(app):
    global crawler
    if crawler:
        await crawler.close()


app = web.Application()
app.router.add_get("/health", health)
app.router.add_post("/crawl", crawl)
app.on_startup.append(on_startup)
app.on_cleanup.append(on_cleanup)

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=11235)
    args = parser.parse_args()

    web.run_app(app, host=args.host, port=args.port)
