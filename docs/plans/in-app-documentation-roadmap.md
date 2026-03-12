# In-App Documentation & Onboarding Roadmap

Comprehensive in-app help system including getting started wizard, contextual tooltips, help guides, and interactive tutorials to improve user experience and reduce support burden.

**Priority:** MEDIUM  
**Dependencies:** None  
**Estimated Effort:** Large

## Goals

1. Help new users get started quickly with a guided setup wizard
2. Provide contextual help throughout the application via tooltips
3. Create searchable help documentation accessible from within the app
4. Enable interactive tutorials for key workflows
5. Reduce friction for self-hosted deployments

---

## Phase 1: Getting Started Wizard ✅ COMPLETE

First-run experience that guides new users through essential setup steps.

### Task 1.1: Wizard Infrastructure
- [x] Create wizard modal/dialog component with step navigation
- [x] Add wizard state tracking (completed steps, skipped, dismissed)
- [x] Store wizard completion status per user in database
- [x] Add "Show wizard again" option in user settings
- [x] Create API endpoint to track wizard progress

### Task 1.2: Welcome Step
- [x] Display app name and logo
- [x] Brief introduction to the application's purpose
- [x] Option to skip wizard entirely

### Task 1.3: Profile Setup Step
- [x] Guide user to set display name and avatar
- [x] Explain profile settings location
- [x] Quick form or link to profile page

### Task 1.4: Security Setup Step (Optional)
- [x] Recommend enabling two-factor authentication
- [x] Explain benefits of 2FA
- [x] Link to security settings or inline setup

### Task 1.5: Notification Preferences Step
- [x] Explain notification channels available
- [x] Quick toggles for email notifications
- [x] Link to full notification preferences

### Task 1.6: Theme & Appearance Step
- [x] Show light/dark/system theme options
- [x] Preview theme selection
- [x] Save preference immediately

### Task 1.7: Quick Tour Step
- [x] Highlight key navigation areas (sidebar, header)
- [x] Point to configuration section (for admins)
- [x] Show where to find help/documentation

### Task 1.8: Completion Step
- [x] Congratulations message
- [x] Quick links to common actions
- [x] Option to view help documentation

---

## Phase 2: Contextual Tooltips ✅ COMPLETE

Help tooltips throughout the application for field-level guidance.

### Task 2.1: Tooltip Component
- [x] Create reusable `HelpTooltip` component
- [x] Support hover and click triggers
- [x] Support markdown content in tooltips
- [x] Accessible (keyboard navigable, screen reader friendly)
- [x] Consistent styling with design system

### Task 2.2: Tooltip Content Management
- [x] Create tooltip content storage (database or config file)
- [x] Support content keyed by page/field identifier
- [ ] Admin UI to edit tooltip content (optional, Phase 4+)
- [x] Fallback to hardcoded defaults

### Task 2.3: Settings Page Tooltips
- [x] Add tooltips to System Settings fields
- [ ] Add tooltips to Branding Settings fields
- [ ] Add tooltips to Email Configuration fields
- [x] Add tooltips to SSO Configuration fields
- [x] Add tooltips to AI/LLM Configuration fields
- [x] Add tooltips to Backup Configuration fields
- [ ] Add tooltips to Notification Settings fields

### Task 2.4: Form Field Tooltips
- [ ] Add tooltips to profile form fields
- [x] Add tooltips to security settings (2FA)
- [ ] Add tooltips to user preferences
- [ ] Add tooltips to admin user management forms

### Task 2.5: Complex Feature Tooltips
- [x] Add tooltips explaining LLM orchestration modes
- [x] Add tooltips for backup destinations
- [ ] Add tooltips for notification channels
- [ ] Add tooltips for email template variables

---

## Phase 3: Help Documentation Center ✅ COMPLETE

In-app documentation accessible from anywhere in the application.

### Task 3.1: Help Center Infrastructure
- [x] Create help center page/modal component
- [x] Add global help icon in header (opens help center)
- [x] Support keyboard shortcut to open help (e.g., `?` or `Ctrl+/`)
- [ ] Track help page views for analytics (optional)

### Task 3.2: Documentation Content System
- [x] Create documentation content structure (markdown files or database)
- [x] Support categories and subcategories
- [x] Support search across all help content
- [x] Support linking between help articles

### Task 3.3: Core Documentation Articles
- [x] **Getting Started** - Overview of the application
- [x] **Dashboard** - Understanding the dashboard (via navigation article)
- [x] **Profile & Account** - Managing your profile and account settings
- [x] **Security** - Password, 2FA, and account security
- [x] **Notifications** - Understanding and configuring notifications
- [x] **Theme & Appearance** - Customizing the look and feel

### Task 3.4: Admin Documentation Articles
- [x] **System Settings** - Configuring application-wide settings (admin overview)
- [x] **Branding** - Customizing logo, colors, and branding
- [x] **Email Configuration** - Setting up email delivery
- [x] **Email Templates** - Customizing email templates
- [x] **SSO Configuration** - Setting up single sign-on providers
- [x] **AI/LLM Settings** - Configuring AI providers
- [x] **User Management** - Managing users, roles, and permissions
- [x] **Backup & Restore** - Data backup and recovery
- [x] **Search Configuration** - Managing search indexes

### Task 3.5: Contextual Help Links
- [x] Add "Learn more" links on each settings page to relevant help article
- [ ] Add help links in error messages where appropriate (optional)
- [ ] Add help links in empty states (optional)

### Task 3.6: Help in Global Search
- [x] Add help articles to Cmd+K search (search-pages.php with help: URL prefix)
- [x] Intercept help: URLs in search modal to open help center

---

## Phase 4: Interactive Tutorials

Guided walkthroughs for complex features.

### Task 4.1: Tutorial Infrastructure
- [ ] Evaluate tutorial libraries (intro.js, react-joyride, shepherd.js, driver.js)
- [ ] Create tutorial component wrapper
- [ ] Store tutorial completion status per user
- [ ] Support pause/resume and skip functionality
- [ ] Support step-by-step progression with highlights

### Task 4.2: Core Tutorials
- [ ] **App Tour** - General navigation and layout walkthrough
- [ ] **Profile Setup** - Completing your profile
- [ ] **Enable 2FA** - Step-by-step 2FA setup
- [ ] **Configure Notifications** - Setting up notification preferences

### Task 4.3: Admin Tutorials
- [ ] **Configure Email** - Setting up email delivery (SMTP/provider)
- [ ] **Add SSO Provider** - Enabling single sign-on
- [ ] **Configure AI Provider** - Adding and testing an LLM provider
- [ ] **Create Backup** - Creating and scheduling backups
- [ ] **Customize Branding** - Setting up logo and colors
- [ ] **Manage Users** - User administration walkthrough

### Task 4.4: Tutorial Triggers
- [ ] Add "Take a tour" buttons on relevant pages
- [ ] Offer tutorial on first visit to complex pages
- [ ] Add tutorial links in help center
- [ ] Add "Show me how" buttons for complex actions

---

## Phase 5: Help Widget & Quick Actions

Floating help widget for quick access to assistance.

### Task 5.1: Help Widget Component
- [ ] Create floating help button (bottom-right corner)
- [ ] Expandable panel with quick options
- [ ] Context-aware suggestions based on current page
- [ ] Keyboard shortcut support

### Task 5.2: Quick Actions Menu
- [ ] Search documentation
- [ ] View relevant help article for current page
- [ ] Start tutorial for current feature
- [ ] Access keyboard shortcuts reference
- [ ] Link to full help center

### Task 5.3: Keyboard Shortcuts
- [ ] Document all keyboard shortcuts
- [ ] Create keyboard shortcuts reference modal
- [ ] Support `?` or `Ctrl+/` to show shortcuts
- [ ] Add shortcut hints in tooltips

---

## Phase 6: Admin Content Management (Optional)

Allow admins to customize help content.

### Task 6.1: Tooltip Admin UI
- [ ] List all tooltips with their content
- [ ] Edit tooltip content inline or in modal
- [ ] Preview tooltip appearance
- [ ] Reset to default content

### Task 6.2: Documentation Admin UI
- [ ] Markdown editor for help articles
- [ ] Article organization (categories, order)
- [ ] Publish/unpublish articles
- [ ] Version history (optional)

### Task 6.3: Wizard Customization
- [ ] Enable/disable wizard steps
- [ ] Customize wizard step content
- [ ] Add custom wizard steps (optional)

---

## Success Criteria

- New users can complete basic setup in < 5 minutes using the wizard
- Users can find answers to common questions without external documentation
- Tooltips provide contextual help for all non-obvious fields
- Complex features have guided tutorials
- Help is accessible from any page in the application
- Admin documentation covers all configuration options

---

## Implementation Notes

### Recommended Libraries

| Feature | Options | Recommendation |
|---------|---------|----------------|
| Tooltips | Radix UI Tooltip, Floating UI | Radix UI (already in project) |
| Tutorials | react-joyride, driver.js, intro.js | driver.js (lightweight, modern) |
| Documentation | MDX, Markdown + custom renderer | MDX (integrates with Next.js) |
| Search | Fuse.js (client-side), Meilisearch | Fuse.js (simple, no server needed) |

### Data Storage

**User Progress:**
```sql
-- wizard/tutorial completion tracking
CREATE TABLE user_onboarding (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL,
  wizard_completed_at TIMESTAMP NULL,
  wizard_dismissed_at TIMESTAMP NULL,
  tutorials_completed JSON DEFAULT '[]',
  help_preferences JSON DEFAULT '{}',
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

**Help Content:**
```sql
-- optional: database-stored help content for admin editing
CREATE TABLE help_articles (
  id INTEGER PRIMARY KEY,
  slug VARCHAR(255) UNIQUE NOT NULL,
  category VARCHAR(100) NOT NULL,
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  is_published BOOLEAN DEFAULT TRUE,
  sort_order INTEGER DEFAULT 0,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

### File Structure

```
frontend/
├── components/
│   ├── help/
│   │   ├── help-center.tsx       # Main help center modal/page
│   │   ├── help-widget.tsx       # Floating help button
│   │   ├── help-tooltip.tsx      # Reusable tooltip component
│   │   ├── help-search.tsx       # Documentation search
│   │   └── keyboard-shortcuts.tsx # Shortcuts reference
│   ├── onboarding/
│   │   ├── wizard.tsx            # Getting started wizard
│   │   ├── wizard-steps/         # Individual wizard step components
│   │   └── tutorial.tsx          # Tutorial wrapper component
├── lib/
│   ├── help-content.ts           # Help content definitions
│   └── onboarding.ts             # Onboarding state management
├── app/
│   └── (dashboard)/
│       └── help/
│           └── page.tsx          # Full help center page
backend/
├── app/
│   ├── Http/Controllers/Api/
│   │   └── OnboardingController.php
│   └── Models/
│       └── UserOnboarding.php
├── database/migrations/
│   └── xxxx_create_user_onboarding_table.php
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/onboarding/status` | Get user's onboarding status |
| POST | `/api/onboarding/wizard/complete` | Mark wizard as complete |
| POST | `/api/onboarding/wizard/dismiss` | Dismiss wizard |
| POST | `/api/onboarding/tutorial/{slug}/complete` | Mark tutorial complete |
| GET | `/api/help/articles` | List help articles |
| GET | `/api/help/articles/{slug}` | Get single article |
| GET | `/api/help/search` | Search help content |

---

## Related Roadmaps

- [Documentation Audit](documentation-audit-roadmap.md) - Ensuring external documentation is accurate
- [Branding & UI Consistency](branding-ui-consistency-roadmap.md) - Consistent visual styling

---

## Notes

- Start with Phase 1 (Wizard) and Phase 2 (Tooltips) for immediate impact
- Phase 3 (Help Center) can be parallel with Phases 1-2
- Phase 4 (Tutorials) builds on Phase 1-3 infrastructure
- Phase 5-6 are enhancements once core help system is established
- Consider user feedback to prioritize which tutorials/articles are most needed
- Tooltips should be concise (1-2 sentences max)
- Help articles can be more detailed with examples and screenshots

## Implementation Journal

- [In-App Documentation Completion](../journal/2026-02-05-in-app-docs-completion.md)
- [In-App Documentation & Onboarding (Phases 1-3)](../journal/2026-02-05-in-app-documentation-phases-1-3.md)
