/**
 * @deprecated Pages are now indexed in Meilisearch.
 * This file is kept as fallback for offline/error scenarios.
 * To add new pages, update backend/config/search-pages.php instead.
 * See docs/ai/recipes/add-searchable-page.md
 */
export interface SearchPage {
  id: string;
  title: string;
  subtitle?: string;
  url: string;
  keywords?: string[];
  adminOnly?: boolean;
}

const MAX_PAGE_RESULTS = 5;

export const SEARCH_PAGES: SearchPage[] = [
  // User pages (all users)
  {
    id: "dashboard",
    title: "Dashboard",
    url: "/dashboard",
    keywords: ["home", "overview"],
  },
  {
    id: "notifications",
    title: "Notifications",
    url: "/notifications",
    keywords: ["alerts", "messages"],
  },
  {
    id: "user-preferences",
    title: "User Preferences",
    url: "/user/preferences",
    keywords: ["settings", "profile"],
  },
  {
    id: "user-profile",
    title: "User Profile",
    url: "/user/profile",
    keywords: ["account", "avatar"],
  },
  {
    id: "user-security",
    title: "User Security",
    subtitle: "Password, 2FA, passkeys, connected accounts",
    url: "/user/security",
    keywords: ["password", "2fa", "two-factor", "mfa", "passkeys", "sso", "connected accounts"],
  },
  // Configuration pages (admin only)
  {
    id: "config-system",
    title: "Configuration > System",
    subtitle: "Application-wide settings",
    url: "/configuration/system",
    adminOnly: true,
    keywords: ["app", "general"],
  },
  {
    id: "config-branding",
    title: "Configuration > Theme & Branding",
    subtitle: "Visual customization",
    url: "/configuration/branding",
    adminOnly: true,
    keywords: ["theme", "palette", "logo"],
  },
  {
    id: "config-users",
    title: "Configuration > Users",
    subtitle: "Manage application users",
    url: "/configuration/users",
    adminOnly: true,
    keywords: ["manage users", "admin"],
  },
  {
    id: "config-groups",
    title: "Configuration > Groups",
    subtitle: "User groups and permissions",
    url: "/configuration/groups",
    adminOnly: true,
    keywords: ["groups", "permissions", "roles"],
  },
  {
    id: "config-security",
    title: "Configuration > Security",
    subtitle: "Auth and security settings",
    url: "/configuration/security",
    adminOnly: true,
    keywords: ["auth", "2fa", "password reset"],
  },
  {
    id: "config-sso",
    title: "Configuration > SSO",
    subtitle: "Single sign-on providers",
    url: "/configuration/sso",
    adminOnly: true,
    keywords: ["oauth", "google", "github", "login"],
  },
  {
    id: "config-api",
    title: "Configuration > Webhooks",
    subtitle: "Outgoing webhook endpoints",
    url: "/configuration/api",
    adminOnly: true,
    keywords: ["webhooks", "events", "automation"],
  },
  {
    id: "user-api-keys",
    title: "Security > API Keys",
    subtitle: "Manage personal API keys",
    url: "/user/security",
    adminOnly: false,
    keywords: ["api", "keys", "graphql", "token", "bearer", "rotate", "revoke"],
  },
  {
    id: "config-communications",
    title: "Configuration > Communications",
    subtitle: "Notification channels, email, templates, delivery log",
    url: "/configuration/notifications",
    adminOnly: true,
    keywords: ["channels", "telegram", "slack", "discord", "notifications", "communications"],
  },
  {
    id: "config-communications-email",
    title: "Configuration > Communications > Email",
    subtitle: "Email delivery configuration",
    url: "/configuration/notifications?tab=email",
    adminOnly: true,
    keywords: ["smtp", "mail", "mailgun", "sendgrid", "ses", "postmark"],
  },
  {
    id: "config-communications-templates",
    title: "Configuration > Communications > Templates",
    subtitle: "Email and notification templates",
    url: "/configuration/notifications?tab=templates",
    adminOnly: true,
    keywords: ["templates", "email", "notification", "push", "in-app", "chat"],
  },
  {
    id: "config-changelog",
    title: "Configuration > Changelog",
    subtitle: "Version history and release notes",
    url: "/configuration/changelog",
    adminOnly: false,
    keywords: ["changelog", "version", "release", "what's new", "updates", "export", "AI", "upgrade guide"],
  },
  {
    id: "config-ai",
    title: "Configuration > AI / LLM",
    subtitle: "LLM providers and modes",
    url: "/configuration/ai",
    adminOnly: true,
    keywords: ["llm", "openai", "claude", "providers"],
  },
  {
    id: "config-storage",
    title: "Configuration > Storage",
    subtitle: "File storage configuration",
    url: "/configuration/storage",
    adminOnly: true,
    keywords: ["s3", "files", "disk"],
  },
  {
    id: "config-search",
    title: "Configuration > Search",
    subtitle: "Manage search indexes",
    url: "/configuration/search",
    adminOnly: true,
    keywords: ["meilisearch", "index"],
  },
  {
    id: "config-audit",
    title: "Configuration > Audit Log",
    subtitle: "View system activity logs",
    url: "/configuration/audit",
    adminOnly: true,
    keywords: ["logs", "activity"],
  },
  {
    id: "config-logs",
    title: "Configuration > Application Logs",
    subtitle: "Real-time console log viewer",
    url: "/configuration/logs",
    adminOnly: true,
    keywords: ["console", "log viewer"],
  },
  {
    id: "config-access-logs",
    title: "Configuration > Access Logs (HIPAA)",
    subtitle: "PHI access audit trail",
    url: "/configuration/access-logs",
    adminOnly: true,
    keywords: ["hipaa", "phi"],
  },
  {
    id: "config-log-retention",
    title: "Configuration > Log retention",
    subtitle: "Retention and cleanup config",
    url: "/configuration/log-retention",
    adminOnly: true,
    keywords: ["retention", "cleanup"],
  },
  {
    id: "config-jobs",
    title: "Configuration > Jobs",
    subtitle: "Monitor scheduled jobs",
    url: "/configuration/jobs",
    adminOnly: true,
    keywords: ["scheduled", "queue", "tasks"],
  },
  {
    id: "config-backup",
    title: "Configuration > Backup & Restore",
    subtitle: "Manage system backups",
    url: "/configuration/backup",
    adminOnly: true,
    keywords: ["restore", "export"],
  },
  {
    id: "config-usage",
    title: "Configuration > Usage & Costs",
    subtitle: "Integration usage analytics and cost tracking",
    url: "/configuration/usage",
    adminOnly: true,
    keywords: ["usage", "cost", "analytics", "tokens", "llm", "billing", "spending"],
  },
  {
    id: "config-communications-delivery-log",
    title: "Configuration > Communications > Delivery Log",
    subtitle: "Notification delivery attempts and failures",
    url: "/configuration/notifications?tab=delivery-log",
    adminOnly: true,
    keywords: ["notification", "delivery", "log", "failed", "retry", "channel", "rate limit"],
  },
  {
    id: "config-communications-novu",
    title: "Configuration > Communications > Novu",
    subtitle: "Novu notification infrastructure",
    url: "/configuration/notifications?tab=novu",
    adminOnly: true,
    keywords: ["novu", "infrastructure", "workflows", "inbox"],
  },
  {
    id: "config-stripe",
    title: "Configuration > Stripe",
    subtitle: "Payment processing configuration",
    url: "/configuration/stripe",
    adminOnly: true,
    keywords: ["stripe", "payments", "billing", "webhooks"],
  },
  {
    id: "config-payments",
    title: "Configuration > Payment History",
    subtitle: "View payment transactions",
    url: "/configuration/payments",
    adminOnly: false,
    keywords: ["payments", "transactions", "history", "billing", "invoices"],
  },
  {
    id: "config-graphql",
    title: "Configuration > GraphQL API",
    subtitle: "GraphQL settings, API keys, and usage stats",
    url: "/configuration/graphql",
    adminOnly: true,
    keywords: ["graphql", "api", "keys", "rate limit", "introspection", "usage"],
  },
  // GraphQL API Documentation help articles
  {
    id: "help-graphql-getting-started",
    title: "Help: GraphQL API - Getting Started",
    subtitle: "Help article",
    url: "help:graphql-getting-started",
    adminOnly: true,
    keywords: ["graphql", "api", "authentication", "bearer", "endpoint", "curl"],
  },
  {
    id: "help-graphql-queries",
    title: "Help: GraphQL API - Queries Reference",
    subtitle: "Help article",
    url: "help:graphql-queries",
    adminOnly: true,
    keywords: ["graphql", "queries", "me", "notifications", "auditLogs", "users"],
  },
  {
    id: "help-graphql-mutations",
    title: "Help: GraphQL API - Mutations Reference",
    subtitle: "Help article",
    url: "help:graphql-mutations",
    adminOnly: true,
    keywords: ["graphql", "mutations", "updateProfile", "deleteNotifications"],
  },
  {
    id: "help-graphql-types",
    title: "Help: GraphQL API - Types & Inputs",
    subtitle: "Help article",
    url: "help:graphql-types",
    adminOnly: true,
    keywords: ["graphql", "types", "inputs", "enums", "scalars", "schema"],
  },
  {
    id: "help-graphql-errors",
    title: "Help: GraphQL API - Error Handling",
    subtitle: "Help article",
    url: "help:graphql-errors",
    adminOnly: true,
    keywords: ["graphql", "errors", "troubleshooting", "unauthenticated", "forbidden"],
  },
  {
    id: "help-graphql-rate-limiting",
    title: "Help: GraphQL API - Rate Limiting & Security",
    subtitle: "Help article",
    url: "help:graphql-rate-limiting",
    adminOnly: true,
    keywords: ["graphql", "rate", "limit", "security", "cors", "introspection"],
  },
  {
    id: "help-graphql-pagination",
    title: "Help: GraphQL API - Pagination",
    subtitle: "Help article",
    url: "help:graphql-pagination",
    adminOnly: true,
    keywords: ["graphql", "pagination", "paginator", "first", "page"],
  },
];

/**
 * Search static navigation pages by query. Filters by admin permission and
 * matches against title, subtitle, and keywords. Returns at most MAX_PAGE_RESULTS.
 */
export function searchPages(query: string, isAdmin: boolean): SearchPage[] {
  const q = query.toLowerCase().trim();
  if (!q) return [];

  return SEARCH_PAGES.filter((page) => {
    if (page.adminOnly && !isAdmin) return false;

    const searchText = [
      page.title,
      page.subtitle,
      ...(page.keywords ?? []),
    ]
      .filter(Boolean)
      .join(" ")
      .toLowerCase();

    return searchText.includes(q);
  }).slice(0, MAX_PAGE_RESULTS);
}
