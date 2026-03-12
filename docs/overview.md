# Sourdough Documentation Overview

**Main documentation hub for AI assistants.**

Sourdough is a starter application framework designed for AI as a starter to develop other applications. It provides enterprise-grade user management, multi-provider notification system, multi-LLM orchestration with council mode, and robust infrastructure - all in a single Docker container powered by Laravel 11 (PHP 8.3+) + Next.js 16 (React 18, TypeScript) with SQLite as the default database.

## Using Sourdough as a Template

Sourdough is designed to be forked and customized for your own projects:

- [FORK-ME.md](../FORK-ME.md) - Quick start guide for using Sourdough as a base
- [Customization Checklist](customization-checklist.md) - Step-by-step guide to customize for your project

## Documentation Index

### AI Development (Start Here)
- [AI Development Guide](ai/README.md) - **Start here** - context loading, workflows, patterns, recipes
- [Quick Reference](quick-reference.md) - Fast lookup: structure, commands, conventions, gotchas

### Architecture & Features
- [Architecture](architecture.md) - Architecture Decision Records (ADRs) with key file references
- [Compliance Templates](compliance/README.md) - SOC 2, ISO 27001, and security policy templates for customization
- [Features](features.md) - Core functionality (auth, notifications, LLM, backup)
- [Backup & Restore](backup.md) - **Backup hub**: user guide, admin settings, developer docs, key files, recipes, patterns
- [Upgrade Guide](user/README.md#upgrade-guide-ai-export) - AI-readable upgrade guide for forked codebases
- [Roadmaps & Plans](roadmaps.md) - Development roadmaps and journal entries

### Technical Reference
- [Development](development.md) - Dev setup, tooling, configuration
- [Docker Configuration](docker.md) - Container setup and configuration
- [API Reference](api-reference.md) - REST API documentation

### Other
- [User Documentation](user-docs.md) - End-user guides

---

*For user-facing documentation, see [README.md](../README.md)*
