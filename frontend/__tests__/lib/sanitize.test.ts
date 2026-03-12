import { describe, it, expect } from "vitest";
import { sanitizeHighlight, sanitizeCss } from "@/lib/sanitize";

describe("sanitizeHighlight", () => {
  it("allows em, mark, strong, b tags", () => {
    const html = '<em>hi</em> <mark>there</mark> <strong>bold</strong> <b>b</b>';
    expect(sanitizeHighlight(html)).toBe(html);
  });

  it("strips script tags", () => {
    expect(sanitizeHighlight('<script>alert(1)</script>hello')).toBe("hello");
  });

  it("strips img with onerror", () => {
    expect(sanitizeHighlight('<img onerror="alert(1)" src=x>text')).toBe("text");
  });

  it("strips div and span tags but keeps text", () => {
    expect(sanitizeHighlight("<div>content</div>")).toBe("content");
  });

  it("handles empty string", () => {
    expect(sanitizeHighlight("")).toBe("");
  });

  it("handles plain text", () => {
    expect(sanitizeHighlight("plain text")).toBe("plain text");
  });
});

describe("sanitizeCss", () => {
  it("blocks expression()", () => {
    expect(sanitizeCss("width: expression(alert(1))")).toContain("/* blocked */");
    expect(sanitizeCss("width: expression(alert(1))")).not.toContain("expression");
  });

  it("blocks javascript:", () => {
    expect(sanitizeCss("background: javascript:alert(1)")).toContain("/* blocked */");
  });

  it("blocks @import", () => {
    expect(sanitizeCss("@import url('evil.css')")).toContain("/* blocked */");
    expect(sanitizeCss("@import url('evil.css')")).not.toContain("@import");
  });

  it("blocks behavior:", () => {
    expect(sanitizeCss("behavior: url(xss.htc)")).toContain("/* blocked */");
  });

  it("blocks -moz-binding:", () => {
    expect(sanitizeCss("-moz-binding: url(xbl)")).toContain("/* blocked */");
  });

  it("blocks data: URI in url()", () => {
    expect(sanitizeCss("background: url(data:text/html,<script>)")).toContain("/* blocked */");
  });

  it("blocks javascript: URI in url()", () => {
    expect(sanitizeCss("background: url(javascript:alert(1))")).toContain("/* blocked */");
  });

  it("passes through safe CSS unchanged", () => {
    const safe = "color: red; font-size: 16px; background: #fff;";
    expect(sanitizeCss(safe)).toBe(safe);
  });

  it("handles empty string", () => {
    expect(sanitizeCss("")).toBe("");
  });
});
