# Sanitize Pattern

Client-side sanitization utilities for HTML and CSS content.

## HTML Sanitization

Used for search highlights and any user-provided HTML:

```typescript
import { sanitizeHighlight } from "@/lib/sanitize";

// Only allows: <em>, <mark>, <strong>, <b>
const safe = sanitizeHighlight(searchResult.highlight);
```

Uses `DOMPurify` with a strict allowlist. Use this whenever rendering HTML from search results or any external source via `dangerouslySetInnerHTML`.

## CSS Sanitization

Strips dangerous CSS patterns that could execute scripts or exfiltrate data:

```typescript
import { sanitizeCss } from "@/lib/sanitize";

const safeCss = sanitizeCss(userProvidedCss);
```

### Blocked Patterns

| Pattern | Risk |
|---------|------|
| `expression()` | Script execution (IE) |
| `javascript:` | Script execution |
| `@import` | External CSS injection |
| `behavior:` | Script execution (IE) |
| `-moz-binding:` | Script execution (Firefox) |
| `url(data:...)` | Data exfiltration |
| `url(javascript:...)` | Script execution |

Blocked patterns are replaced with `/* blocked */`.

## Where Used

- Search result highlighting (`sanitizeHighlight`)
- User-customizable CSS themes (`sanitizeCss`)
- Any place user-provided HTML/CSS is rendered

**Key files:** `frontend/lib/sanitize.ts`

**Related:** [Pattern: Security](security.md)
