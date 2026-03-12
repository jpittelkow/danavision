# Sourdough

**Ship faster. Skip the boring parts.**

A production-ready full-stack starter that eliminates repetitive infrastructure setup. Sourdough provides all the essential building blocks—authentication, notifications, AI integration, backups, search, audit logs, and admin configuration UI—so you can focus on building your unique features instead of reinventing the wheel.

🌐 **[getsourdough.app](https://getsourdough.app/)** · 📖 **[Documentation](docs/)** · 🐙 **[GitHub](https://github.com/Sourdough-start/sourdough)**

## What is Sourdough?

Sourdough is a complete Docker-containerized application framework combining **Laravel 11** (API) and **Next.js 16** (frontend) with pre-built, enterprise-grade features. Everything runs in a single Docker container and is managed through a polished dashboard—no config file edits required.

## Tech Stack

| Layer | Technologies |
|-------|-------------|
| **Backend** | Laravel 11 (PHP 8.3+), Sanctum auth, Scout search, Reverb WebSockets, Eloquent ORM, Queue system |
| **Frontend** | Next.js 16, React 18, TypeScript, Tailwind CSS, shadcn/ui, TanStack Query, Zustand, React Hook Form, Zod |
| **Database** | SQLite (default, zero-config) — swappable to MySQL, PostgreSQL, Supabase, or PlanetScale |
| **Search** | Meilisearch with database LIKE query fallback |
| **Infrastructure** | Single Docker container: Nginx, PHP-FPM, Next.js, Meilisearch, Supervisor |
| **Testing** | Pest (backend), Vitest + Playwright (frontend) |

## Key Features

### Authentication & Users
Complete auth system with email/password, SSO (Google, GitHub, Microsoft, Apple, Discord), TOTP two-factor authentication with recovery codes, WebAuthn/FIDO2 passkey support, and group-based permissions with granular controls.

### Multi-Channel Notifications
Email (SMTP/Mailgun/SES), SMS (Twilio/Vonage/SNS), Slack, Telegram, Discord, Signal, Matrix, ntfy, Web Push, and in-app delivery — all with per-user preferences.

### AI/LLM Integration
Support for Claude, OpenAI, Gemini, Ollama, AWS Bedrock, and Azure OpenAI. Three orchestration modes: single queries, synthesized aggregation, and consensus voting councils.

### Search & Audit
Meilisearch-powered full-text search with Cmd+K shortcuts. Real-time audit log streaming via Server-Sent Events with HIPAA-compliant access logging and suspicious activity detection.

### Backup & Restore
Automated full backups (database + files + settings) with scheduling, remote destinations (S3, SFTP, Google Drive, local), optional encryption, and configurable retention policies. See [Backup & Restore documentation](docs/backup.md) for details.

### Progressive Web App
Offline capability, install prompts, background sync, Service Worker via Workbox 7, and Web Push support with VAPID keys.

### File Storage
Amazon S3, Google Cloud Storage, Azure Blob Storage, and local filesystem with integrated file manager UI.

### Stripe Payments (Optional)
Stripe Connect integration with platform fees, destination charges, OAuth onboarding, and idempotent webhook handling.

### AI-Readable Upgrade Guide
Generate structured markdown upgrade guides between any two versions directly from the changelog page. Designed for AI agents to understand and apply upstream changes to forked codebases—includes version-by-version details, consolidated changes, database migration detection, and step-by-step instructions.

### Simple Deployment
Everything runs in one Docker container—Nginx, PHP-FPM, Next.js, Meilisearch, and Supervisor all start automatically. No orchestration required.

## Getting Started

```bash
# Clone the repository
git clone https://github.com/Sourdough-start/sourdough.git
cd sourdough

# Start the application
docker-compose up -d

# Access the application at http://localhost:8080
```

That's it! A fully running development environment with all services operational. An AI setup wizard guides you through configuration.

## AI-Powered Development

Sourdough is built to be agentic-friendly with deep AI documentation:

- **47 implementation recipes** for common tasks (features, config pages, notifications, dashboard widgets, API endpoints, and more)
- **Patterns & anti-patterns** documentation ensuring consistent code structure
- **IDE auto-configuration** for Cursor, GitHub Copilot, Windsurf, and Claude Code
- **26 Architecture Decision Records (ADRs)** documenting design reasoning

## System Requirements

- Docker
- 1 GB RAM (2+ GB recommended)
- 2 GB disk space (5+ GB recommended)
- 1 CPU core (2+ recommended)

## Documentation

- [User Guide](docs/user/) — Learn how to use Sourdough
- [Developer Guide](docs/dev/) — Technical documentation for developers
- [API Reference](docs/api/) — API endpoints and integration details
- [Backup & Restore](docs/backup.md) — Backup/restore user guide, admin settings, and developer docs
- [Upgrade Guide](docs/user/README.md#upgrade-guide-ai-export) — Generate AI-readable upgrade guides for forked codebases
- [Architecture Decisions](docs/architecture.md) — ADRs and design decisions
- [AI Development Guide](docs/ai/README.md) — Recipes, patterns, and workflow for AI-assisted development

## License

MIT License — see [LICENSE](LICENSE) for details.
