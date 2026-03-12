# Update GraphQL API Documentation

Update the in-app GraphQL API documentation when the schema changes.

## When to Use

After modifying `backend/graphql/schema.graphql` — adding, changing, or removing queries, mutations, types, inputs, or enums.

## Steps

### 1. Read the Current Schema

Read `backend/graphql/schema.graphql` to understand the full current schema.

### 2. Read Current Documentation

Read the GraphQL API category in `frontend/lib/help/help-content.ts` (search for `slug: "graphql-api"`).

### 3. Update Affected Articles

Based on what changed in the schema, update the corresponding articles:

| Schema Change | Article to Update (id) |
|---|---|
| New/modified query | `graphql-queries` (Queries Reference) |
| New/modified mutation | `graphql-mutations` (Mutations Reference) |
| New/modified type, input, or enum | `graphql-types` (Types & Inputs Reference) |
| New pagination type | `graphql-pagination` (Pagination) |
| Auth/security changes | `graphql-getting-started` and `graphql-rate-limiting` |
| Error handling changes | `graphql-errors` (Error Handling) |

For each query/mutation, ensure the documentation includes:
- Description
- Arguments table (name, type, default, description)
- Return type
- Permission requirement (if any)
- Complete example request and response

### 4. Update Search Keywords

If new terms were introduced, update the `content` field for the relevant help article entry in `backend/config/search-pages.php` (search for `help-graphql-`).

### 5. Reindex Search

```bash
docker exec sourdough-dev bash -c "cd /var/www/html/backend && php artisan search:reindex pages"
```

### 6. Verify

1. Open Help Center (?) → GraphQL API category
2. Verify updated articles render correctly with proper markdown formatting
3. Click "Download as Markdown" and confirm the `.md` file includes the changes
4. Search for new terms via Cmd+K and confirm results appear

## Checklist

- [ ] Schema read and changes identified
- [ ] Affected articles in `help-content.ts` updated
- [ ] Example requests/responses match actual schema
- [ ] Permission requirements match `@can` directives in schema
- [ ] Search keywords updated in `search-pages.php`
- [ ] Search reindexed
- [ ] Download button generates complete, accurate markdown
- [ ] Cmd+K search finds updated articles

## Related

- [Add Help Article](add-help-article.md) — Adding entirely new help articles
- [GraphQL Documentation Pattern](../patterns/graphql-docs.md) — Standards for documenting queries/mutations
