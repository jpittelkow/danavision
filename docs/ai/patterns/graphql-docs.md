# GraphQL Documentation Pattern

Keep the in-app GraphQL API documentation in sync with the schema.

## Source of Truth

The GraphQL schema (`backend/graphql/schema.graphql`) is the source of truth. Documentation in `frontend/lib/help/help-content.ts` (under the `graphql-api` category) must match the schema.

## Article Structure

| Article ID | Covers |
|---|---|
| `graphql-getting-started` | Endpoint, auth, first request, response format, variables |
| `graphql-queries` | All Query type fields with args, return types, permissions, examples |
| `graphql-mutations` | All Mutation type fields with inputs, return types, examples |
| `graphql-types` | All type, input, enum, scalar definitions with field tables |
| `graphql-errors` | Error format, common codes, troubleshooting |
| `graphql-rate-limiting` | Rate limit headers, depth/complexity, CORS, introspection, key rotation |
| `graphql-pagination` | PaginatorInfo, first/page args, iteration examples |

## Documentation Standards

1. **Every query/mutation** must have: description, arguments table, return type, permission requirement, complete example request and response
2. **Example responses** must use realistic data matching the type definitions
3. **Permission requirements** must match the `@can` directives in the schema
4. **Rate limit values** should reference "configurable" defaults since admins can change them
5. **Types** should be documented as tables with field name, type, and description

## Example: Documenting a Query

```markdown
### myNotifications

Get the authenticated user's notifications with optional filtering and pagination.

| Argument | Type | Default | Description |
|---|---|---|---|
| category | String | null | Filter by notification category |
| unreadOnly | Boolean | null | Only return unread notifications |
| first | Int | 25 | Results per page |
| page | Int | 1 | Page number |

**Returns:** `NotificationPaginator!`

\`\`\`graphql
query {
  myNotifications(unreadOnly: true, first: 10) {
    data {
      id
      title
      message
      readAt
    }
    paginatorInfo {
      currentPage
      lastPage
      total
    }
  }
}
\`\`\`
```

## Example: Documenting a Mutation

```markdown
### updateProfile

Update the authenticated user's profile.

**Input:** `UpdateProfileInput!`

| Field | Type | Description |
|---|---|---|
| name | String | New display name |
| email | String | New email (triggers verification) |

**Returns:** `UpdateProfilePayload!` — contains `user` and `emailVerificationSent`.

\`\`\`graphql
mutation {
  updateProfile(input: { name: "Jane" }) {
    user { id name }
    emailVerificationSent
  }
}
\`\`\`
```

## Feature Flag Gating

The GraphQL API category uses `featureFlag: "graphqlEnabled"` on the `HelpCategory`. This automatically hides the entire category (and all its articles) when GraphQL is disabled. No additional conditional logic is needed.

## Download Button

The `DownloadDocsButton` component (`frontend/components/help/download-docs-button.tsx`) concatenates all articles in the category into a single markdown file. It is rendered by the help modal when viewing any article in the `graphql-api` category.

## Key Files

- `backend/graphql/schema.graphql` — Schema (source of truth)
- `frontend/lib/help/help-content.ts` — Help articles (`graphql-api` category)
- `frontend/components/help/download-docs-button.tsx` — Download button
- `frontend/components/help/help-center-modal.tsx` — Download button integration
- `backend/config/search-pages.php` — Search entries (`help-graphql-*`)
- `backend/config/lighthouse.php` — Security defaults (depth, complexity)

## Related

- [Update GraphQL Docs Recipe](../recipes/update-graphql-docs.md)
- [Add Help Article Recipe](../recipes/add-help-article.md)
- [UI Patterns: Help System](ui-patterns.md#help-system)
