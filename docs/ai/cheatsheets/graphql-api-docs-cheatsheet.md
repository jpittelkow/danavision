# Cheat Sheet: Adding Feature-Flagged GraphQL API Documentation to a Help System

This cheat sheet describes how to add comprehensive, feature-flag-gated GraphQL API documentation to an in-app help center with a downloadable markdown export. Hand this to an AI assistant working on another Sourdough-based repo to replicate the pattern.

## Architecture Overview

```
GraphQL Schema (source of truth)
        ↓
Help Content (7 articles in a feature-flagged category)
        ↓
Help Modal (renders articles + download button)
        ↓
Search Index (articles discoverable via Cmd+K)
```

- **Feature flag gating**: The help category has a `featureFlag` field. When the flag is `false`, the entire category (and all articles) is hidden from the help center and search.
- **Permission gating**: The category also has a `permission` field. Only users with that permission see the category.
- **Download button**: Concatenates all articles into a single `.md` file for export to developers/agents.

## What to Change (10 steps)

### Step 1: Add `featureFlag` to `HelpCategory` Interface

**File:** `frontend/lib/help/help-content.ts`

Add `featureFlag?: string` to the `HelpCategory` interface:

```ts
export interface HelpCategory {
  slug: string;
  name: string;
  icon?: LucideIcon;
  articles: HelpArticle[];
  permission?: string;
  /** Feature flag key from useAppConfig().features. Hidden when flag is false. */
  featureFlag?: string;
}
```

### Step 2: Update `getAllCategories()` to Filter by Feature Flags

**File:** `frontend/lib/help/help-content.ts`

Change the signature to accept an optional `featureFlags` map:

```ts
export function getAllCategories(
  permissions: string[],
  featureFlags?: Record<string, boolean>
): HelpCategory[] {
  const permissionGated = permissionHelpCategories.filter(
    (cat) =>
      (!cat.permission || permissions.includes(cat.permission)) &&
      (!cat.featureFlag || featureFlags?.[cat.featureFlag] !== false)
  );
  return [...userHelpCategories, ...permissionGated];
}
```

Do the same for `findArticle()` and `getSearchableArticles()` — add `featureFlags?: Record<string, boolean>` parameter and pass it to `getAllCategories()`.

### Step 3: Pass Feature Flags from Help Modal

**File:** `frontend/components/help/help-center-modal.tsx`

```ts
import { useAppConfig } from "@/lib/app-config";

// Inside the component:
const { features } = useAppConfig();

const featureFlags = useMemo<Record<string, boolean>>(
  () => ({
    graphqlEnabled: features?.graphqlEnabled ?? false,
  }),
  [features]
);

// Pass featureFlags as second arg to all calls:
const categories = useMemo(() => getAllCategories(permissions, featureFlags), [permissions, featureFlags]);
// Also update: findArticle(..., featureFlags), getSearchableArticles(..., featureFlags)
```

### Step 4: Add the GraphQL API Help Category

**File:** `frontend/lib/help/help-content.ts`

Add a new category to `permissionHelpCategories`:

```ts
{
  slug: "graphql-api",
  name: "GraphQL API",
  icon: Terminal,  // from lucide-react
  permission: "settings.view",
  featureFlag: "graphqlEnabled",
  articles: [
    { id: "graphql-getting-started", title: "Getting Started", tags: [...], content: `...` },
    { id: "graphql-queries", title: "Queries Reference", tags: [...], content: `...` },
    { id: "graphql-mutations", title: "Mutations Reference", tags: [...], content: `...` },
    { id: "graphql-types", title: "Types & Inputs Reference", tags: [...], content: `...` },
    { id: "graphql-errors", title: "Error Handling", tags: [...], content: `...` },
    { id: "graphql-rate-limiting", title: "Rate Limiting & Security", tags: [...], content: `...` },
    { id: "graphql-pagination", title: "Pagination", tags: [...], content: `...` },
  ],
}
```

Write each article's `content` by reading `backend/graphql/schema.graphql` and documenting every query, mutation, type, input, and enum with:
- Description
- Arguments/fields table
- Return type
- Permission requirement
- Complete example request/response

### Step 5: Create DownloadDocsButton Component

**File:** `frontend/components/help/download-docs-button.tsx` (new)

```tsx
"use client";

import { Download } from "lucide-react";
import { Button } from "@/components/ui/button";
import type { HelpArticle } from "@/lib/help/help-content";

interface DownloadDocsButtonProps {
  articles: HelpArticle[];
  filename?: string;
}

export function DownloadDocsButton({
  articles,
  filename = "graphql-api-documentation.md",
}: DownloadDocsButtonProps) {
  const handleDownload = () => {
    const header = `# GraphQL API Documentation\n\nGenerated from the in-app help center.\n\n---\n\n`;
    const body = articles.map((article) => article.content).join("\n\n---\n\n");
    const markdown = header + body;

    const blob = new Blob([markdown], { type: "text/markdown;charset=utf-8" });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    link.parentNode?.removeChild(link);
    window.URL.revokeObjectURL(url);
  };

  return (
    <Button variant="outline" size="sm" onClick={handleDownload} className="gap-1.5">
      <Download className="h-3.5 w-3.5" />
      Download as Markdown
    </Button>
  );
}
```

### Step 6: Integrate Download Button into Help Modal

**File:** `frontend/components/help/help-center-modal.tsx`

Where the modal renders article content, add the download button conditionally:

```tsx
{currentArticle && articleData ? (
  <div>
    {articleData.category.slug === "graphql-api" && (
      <div className="flex justify-end mb-4">
        <DownloadDocsButton articles={articleData.category.articles} />
      </div>
    )}
    <HelpArticle content={articleData.article.content} />
  </div>
) : (
  // ... category grid
)}
```

### Step 7: Add Link from GraphQL Config Page

**File:** `frontend/app/(dashboard)/configuration/graphql/page.tsx`

Add a button-variant HelpLink below the page description:

```tsx
<HelpLink
  articleId="graphql-getting-started"
  label="View full API documentation"
  variant="button"
/>
```

### Step 8: Register Articles in Search

**File:** `backend/config/search-pages.php`

Add 7 entries, one per article:

```php
[
    'id' => 'help-graphql-getting-started',
    'title' => 'Help: GraphQL API - Getting Started',
    'subtitle' => 'Help article',
    'url' => 'help:graphql-getting-started',  // help: prefix opens in help modal
    'admin_only' => true,
    'permission' => 'settings.view',
    'content' => 'graphql api getting started authentication bearer endpoint curl',
],
// ... repeat for each article
```

**File:** `frontend/lib/search-pages.ts` (fallback)

Add matching entries with `url: "help:graphql-getting-started"` format.

### Step 9: Reindex Search

```bash
docker exec sourdough-dev bash -c "cd /var/www/html/backend && php artisan search:reindex pages"
```

### Step 10: Verify

- [ ] Help center shows "GraphQL API" category when enabled
- [ ] Category hidden when GraphQL is disabled
- [ ] All 7 articles render correctly
- [ ] "Download as Markdown" produces a complete `.md` file
- [ ] Config page "View full API documentation" opens help center
- [ ] Cmd+K search finds GraphQL help articles
- [ ] Non-admin users without `settings.view` don't see the category

## Keeping Docs Updated

When the GraphQL schema changes:

1. Read `backend/graphql/schema.graphql`
2. Update the affected article(s) in `frontend/lib/help/help-content.ts`
3. Update search keywords in `backend/config/search-pages.php` if needed
4. Reindex search
5. Test download button output

## Key Patterns

- **Feature flag naming**: Use the same key in `HelpCategory.featureFlag` as in `useAppConfig().features` (e.g., `"graphqlEnabled"`)
- **Search URL format**: `help:{article-id}` — the frontend intercepts this and opens the help modal
- **Permission consistency**: The category's `permission` field should match the config page's permission
- **Article IDs**: Use kebab-case with a descriptive prefix (e.g., `graphql-queries`, `graphql-errors`)
