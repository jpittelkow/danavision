# Recipe: Add Provider Icon

Add a new provider or channel icon to the shared icon set used across sign-in, configuration, and settings pages.

## When to Use

- Adding a new SSO provider (see also [Add SSO Provider](add-sso-provider.md) Step 7)
- Adding a new LLM provider that should show a branded icon
- Adding a new notification channel, email provider, or backup destination that should show an icon in config headers

## Prerequisites

Read: [frontend/components/provider-icons.tsx](../../frontend/components/provider-icons.tsx)

## Steps

### 1. Choose the Icon Key

The key must match how the provider is identified elsewhere:

- **SSO:** Same as `config/sso.php` `icon` and the provider id (e.g. `google`, `github`, `example`).
- **LLM:** Same as the provider slug from the API (e.g. `openai`, `claude`, `gemini`, `ollama`).
- **Backup/Email/Notifications:** Use a short slug (e.g. `s3`, `gdrive`, `sendgrid`).

### 2. Add the Icon to provider-icons.tsx

1. Open `frontend/components/provider-icons.tsx`.
2. Optionally add the key to the `ProviderIconId` type (for stricter typing).
3. Add an entry to the appropriate map:
   - **SSO:** `SSO_ICONS` (used for sign-in and SSO config).
   - **LLM:** `LLM_ICONS` (used for AI/LLM config).
   - **Other:** `ALL_ICONS` (or add to a dedicated map and merge into `ALL_ICONS`).

Use the existing `renderSvg(className, children)` helper for simple SVGs:

```tsx
// Example: add to SSO_ICONS
example: (className) =>
  renderSvg(
    className,
    <path d="M12 2L2 7v10l10 5 10-5V7L12 2z" />
  ),
```

For stroke-based icons (e.g. key), use an `<svg>` with `fill="none"` and `stroke="currentColor"` (see the `key` entry in `SSO_ICONS`).

### 3. Use the Icon

**Sign-in / SSO buttons:** Icons are resolved by the `icon` value from `GET /auth/sso/providers` (from `config/sso.php`). No change needed in `sso-buttons.tsx` if the backend returns the same key.

**Config pages:** Use `ProviderIcon` in CollapsibleCard headers or elsewhere:

```tsx
import { ProviderIcon } from "@/components/provider-icons";

<ProviderIcon provider="example" size="sm" style="mono" />
```

**Note:** The `style` prop accepts `"mono"` (default) or `"branded"`. Currently both render with `currentColor`; `"branded"` is reserved for future use with official brand colors.

## Checklist

- [ ] Icon key matches backend/config (SSO: `config/sso.php`; LLM: provider slug).
- [ ] Entry added to `SSO_ICONS`, `LLM_ICONS`, or `ALL_ICONS` in `provider-icons.tsx`.
- [ ] SVG uses `currentColor` for mono/theme-aware appearance (or official colors if implementing branded style later).
- [ ] Unknown keys fall back to `key` icon; no runtime errors.

## Related

- [Patterns: ProviderIcon](../patterns/ui-patterns.md#providericon)
- [Patterns: CollapsibleCard](../patterns/ui-patterns.md#collapsiblecard) (using icons in card headers)
- [Recipe: Add SSO Provider](add-sso-provider.md) (Step 7 for SSO icons)
- [Recipe: Add collapsible section](add-collapsible-section.md) (using icons in CollapsibleCard headers)
