import DOMPurify from "dompurify";

/**
 * Sanitize HTML from search highlights, allowing only formatting tags.
 */
export function sanitizeHighlight(html: string): string {
  return DOMPurify.sanitize(html, {
    ALLOWED_TAGS: ["em", "mark", "strong", "b"],
  });
}

/**
 * Dangerous CSS patterns that could execute scripts or exfiltrate data.
 */
const DANGEROUS_CSS_PATTERNS: RegExp[] = [
  /expression\s*\(/gi,
  /javascript\s*:/gi,
  /@\s*import/gi,
  /behavior\s*:/gi,
  /-moz-binding\s*:/gi,
  /url\s*\(\s*["']?\s*data\s*:/gi,
  /url\s*\(\s*["']?\s*javascript\s*:/gi,
];

/**
 * Sanitize CSS by stripping dangerous patterns that could execute
 * scripts or exfiltrate data (e.g. expression(), @import, data: URIs).
 */
/**
 * Strip CSS comments to prevent bypass via comment injection
 * (e.g. `@im\/*\*\/port`).
 */
function stripCssComments(css: string): string {
  return css.replace(/\/\*[\s\S]*?\*\//g, "");
}

export function sanitizeCss(css: string): string {
  let sanitized = stripCssComments(css);
  // Two passes to catch patterns reconstructed after first-pass removal
  // (e.g. "expresexpression(sion(" → "expression(" after pass 1)
  for (let pass = 0; pass < 2; pass++) {
    for (const pattern of DANGEROUS_CSS_PATTERNS) {
      sanitized = sanitized.replace(pattern, "/* blocked */");
    }
  }
  return sanitized;
}
