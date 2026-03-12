import { type LucideIcon, Book, Shield, Bell, Settings, User, Users, FileText, Brain, Database, BarChart3, Send, Terminal } from "lucide-react";

export interface HelpArticle {
  id: string;
  title: string;
  content: string;
  tags?: string[];
}

export interface HelpCategory {
  slug: string;
  name: string;
  icon?: LucideIcon;
  articles: HelpArticle[];
  /** Permission required to see this category. Omit for categories visible to all authenticated users. */
  permission?: string;
  /** Feature flag key from useAppConfig().features. Category is hidden when the flag is false. */
  featureFlag?: string;
}

// ---------------------------------------------------------------------------
// User-facing help categories (no permission required)
// ---------------------------------------------------------------------------
export const userHelpCategories: HelpCategory[] = [
  {
    slug: "getting-started",
    name: "Getting Started",
    icon: Book,
    articles: [
      {
        id: "welcome",
        title: "Welcome to Sourdough",
        tags: ["intro", "overview", "start"],
        content: `# Welcome to Sourdough

Sourdough is a modern application template designed for building secure, feature-rich web applications.

## Key Features

- **Secure Authentication** - Multiple sign-in options including email/password, passkeys, and SSO
- **Responsive Design** - Works great on desktop, tablet, and mobile devices
- **Progressive Web App** - Install as a standalone app on your device
- **Dark Mode** - Switch between light and dark themes

## Getting Help

If you need assistance:

1. Browse the help articles in this center
2. Use the search bar to find specific topics
3. Press **?** at any time to open this help center

Welcome aboard!`,
      },
      {
        id: "navigation",
        title: "Navigating the App",
        tags: ["menu", "sidebar", "navigate"],
        content: `# Navigating the App

## Main Navigation

The sidebar on the left provides quick access to all main sections:

- **Dashboard** - Your home page with key information
- **Search** - Find content across the application

## User Menu

Click your profile picture in the top-right corner to access:

- Profile settings
- Security options
- Theme preferences
- Sign out

## Keyboard Shortcuts

- **?** or **Ctrl+/** / **Cmd+/** - Open this help center
- **Ctrl+K** / **Cmd+K** - Open search
- **Esc** - Close dialogs`,
      },
      {
        id: "search",
        title: "Using Search",
        tags: ["search", "find", "filter"],
        content: `# Using Search

## Quick Search

Press **Ctrl+K** (or **Cmd+K** on Mac) to open the global search.

Type your query to search across:
- Pages and navigation
- Help articles
- Content

## Search Tips

- Use specific keywords for better results
- Search is case-insensitive
- Results update as you type

## Filtering Results

Search results are grouped by type. Click on a result to navigate directly to that item.`,
      },
      {
        id: "theme-appearance",
        title: "Theme & Appearance",
        tags: ["theme", "dark", "light", "appearance", "customization"],
        content: `# Theme & Appearance

Customize how the application looks to match your preferences.

## Theme Options

You can choose from three theme modes:

- **Light** - Bright interface for well-lit environments
- **Dark** - Dark interface that's easier on the eyes
- **System** - Automatically matches your device's theme (light or dark)

## Changing Your Theme

1. Click your profile picture in the top-right
2. Select **Preferences** or use the theme toggle in the header
3. Choose Light, Dark, or System

## Theme Persistence

Your theme preference is saved and will persist across sessions and devices.`,
      },
      {
        id: "timezone-settings",
        title: "Timezone Settings",
        tags: ["timezone", "time", "regional", "date", "time zone"],
        content: `# Timezone Settings

Control how dates and times are displayed throughout the application.

## How Timezone Is Set

Your timezone is **automatically detected** from your browser when you first log in or register. You can override it manually at any time.

## Changing Your Timezone

1. Click your profile picture in the top-right
2. Select **Preferences**
3. Find the **Regional** card
4. Choose your preferred timezone from the dropdown
5. Select "Use system default" to revert to automatic detection

## Timezone Fallback

If you haven't set a personal timezone, the app uses this fallback chain:

1. **Your personal setting** (set in Preferences)
2. **System default** (set by an administrator in System Settings)
3. **Server timezone** (UTC by default)

## Where Timezone Applies

Your timezone affects all dates and times shown in the app, including:

- Notification timestamps
- Audit log entries
- Backup timestamps
- Activity history`,
      },
      {
        id: "changelog",
        title: "Changelog & Version History",
        tags: ["changelog", "version", "release", "updates", "what's new"],
        content: `# Changelog & Version History

Stay up to date with the latest changes and improvements.

## Viewing the Changelog

1. Go to **Configuration** → **Changelog**
2. Browse entries grouped by version number
3. Each version shows its release date and categorized changes

## Entry Categories

Changes are organized into sections:

- **Added** - New features and capabilities
- **Changed** - Modifications to existing functionality
- **Fixed** - Bug fixes and corrections
- **Removed** - Features that have been removed
- **Security** - Security-related updates

## Older Versions

Older version entries are collapsible to keep the page clean. Click on a version header to expand its details.

## AI Export

Click the **AI Export** button to generate a structured markdown upgrade guide between two versions. This document is optimized for AI agents to understand and replicate changes in forked codebases. It includes version-by-version changes, consolidated summaries, database migration lists, and step-by-step instructions.

## Export Settings (Admin)

Admins can customize AI export output under the **changelog** settings group:

- **Format** — \`detailed\` (version-by-version + consolidated) or \`summary\` (consolidated only)
- **Detail Level** — \`full\` (includes migration detection) or \`changes-only\` (changelog entries only)
- **Instruction Style** — \`step-by-step\` (numbered guide), \`checklist\` (task list), or \`minimal\` (brief)`,
      },
    ],
  },
  {
    slug: "account",
    name: "Your Account",
    icon: User,
    articles: [
      {
        id: "profile",
        title: "Managing Your Profile",
        tags: ["profile", "account", "settings"],
        content: `# Managing Your Profile

## Updating Your Profile

1. Click your profile picture in the top-right
2. Select **Profile**
3. Update your information:
   - Display name
   - Email address
   - Profile picture

## Email Changes

When you change your email:
- A verification email is sent to the new address
- Your old email remains active until verification
- Click the link in the verification email to confirm

## Profile Picture

Upload a profile picture by:
1. Clicking the avatar area
2. Selecting an image file
3. Cropping if needed`,
      },
      {
        id: "password",
        title: "Changing Your Password",
        tags: ["password", "security", "change"],
        content: `# Changing Your Password

## How to Change Your Password

1. Go to **Profile** → **Security**
2. Click **Change Password**
3. Enter your current password
4. Enter and confirm your new password
5. Click **Save**

## Password Requirements

Your password must:
- Be at least 8 characters (configurable by admin)
- Not be a commonly used password
- Not be similar to your email

## Password Security Tips

- Use a unique password for this application
- Consider using a password manager
- Enable two-factor authentication for extra security`,
      },
    ],
  },
  {
    slug: "security",
    name: "Security",
    icon: Shield,
    articles: [
      {
        id: "two-factor",
        title: "Two-Factor Authentication",
        tags: ["2fa", "two-factor", "authenticator", "security"],
        content: `# Two-Factor Authentication

Two-factor authentication (2FA) adds an extra layer of security to your account.

## Setting Up 2FA

1. Go to **Profile** → **Security**
2. Find the **Two-Factor Authentication** section
3. Click **Enable**
4. Scan the QR code with your authenticator app
5. Enter the verification code
6. Save your recovery codes

## Supported Authenticator Apps

- Google Authenticator
- Authy
- Microsoft Authenticator
- 1Password
- Any TOTP-compatible app

## Recovery Codes

When enabling 2FA, you'll receive recovery codes. **Store these securely!**

If you lose access to your authenticator:
1. Use a recovery code to sign in
2. Each code can only be used once
3. Regenerate codes if running low`,
      },
      {
        id: "passkeys",
        title: "Using Passkeys",
        tags: ["passkey", "webauthn", "biometric", "fingerprint"],
        content: `# Using Passkeys

Passkeys are a more secure and convenient way to sign in without passwords.

## What are Passkeys?

Passkeys use biometrics (fingerprint, face) or device PIN to authenticate you. They're:

- **More secure** - Can't be phished or stolen
- **Easier to use** - No passwords to remember
- **Device-synced** - Work across your devices

## Setting Up a Passkey

1. Go to **Profile** → **Security**
2. Find the **Passkeys** section
3. Click **Add Passkey**
4. Follow your device's prompts
5. Name your passkey for identification

## Signing In with Passkeys

1. Click **Sign in with Passkey** on the login page
2. Use your fingerprint, face, or device PIN
3. You're signed in!

## Managing Passkeys

View and remove your passkeys from **Profile** → **Security**. We recommend having at least two passkeys registered.`,
      },
      {
        id: "sessions",
        title: "Managing Sessions",
        tags: ["session", "devices", "logout"],
        content: `# Managing Sessions

## Active Sessions

View all devices where you're signed in:

1. Go to **Profile** → **Security**
2. Scroll to **Active Sessions**
3. See device type, location, and last activity

## Signing Out Remotely

If you see an unfamiliar session:

1. Click **Revoke** next to the session
2. That device will be signed out immediately
3. Consider changing your password if suspicious

## Session Security

- Sessions expire after a period of inactivity
- Closing your browser doesn't always end the session
- Use **Sign out** when on shared devices`,
      },
    ],
  },
  {
    slug: "notifications",
    name: "Notifications",
    icon: Bell,
    articles: [
      {
        id: "notification-settings",
        title: "Notification Settings",
        tags: ["notifications", "alerts", "preferences"],
        content: `# Notification Settings

## Configuring Notifications

1. Go to **Profile** → **Notifications**
2. Choose your preferences for each notification type:
   - **In-app** - Notifications within the application
   - **Email** - Notifications sent to your email
   - **Push** - Browser/device push notifications

## Push Notifications

To receive push notifications:

1. Enable push notifications in settings
2. Allow notifications when prompted by your browser
3. Ensure browser notifications aren't blocked

## Mobile Push Notifications

To receive push notifications on your phone or tablet:

### Requirements
- **HTTPS** — The app must be served over HTTPS
- **Installed as PWA** — On mobile, install the app to your home screen
- **Permission granted** — Allow notifications when prompted
- **VAPID configured** — Your administrator must configure push notification keys

### iOS (iPhone / iPad)
- Requires **iOS 16.4 or later**
- You must add the app to your home screen via Safari's Share menu
- Push notifications only work when opened from the home screen icon
- iOS does not support badge counts or silent push notifications

### Android
- Install the app from Chrome's menu ("Add to Home Screen" or "Install App")
- Push notifications work in both the browser and the installed app
- Notifications appear in the system notification tray

### Troubleshooting
- If notifications stop working, try disabling and re-enabling them in Preferences
- Ensure your device has not blocked notifications for this site
- Check that your device is connected to the internet

## Notification Types

Different events may have different notification options:
- Security alerts (login, password changes)
- Account updates
- System announcements

Some critical security notifications cannot be disabled.`,
      },
    ],
  },
];

// ---------------------------------------------------------------------------
// Permission-gated help categories (shown based on user permissions)
// ---------------------------------------------------------------------------
export const permissionHelpCategories: HelpCategory[] = [
  // --- Administration (settings.view) ---
  {
    slug: "administration",
    name: "Administration",
    icon: Settings,
    permission: "settings.view",
    articles: [
      {
        id: "admin-overview",
        title: "Administration Overview",
        tags: ["admin", "settings", "configuration"],
        content: `# Administration Overview

As an administrator, you have access to system-wide settings and user management.

## Admin Areas

- **System Settings** - Configure application behavior
- **Security Settings** - Set security policies
- **User Management** - Manage user accounts
- **Audit Logs** - Review system activity

## Accessing Admin Settings

1. Click your profile in the top-right
2. Select **Configuration**
3. Navigate through the admin sections

## Best Practices

- Review audit logs regularly
- Keep security settings appropriately strict
- Test changes in a non-production environment first`,
      },
      {
        id: "branding",
        title: "Branding & Customization",
        tags: ["branding", "logo", "colors", "theme", "visual"],
        content: `# Branding & Customization

Customize the application's visual identity for your organization.

## Branding Options

- **Application Name** - The name displayed in the header and page titles
- **Logo** - Upload a custom logo for the sidebar and login page
- **Theme Palette** - Choose primary accent colors for buttons and links
- **Favicon** - Browser tab icon

## Configuring Branding

1. Go to **Configuration** → **Theme & Branding**
2. Upload your logo or enter the application name
3. Select your preferred color palette
4. Save your changes

## Logo Guidelines

- Use high-resolution images for best display
- Transparent backgrounds work well for logos
- Recommended aspect ratio: square or landscape`,
      },
    ],
  },
  // --- User Management (users.view) ---
  {
    slug: "user-management",
    name: "User Management",
    icon: Users,
    permission: "users.view",
    articles: [
      {
        id: "user-management",
        title: "User Management",
        tags: ["users", "admin", "accounts", "manage"],
        content: `# User Management

## Viewing Users

Go to **Configuration** → **Users** to see all registered users.

You can:
- Search and filter users
- View user details
- Modify user roles
- Disable or delete accounts

## User Roles

- **User** - Standard access
- **Admin** - Full administrative access

## Modifying Users

1. Find the user in the list
2. Click on their row
3. Make changes
4. Save

## Disabling Accounts

Disabled accounts:
- Cannot sign in
- Retain their data
- Can be re-enabled later`,
      },
    ],
  },
  // --- Groups (groups.view) ---
  {
    slug: "groups",
    name: "Groups & Permissions",
    icon: Users,
    permission: "groups.view",
    articles: [
      {
        id: "groups-management",
        title: "User Groups & Permissions",
        tags: ["groups", "permissions", "roles", "members", "access"],
        content: `# User Groups & Permissions

Organize users into groups and control what they can access.

## What are Groups?

Groups define sets of permissions. Users can belong to multiple groups, and their effective permissions are the union of all group permissions.

## Managing Groups

1. Go to **Configuration** → **Groups**
2. View existing groups and their members
3. Create new groups or edit existing ones

## Built-in Groups

- **Administrators** - Full access to all features and settings
- **Users** - Default group for standard users

## Permission Matrix

Each group has a permission matrix that controls access to:

- **Users** - View, create, edit, delete user accounts
- **Groups** - View and manage groups
- **Settings** - View and edit system settings
- **Backups** - View, create, restore, delete backups
- **Logs** - View and export logs, view audit logs
- **Usage** - View integration usage and costs

## Assigning Users to Groups

1. Go to **Configuration** → **Users**
2. Click on a user
3. Use the group picker to assign groups
4. Save changes`,
      },
    ],
  },
  // --- Security & Access (settings.view) ---
  {
    slug: "security-access",
    name: "Security & Access",
    icon: Shield,
    permission: "settings.view",
    articles: [
      {
        id: "security-settings",
        title: "Security Configuration",
        tags: ["security", "policy", "admin", "2fa", "password"],
        content: `# Security Configuration

## Authentication Settings

Configure how users authenticate:

- **Email Verification** - Require verified emails
- **Two-Factor Authentication** - Require/optional 2FA
- **Passkey Mode** - Passkey requirements

## Password Policy

Set password requirements:

- Minimum length
- Complexity requirements
- Maximum login attempts

## Session Settings

- **Session Timeout** - Auto-logout after inactivity
- **Concurrent Sessions** - Allow multiple devices

## SSO Configuration

If using Single Sign-On:

- Enable/disable providers
- Configure account linking
- Set auto-registration rules`,
      },
      {
        id: "sso-configuration",
        title: "SSO Configuration",
        tags: ["sso", "oauth", "google", "github", "single sign-on"],
        content: `# SSO Configuration

Enable Single Sign-On so users can sign in with external identity providers.

## Supported Providers

- **Google** - Sign in with Google
- **GitHub** - Sign in with GitHub
- **GitLab** - Sign in with GitLab
- **Microsoft/Azure AD** - Sign in with Microsoft account
- **Apple** - Sign in with Apple
- **Discord** - Sign in with Discord
- **OIDC** - Any OpenID Connect compatible provider

## Adding a Provider

1. Go to **Configuration** → **SSO**
2. Create an application in your provider's developer console
3. Copy the Client ID and Client Secret
4. Add the redirect URI shown in the configuration page
5. Paste credentials into the form
6. Use **Test Connection** to verify

## Account Linking

When **Allow Account Linking** is enabled, users with an existing email/password account can link their SSO provider. When disabled, SSO creates a new account for new users only.

## Auto-Registration

Enable **Auto-Register** to allow new users to create accounts via SSO without manual approval. Disable to require admin approval first.`,
      },
      {
        id: "api-webhooks",
        title: "Webhooks",
        tags: ["webhooks", "integration", "automation", "events"],
        content: `# Webhooks

Configure outgoing webhook endpoints for event-driven integrations.

## Setting Up Webhooks

Configure webhooks to notify external systems when events occur:

1. Go to **Configuration** → **Webhooks**
2. Click **Create Webhook**
3. Enter the endpoint URL and select events to subscribe to
4. Optionally set a secret for payload signature verification

## Available Webhook Events

- \`user.created\`, \`user.updated\`, \`user.deleted\`
- \`backup.completed\`, \`backup.failed\`
- \`settings.updated\`

## Webhook Security

When a secret is configured, each delivery includes an HMAC-SHA256 signature in the \`X-Webhook-Signature\` header for payload verification.

## API Keys

For programmatic access to the GraphQL API, manage your personal API keys in **User menu** → **Security** → **API Keys**.`,
      },
      {
        id: "api-keys",
        title: "API Keys",
        tags: ["api", "keys", "graphql", "programmatic", "integration", "bearer", "token"],
        content: `# API Keys

Manage personal API keys for programmatic access to the GraphQL API.

## What Are API Keys?

API keys let you access Sourdough programmatically via the GraphQL API. Each key is unique to you and acts as your identity for API requests. Keys must be kept secret — treat them like passwords.

> **Note:** The API Keys section is only visible when the GraphQL API is enabled by an administrator.

## Creating an API Key

1. Go to **User menu** → **Security** → **API Keys**
2. Click **Create API Key**
3. Enter a descriptive name (e.g., "My script", "CI pipeline")
4. Optionally set an expiration date
5. Click **Create** and **copy the key immediately** — it is only shown once

## Using an API Key

Include your key in the \`Authorization\` header of every API request:

\`\`\`
Authorization: Bearer sk_your_key_here
\`\`\`

## Key Prefix

The first 8 characters of your key (the prefix, e.g. \`sk_a1b2c3\`) are stored for display purposes so you can identify keys in logs and this list without exposing the full key.

## Rotating a Key

If you suspect a key has been compromised, rotate it:

1. Click **Rotate** next to the key
2. Confirm — the old key remains valid for a grace period
3. Copy the new key immediately
4. Update your applications to use the new key

The old key is automatically revoked after the grace period.

## Revoking a Key

To immediately revoke a key, click **Revoke** and confirm. The key becomes invalid instantly.

## Security Best Practices

- Never commit API keys to version control
- Set expiration dates when possible
- Use a separate key per application or script
- Rotate keys regularly
- Revoke unused keys promptly
- Monitor API usage in audit logs`,
      },
    ],
  },
  // --- Communications (settings.view) ---
  {
    slug: "communications",
    name: "Communications",
    icon: Bell,
    permission: "settings.view",
    articles: [
      {
        id: "email-configuration",
        title: "Email Configuration",
        tags: ["email", "smtp", "mailgun", "sendgrid", "delivery"],
        content: `# Email Configuration

Configure how the application sends email for notifications, password resets, and verification.

## Supported Drivers

- **SMTP** - Standard SMTP server (Gmail, Office 365, custom)
- **Mailgun** - Transactional email service
- **SendGrid** - Transactional email service
- **AWS SES** - Amazon Simple Email Service
- **Postmark** - Transactional email service

## Setup Steps

1. Go to **Configuration** → **Email**
2. Select your mail driver
3. Enter the required credentials (host, port, username, password)
4. Configure TLS/SSL encryption settings
5. Set the "From" address and name
6. Use **Test Connection** to verify

## Troubleshooting

- Ensure firewall allows outbound SMTP (port 25, 465, or 587)
- For Gmail, use an App Password, not your regular password
- Check that "From" address is verified with your provider`,
      },
      {
        id: "email-templates",
        title: "Email Templates",
        tags: ["email", "templates", "variables", "customize"],
        content: `# Email Templates

Customize the content of system emails sent to users.

## Available Templates

- Password reset
- Email verification
- Welcome email
- Notification digests

## Editing Templates

1. Go to **Configuration** → **Communications** → **Templates** tab
2. Select the template to edit
3. Modify the subject line and body
4. Use the variable picker to insert dynamic content (e.g., user name, reset link)
5. Preview before saving

## Template Variables

Each template supports variables that are replaced at send time:
- **{{user.name}}** - User's display name
- **{{user.email}}** - User's email address
- **{{reset_link}}** - Password reset URL (password reset template)
- **{{verification_link}}** - Email verification URL

Available variables are shown in the variable picker for each template.`,
      },
      {
        id: "notification-channels",
        title: "Notification Channels",
        tags: ["notifications", "channels", "telegram", "discord", "slack", "sms", "push", "admin"],
        content: `# Notification Channels

Configure which notification channels are available to users.

## Available Channels

- **Email** - Notifications via email (requires email configuration)
- **Telegram** - Notifications via Telegram bot
- **Discord** - Notifications via Discord webhook
- **Slack** - Notifications via Slack webhook
- **SMS** - SMS via Twilio, Vonage, or AWS SNS
- **Signal** - Notifications via Signal (signal-cli)
- **Matrix** - Notifications via Matrix homeserver
- **ntfy** - Push notifications via ntfy service
- **Web Push** - Browser push notifications (VAPID)
- **FCM** - Firebase Cloud Messaging for mobile push
- **In-App** - In-application notification bell

## Enabling Channels

1. Go to **Configuration** → **Notifications**
2. Enable the channels you want to make available
3. Enter the required credentials for each channel (API keys, webhook URLs, etc.)
4. Use **Test** to verify the channel works

## User vs Admin Configuration

Admins enable which channels are available system-wide. Users then enable their preferred channels and enter personal details (phone number, webhook URLs) in **User Preferences**.`,
      },
      {
        id: "notification-templates",
        title: "Notification Templates",
        tags: ["notification", "templates", "push", "inapp", "chat", "email", "customize"],
        content: `# Notification Templates

Customize the content of push, in-app, chat, and email notifications.

## Template Types

Each notification type (e.g., backup completed, login alert) has templates for four channel groups:

- **Push** - Web Push, FCM, ntfy
- **In-App** - Database channel (notification bell)
- **Chat** - Telegram, Discord, Slack, SMS, Matrix
- **Email** - Per-type email content (subject and HTML body)

## Editing Templates

1. Go to **Configuration** → **Communications** → **Templates** tab
2. Select the notification type
3. Use the **channel tabs** to switch between Push, In-App, Chat, and Email
4. Edit the title/subject and body for each channel group
5. Email templates use a rich text editor; other channels use plain text
6. Use the **Available Variables** panel to insert dynamic content
7. Preview before saving

## Template Variables

Variables use double-brace syntax: \`{{variable}}\`. Each template type shows its available variables in a collapsible reference panel.

## Resetting Templates

Click **Reset to Default** to restore the original system template content.`,
      },
      {
        id: "novu-configuration",
        title: "Novu Configuration",
        tags: ["novu", "notification", "infrastructure", "workflows"],
        content: `# Novu Configuration

Optionally use Novu for advanced notification workflows and a rich notification inbox.

## What is Novu?

Novu is a notification infrastructure platform that provides workflow orchestration, a notification inbox UI, and multi-channel delivery.

## Enabling Novu

1. Go to **Configuration** → **Communications** → **Novu** tab
2. Enter your Novu API Key and Application Identifier
3. Enable Novu integration
4. Save settings

## When Novu is Enabled

- The header notification bell uses the Novu Inbox component
- Notifications are routed through Novu's API
- Users are synced as Novu subscribers

## When Novu is Disabled

The built-in notification system and templates are used instead. You can switch between modes at any time.`,
      },
    ],
  },
  // --- Integrations (settings.view) ---
  {
    slug: "integrations",
    name: "Integrations",
    icon: Brain,
    permission: "settings.view",
    articles: [
      {
        id: "ai-llm-settings",
        title: "AI / LLM Settings",
        tags: ["ai", "llm", "openai", "anthropic", "models", "providers"],
        content: `# AI / LLM Settings

Configure AI providers and large language models for features that use AI.

## Supported Providers

- **OpenAI** - GPT-4, GPT-3.5
- **Anthropic** - Claude models
- **Google** - Gemini
- **AWS Bedrock** - Various models via Amazon
- **Azure OpenAI** - OpenAI models via Azure
- **Ollama** - Local models

## Adding a Provider

1. Go to **Configuration** → **AI / LLM**
2. Click **Add Provider**
3. Select the provider type
4. Enter your API key (stored securely)
5. Use **Test Key** to verify
6. Optionally use **Fetch Models** to discover available models

## Orchestration Modes

- **Single** - Use one primary provider
- **Aggregation** - Combine responses from multiple providers
- **Council** - Multiple providers vote on responses (for reliability)

## Model Selection

Select a default model per provider. Models with "Fetch Models" can discover available models from the provider's API.`,
      },
      {
        id: "storage-settings",
        title: "Storage Configuration",
        tags: ["storage", "s3", "disk", "upload", "files", "provider"],
        content: `# Storage Configuration

Configure where files are stored and manage storage settings.

## Supported Drivers

- **Local** - Files stored on the server's filesystem
- **Amazon S3** - AWS S3 or compatible services
- **Google Cloud Storage** - GCS with service account
- **Azure Blob Storage** - Azure container storage
- **DigitalOcean Spaces** - S3-compatible object storage
- **MinIO** - Self-hosted S3-compatible storage
- **Backblaze B2** - Affordable cloud storage

## Configuration Steps

1. Go to **Configuration** → **Storage**
2. Select your storage driver
3. Enter the required credentials (bucket, region, keys)
4. Set maximum upload size and allowed file types
5. Use **Test Connection** to verify (non-local drivers)

## Storage Health

The storage page shows:

- **Disk usage** - Current usage and available space
- **Storage paths** - Where different types of files are stored
- **Health status** - Warnings when disk usage is high or storage is not writable

## File Manager

Access **Manage Files** to browse, upload, download, and delete files directly.`,
      },
      {
        id: "search-config",
        title: "Search Configuration",
        tags: ["search", "meilisearch", "indexing", "admin"],
        content: `# Search Configuration

## How Search Works

The application uses Meilisearch for fast, typo-tolerant search.

## Reindexing

If search results seem outdated:

1. Go to **Configuration** → **Search**
2. View index statistics and document counts
3. Click **Reindex** for a specific model or **Reindex All**
4. Wait for completion

## Search Health

Monitor search status:

- **Connected** - Search is working
- **Disconnected** - Check Meilisearch service
- **Indexing** - Rebuild in progress

## Troubleshooting

If search isn't working:

1. Check Meilisearch service status
2. Verify configuration in environment
3. Try rebuilding the index
4. Check application logs for errors`,
      },
      {
        id: "stripe-configuration",
        title: "Stripe Configuration",
        tags: ["stripe", "payments", "billing", "api keys"],
        content: `# Stripe Configuration

Configure Stripe for payment processing.

## Prerequisites

You need a Stripe account. Sign up at stripe.com if you don't have one.

## Setup Steps

1. Go to **Configuration** → **Stripe**
2. Enter your API keys (Secret Key, Publishable Key)
3. Set the Webhook Secret
4. Use **Test Connection** to verify
5. Set the currency code (default: usd)

## Modes

- **Test Mode** — Use Stripe test keys for development
- **Live Mode** — Use live keys for real payments

## Webhooks

Set up a webhook endpoint in your Stripe dashboard pointing to \`https://your-domain/api/stripe/webhook\`. The app handles payment succeeded, failed, and refund events automatically.`,
      },
      {
        id: "graphql-configuration",
        title: "GraphQL API Configuration",
        tags: ["graphql", "api", "admin", "settings", "keys", "usage", "introspection"],
        content: `# GraphQL API Configuration

Manage the GraphQL API module settings, view all API keys, and monitor usage.

## Enabling GraphQL

Toggle the **Enable GraphQL API** switch to activate the GraphQL API endpoint. When disabled, all GraphQL routes return 404 and the API Keys section in User Security is hidden. No environment variable is required — this is a database-only toggle.

## Settings

- **Max API keys per user** — Limits how many active keys each user can create (default: 5)
- **Default rate limit** — Requests per minute allowed per key (default: 60)
- **Allow introspection** — Enables schema exploration for developer tools. Disable in production for security
- **Max query depth** — Maximum nesting depth for GraphQL queries (default: 12)
- **Max query complexity** — Maximum complexity score for a single query (default: 200)
- **Max result size** — Maximum items returned per paginated query (default: 100)
- **Key rotation grace period** — Days the old key remains valid after rotation (default: 7)
- **CORS allowed origins** — Comma-separated origins allowed to make cross-origin requests, or * for any

## API Key Management

The API Keys section shows all keys across all users. You can filter by user, status, or expiration, and revoke any key with confirmation. Requires the **api_keys.manage** permission.

## Usage Stats

View total API requests over 7 and 30 days, daily request trends, top users by request count, and top query names.`,
      },
      {
        id: "payment-history",
        title: "Payment History",
        tags: ["payments", "transactions", "billing", "history", "refunds"],
        content: `# Payment History

View and manage payment transactions.

## Viewing Payments

1. Go to **Configuration** → **Payment History**
2. Browse the paginated list of payments

## Payment Details

Each payment shows:

- **Date** — When the payment was processed
- **Description** — What the payment was for
- **Amount** — Payment amount (converted from cents to dollars)
- **Status** — Current status (succeeded, failed, refunded)

## Admin View

Administrators can toggle between:

- **My Payments** — Only your payments
- **All Payments** — All payments across all users (includes fee and user columns)

## Payment Tracking

Payment events are tracked in the **Usage & Costs** dashboard for cost visibility.`,
      },
    ],
  },
  // --- GraphQL API Documentation ---
  {
    slug: "graphql-api",
    name: "GraphQL API",
    icon: Terminal,
    permission: "settings.view",
    featureFlag: "graphqlEnabled",
    articles: [
      {
        id: "graphql-getting-started",
        title: "Getting Started",
        tags: ["graphql", "api", "authentication", "bearer", "endpoint", "quickstart", "curl"],
        content: `# Getting Started with the GraphQL API

## Endpoint

All GraphQL requests are sent as \`POST\` requests to:

\`\`\`
POST /api/graphql
\`\`\`

## Authentication

Every request must include a valid API key in the \`Authorization\` header:

\`\`\`
Authorization: Bearer sk_your_api_key_here
\`\`\`

API keys are created in **User Menu** > **Security** > **API Keys**. Each key uses the \`sk_\` prefix and acts as your identity for all API requests. Treat keys like passwords — never share them or commit them to source control.

## Your First Request

\`\`\`graphql
query {
  me {
    id
    name
    email
    isAdmin
    createdAt
  }
}
\`\`\`

### cURL Example

\`\`\`bash
curl -X POST https://your-app.com/api/graphql \\
  -H "Content-Type: application/json" \\
  -H "Authorization: Bearer sk_your_key" \\
  -d '{"query": "{ me { id name email } }"}'
\`\`\`

### JavaScript Example

\`\`\`javascript
const response = await fetch("/api/graphql", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    "Authorization": "Bearer sk_your_key",
  },
  body: JSON.stringify({
    query: \`{ me { id name email } }\`,
  }),
});
const { data, errors } = await response.json();
\`\`\`

## Response Format

All responses are JSON with a \`data\` field (on success) and/or an \`errors\` array (on failure):

\`\`\`json
{
  "data": {
    "me": {
      "id": "1",
      "name": "Jane Doe",
      "email": "jane@example.com"
    }
  }
}
\`\`\`

See the **Error Handling** article for error response details.

## Variables

Pass variables separately from the query for cleaner, reusable requests:

\`\`\`bash
curl -X POST https://your-app.com/api/graphql \\
  -H "Content-Type: application/json" \\
  -H "Authorization: Bearer sk_your_key" \\
  -d '{
    "query": "query ($unreadOnly: Boolean) { myNotifications(unreadOnly: $unreadOnly) { data { id title } paginatorInfo { total } } }",
    "variables": { "unreadOnly": true }
  }'
\`\`\`

## Next Steps

- **Queries Reference** — All available read operations
- **Mutations Reference** — All available write operations
- **Types & Inputs Reference** — Complete type definitions
- **Pagination** — How to paginate results
- **Rate Limiting & Security** — Limits and security configuration`,
      },
      {
        id: "graphql-queries",
        title: "Queries Reference",
        tags: ["graphql", "queries", "read", "me", "notifications", "audit", "users", "logs"],
        content: `# Queries Reference

All queries require a valid API key via \`Authorization: Bearer sk_...\`. Admin queries additionally require the listed permission.

## User Queries

These queries operate on the authenticated user's own data.

### me

Get the currently authenticated user's profile.

**Returns:** \`User!\`

\`\`\`graphql
query {
  me {
    id
    name
    email
    avatar
    emailVerifiedAt
    twoFactorEnabled
    isAdmin
    createdAt
    updatedAt
  }
}
\`\`\`

### myNotifications

Get the authenticated user's notifications with optional filtering and pagination.

| Argument | Type | Default | Description |
|---|---|---|---|
| category | String | null | Filter by notification category (e.g. "backup", "auth", "system") |
| unreadOnly | Boolean | null | Only return unread notifications |
| first | Int | 25 | Results per page |
| page | Int | 1 | Page number |

**Returns:** \`NotificationPaginator!\`

\`\`\`graphql
query {
  myNotifications(unreadOnly: true, first: 10, page: 1) {
    data {
      id
      type
      title
      message
      data
      readAt
      createdAt
    }
    paginatorInfo {
      currentPage
      lastPage
      total
      hasMorePages
    }
  }
}
\`\`\`

### myApiKeys

Get the authenticated user's API keys.

**Returns:** \`[ApiKey!]!\`

\`\`\`graphql
query {
  myApiKeys {
    id
    name
    keyPrefix
    lastUsedAt
    expiresAt
    revokedAt
    status
    createdAt
  }
}
\`\`\`

### myNotificationSettings

Get the authenticated user's notification channel settings.

**Returns:** \`NotificationSettings!\`

\`\`\`graphql
query {
  myNotificationSettings {
    channels {
      id
      name
      enabled
      configured
    }
    typePreferences
  }
}
\`\`\`

## Admin Queries

These queries require specific permissions and return data across all users.

### auditLogs

List audit logs. **Requires:** \`audit.view\` permission.

| Argument | Type | Default | Description |
|---|---|---|---|
| first | Int | 25 | Results per page |
| page | Int | 1 | Page number |
| filters | AuditLogFilter | null | Filter criteria |
| orderBy | [AuditLogOrderBy!] | null | Sort order |

**Returns:** \`AuditLogPaginator!\`

\`\`\`graphql
query {
  auditLogs(
    first: 10
    filters: { severity: "warning", dateFrom: "2026-01-01" }
    orderBy: [{ column: CREATED_AT, direction: DESC }]
  ) {
    data {
      id
      userId
      user { name email }
      action
      severity
      oldValues
      newValues
      ipAddress
      createdAt
    }
    paginatorInfo { total currentPage lastPage }
  }
}
\`\`\`

### accessLogs

List access logs (HIPAA). **Requires:** \`audit.view\` permission.

| Argument | Type | Default | Description |
|---|---|---|---|
| first | Int | 25 | Results per page |
| page | Int | 1 | Page number |
| filters | AccessLogFilter | null | Filter criteria |
| orderBy | [AccessLogOrderBy!] | null | Sort order |

**Returns:** \`AccessLogPaginator!\`

\`\`\`graphql
query {
  accessLogs(first: 25, page: 1) {
    data {
      id
      userId
      user { name email }
      action
      resourceType
      resourceId
      fieldsAccessed
      ipAddress
      createdAt
    }
    paginatorInfo { total currentPage lastPage }
  }
}
\`\`\`

### notificationDeliveries

List notification delivery attempts. **Requires:** \`notification_deliveries.view\` permission.

| Argument | Type | Default | Description |
|---|---|---|---|
| first | Int | 25 | Results per page |
| page | Int | 1 | Page number |
| filters | NotificationDeliveryFilter | null | Filter criteria |
| orderBy | [NotificationDeliveryOrderBy!] | null | Sort order |

**Returns:** \`NotificationDeliveryPaginator!\`

\`\`\`graphql
query {
  notificationDeliveries(
    filters: { status: "failed", channel: "email" }
    orderBy: [{ column: CREATED_AT, direction: DESC }]
  ) {
    data {
      id
      userId
      user { name }
      notificationType
      channel
      status
      error
      attempt
      attemptedAt
      createdAt
    }
    paginatorInfo { total currentPage lastPage }
  }
}
\`\`\`

### payments

List payments. **Requires:** \`payments.view\` permission.

| Argument | Type | Default | Description |
|---|---|---|---|
| first | Int | 25 | Results per page |
| page | Int | 1 | Page number |
| filters | PaymentFilter | null | Filter criteria |
| orderBy | [PaymentOrderBy!] | null | Sort order |

**Returns:** \`PaymentPaginator!\`

\`\`\`graphql
query {
  payments(
    first: 50
    filters: { status: "succeeded" }
    orderBy: [{ column: CREATED_AT, direction: DESC }]
  ) {
    data {
      id
      userId
      user { name email }
      amount
      currency
      status
      description
      paidAt
      createdAt
    }
    paginatorInfo { total currentPage lastPage }
  }
}
\`\`\`

### usageStats

Get integration usage statistics. **Requires:** \`usage.view\` permission.

| Argument | Type | Default | Description |
|---|---|---|---|
| dateFrom | Date | null | Start date (Y-m-d) |
| dateTo | Date | null | End date (Y-m-d) |
| integration | String | null | Filter by integration name |
| first | Int | 25 | Results per page |
| page | Int | 1 | Page number |

**Returns:** \`UsageStatPaginator!\`

\`\`\`graphql
query {
  usageStats(dateFrom: "2026-01-01", dateTo: "2026-01-31", integration: "openai") {
    data {
      id
      integration
      provider
      metric
      quantity
      estimatedCost
      metadata
      userId
      createdAt
    }
    paginatorInfo { total currentPage lastPage }
  }
}
\`\`\`

### usageBreakdown

Get usage grouped by integration. **Requires:** \`usage.view\` permission.

| Argument | Type | Default | Description |
|---|---|---|---|
| dateFrom | Date | null | Start date (Y-m-d) |
| dateTo | Date | null | End date (Y-m-d) |
| integration | String | null | Filter by integration name |

**Returns:** \`[UsageBreakdownEntry!]!\`

\`\`\`graphql
query {
  usageBreakdown(dateFrom: "2026-01-01", dateTo: "2026-01-31") {
    integration
    provider
    totalQuantity
    totalCost
    count
  }
}
\`\`\`

### userGroups

List all user groups. **Requires:** \`groups.view\` permission.

**Returns:** \`[UserGroup!]!\`

\`\`\`graphql
query {
  userGroups {
    id
    name
    slug
    description
    isSystem
    isDefault
    memberCount
    permissions
    createdAt
  }
}
\`\`\`

### users

List all users (admin). **Requires:** \`users.view\` permission.

| Argument | Type | Default | Description |
|---|---|---|---|
| first | Int | 25 | Results per page |
| page | Int | 1 | Page number |
| search | String | null | Search by name or email |

**Returns:** \`UserAdminPaginator!\`

\`\`\`graphql
query {
  users(first: 10, search: "jane") {
    data {
      id
      name
      email
      isAdmin
      disabledAt
      createdAt
    }
    paginatorInfo { total currentPage lastPage }
  }
}
\`\`\``,
      },
      {
        id: "graphql-mutations",
        title: "Mutations Reference",
        tags: ["graphql", "mutations", "write", "update", "profile", "notifications", "delete"],
        content: `# Mutations Reference

All mutations require a valid API key via \`Authorization: Bearer sk_...\`.

### updateProfile

Update the authenticated user's profile.

**Input:** \`UpdateProfileInput!\`

| Field | Type | Description |
|---|---|---|
| name | String | New display name |
| email | String | New email address (triggers verification) |
| avatar | String | New avatar URL |

**Returns:** \`UpdateProfilePayload!\` — contains \`user\` (updated User) and \`emailVerificationSent\` (Boolean).

\`\`\`graphql
mutation {
  updateProfile(input: { name: "Jane Smith", email: "jane.smith@example.com" }) {
    user {
      id
      name
      email
    }
    emailVerificationSent
  }
}
\`\`\`

### markNotificationAsRead

Mark a single notification as read.

| Argument | Type | Description |
|---|---|---|
| id | ID! | Notification ID |

**Returns:** \`Notification!\`

\`\`\`graphql
mutation {
  markNotificationAsRead(id: "42") {
    id
    title
    readAt
  }
}
\`\`\`

### deleteNotifications

Delete one or more notifications.

| Argument | Type | Description |
|---|---|---|
| ids | [ID!]! | Array of notification IDs to delete |

**Returns:** \`DeleteNotificationsPayload!\` — contains \`deletedCount\` (Int).

\`\`\`graphql
mutation {
  deleteNotifications(ids: ["42", "43", "44"]) {
    deletedCount
  }
}
\`\`\`

### updateNotificationSettings

Update the authenticated user's notification channel settings.

**Input:** \`NotificationSettingsInput!\`

| Field | Type | Description |
|---|---|---|
| channel | String! | Channel identifier (e.g. "email", "push") |
| enabled | Boolean | Enable or disable the channel |
| settings | JSON | Channel-specific settings |

**Returns:** \`NotificationSettings!\`

\`\`\`graphql
mutation {
  updateNotificationSettings(input: { channel: "email", enabled: true }) {
    channels {
      id
      name
      enabled
      configured
    }
    typePreferences
  }
}
\`\`\`

### updateTypePreferences

Update per-type notification channel preferences.

**Input:** \`TypePreferencesInput!\`

| Field | Type | Description |
|---|---|---|
| type | String! | Notification type identifier |
| channel | String! | Channel identifier |
| enabled | Boolean! | Enable or disable this type on this channel |

**Returns:** \`TypePreferencesPayload!\` — contains \`preferences\` (JSON).

\`\`\`graphql
mutation {
  updateTypePreferences(input: { type: "backup.completed", channel: "email", enabled: false }) {
    preferences
  }
}
\`\`\``,
      },
      {
        id: "graphql-types",
        title: "Types & Inputs Reference",
        tags: ["graphql", "types", "inputs", "enums", "scalars", "schema", "reference"],
        content: `# Types & Inputs Reference

## Scalars

| Scalar | Format | Example |
|---|---|---|
| DateTime | ISO 8601 with timezone | \`2026-01-15T10:30:00+00:00\` |
| Date | Y-m-d | \`2026-01-15\` |
| JSON | Arbitrary JSON data | \`{"key": "value"}\` |
| ID | Unique identifier | \`"1"\` |

## User-Facing Types

### User

| Field | Type | Description |
|---|---|---|
| id | ID! | Unique identifier |
| name | String! | Display name |
| email | String! | Email address |
| avatar | String | Avatar URL |
| emailVerifiedAt | DateTime | When email was verified |
| twoFactorEnabled | Boolean! | Whether 2FA is active |
| isAdmin | Boolean! | Whether user is an administrator |
| createdAt | DateTime! | Account creation time |
| updatedAt | DateTime! | Last update time |

### Notification

| Field | Type | Description |
|---|---|---|
| id | ID! | Unique identifier |
| type | String! | Notification type (e.g. "backup.completed") |
| title | String! | Notification title |
| message | String | Notification body |
| data | JSON | Additional structured data |
| readAt | DateTime | When marked as read (null if unread) |
| createdAt | DateTime! | When the notification was created |

### ApiKey

| Field | Type | Description |
|---|---|---|
| id | ID! | Unique identifier |
| name | String! | Key name/label |
| keyPrefix | String! | First characters of the key (e.g. "sk_a1b2") |
| lastUsedAt | DateTime | Last time this key was used |
| expiresAt | DateTime | Expiration date (null = never expires) |
| revokedAt | DateTime | When the key was revoked (null if active) |
| status | String! | Current status: "active", "expired", or "revoked" |
| createdAt | DateTime! | When the key was created |

### NotificationSettings

| Field | Type | Description |
|---|---|---|
| channels | [NotificationChannel!]! | List of notification channels |
| typePreferences | JSON! | Per-type channel preferences |

### NotificationChannel

| Field | Type | Description |
|---|---|---|
| id | String! | Channel identifier (e.g. "email") |
| name | String! | Display name |
| enabled | Boolean! | Whether the channel is enabled |
| configured | Boolean! | Whether the channel is configured |

## Admin Types

### AuditLog

| Field | Type | Description |
|---|---|---|
| id | ID! | Unique identifier |
| userId | ID | User who performed the action |
| user | UserAdmin | User details |
| action | String! | Action name (e.g. "user.updated") |
| severity | String! | Severity level |
| auditableType | String | Audited entity type |
| auditableId | String | Audited entity ID |
| oldValues | JSON | Previous values |
| newValues | JSON | New values |
| ipAddress | String | Request IP address |
| userAgent | String | Request user agent |
| correlationId | String | Request correlation ID |
| createdAt | DateTime! | When the event occurred |

### AccessLog

| Field | Type | Description |
|---|---|---|
| id | ID! | Unique identifier |
| userId | ID | User who accessed the resource |
| user | UserAdmin | User details |
| action | String! | Access action |
| resourceType | String | Type of resource accessed |
| resourceId | String | ID of resource accessed |
| fieldsAccessed | JSON | Fields that were accessed |
| ipAddress | String | Request IP address |
| userAgent | String | Request user agent |
| correlationId | String | Correlation ID |
| createdAt | DateTime! | When the access occurred |

### NotificationDelivery

| Field | Type | Description |
|---|---|---|
| id | ID! | Unique identifier |
| userId | ID | Target user |
| user | UserAdmin | User details |
| notificationType | String! | Notification type |
| channel | String! | Delivery channel |
| status | String! | Delivery status |
| error | String | Error message (if failed) |
| attempt | Int | Attempt number |
| attemptedAt | DateTime | When delivery was attempted |
| createdAt | DateTime! | Record creation time |

### Payment

| Field | Type | Description |
|---|---|---|
| id | ID! | Unique identifier |
| userId | ID | Paying user |
| user | UserAdmin | User details |
| stripeCustomerId | String | Stripe customer ID (requires \`payments.manage\` permission; returns \`null\` otherwise) |
| stripePaymentIntentId | String | Stripe PaymentIntent ID |
| amount | Int! | Amount in cents |
| currency | String! | Currency code (e.g. "usd") |
| status | String! | Payment status |
| description | String | Payment description |
| metadata | JSON | Additional metadata |
| applicationFeeAmount | Int | Platform fee in cents |
| paidAt | DateTime | When payment was completed |
| refundedAt | DateTime | When payment was refunded |
| createdAt | DateTime! | Record creation time |

### UsageStat

| Field | Type | Description |
|---|---|---|
| id | ID! | Unique identifier |
| integration | String! | Integration name |
| provider | String! | Provider name |
| metric | String! | Metric name |
| quantity | Float! | Usage quantity |
| estimatedCost | Float | Estimated cost |
| metadata | JSON | Additional metadata |
| userId | ID | Associated user |
| createdAt | DateTime! | Record creation time |

### UsageBreakdownEntry

| Field | Type | Description |
|---|---|---|
| integration | String! | Integration name |
| provider | String! | Provider name |
| totalQuantity | Float! | Total usage |
| totalCost | Float! | Total cost |
| count | Int! | Number of records |

### UserGroup

| Field | Type | Description |
|---|---|---|
| id | ID! | Unique identifier |
| name | String! | Group name |
| slug | String! | URL-safe slug |
| description | String | Group description |
| isSystem | Boolean! | Whether this is a system group |
| isDefault | Boolean! | Whether this is the default group |
| memberCount | Int! | Number of members |
| permissions | [String!]! | Assigned permissions |
| createdAt | DateTime! | Creation time |

### UserAdmin

Extended user type for admin queries with additional fields.

| Field | Type | Description |
|---|---|---|
| id | ID! | Unique identifier |
| name | String! | Display name |
| email | String! | Email address |
| avatar | String | Avatar URL |
| emailVerifiedAt | DateTime | Email verification time |
| twoFactorEnabled | Boolean! | 2FA status |
| isAdmin | Boolean! | Admin status |
| disabledAt | DateTime | When the account was disabled |
| createdAt | DateTime! | Account creation time |
| updatedAt | DateTime! | Last update time |

## Input Types

### UpdateProfileInput

| Field | Type | Description |
|---|---|---|
| name | String | New display name |
| email | String | New email (triggers verification) |
| avatar | String | New avatar URL |

### NotificationSettingsInput

| Field | Type | Description |
|---|---|---|
| channel | String! | Channel identifier |
| enabled | Boolean | Enable/disable |
| settings | JSON | Channel-specific settings |

### TypePreferencesInput

| Field | Type | Description |
|---|---|---|
| type | String! | Notification type |
| channel | String! | Channel identifier |
| enabled | Boolean! | Enable/disable |

### Filter Inputs

**AuditLogFilter**

| Field | Type |
|---|---|
| action | String |
| userId | ID |
| severity | String |
| dateFrom | Date |
| dateTo | Date |

**AccessLogFilter**

| Field | Type |
|---|---|
| action | String |
| userId | ID |
| resourceType | String |
| dateFrom | Date |
| dateTo | Date |

**NotificationDeliveryFilter**

| Field | Type |
|---|---|
| notificationType | String |
| channel | String |
| status | String |
| userId | ID |
| dateFrom | Date |
| dateTo | Date |

**PaymentFilter**

| Field | Type |
|---|---|
| status | String |
| userId | ID |
| dateFrom | Date |
| dateTo | Date |

## Enums

### SortDirection

\`ASC\` | \`DESC\`

### AuditLogOrderColumn

\`CREATED_AT\` | \`ACTION\` | \`SEVERITY\`

### AccessLogOrderColumn

\`CREATED_AT\` | \`ACTION\` | \`RESOURCE_TYPE\`

### NotificationDeliveryOrderColumn

\`CREATED_AT\` | \`CHANNEL\` | \`STATUS\`

### PaymentOrderColumn

\`CREATED_AT\` | \`AMOUNT\` | \`STATUS\`

## Mutation Payloads

### UpdateProfilePayload

| Field | Type | Description |
|---|---|---|
| user | User! | Updated user |
| emailVerificationSent | Boolean! | Whether a verification email was sent |

### DeleteNotificationsPayload

| Field | Type | Description |
|---|---|---|
| deletedCount | Int! | Number of notifications deleted |

### TypePreferencesPayload

| Field | Type | Description |
|---|---|---|
| preferences | JSON! | Updated preferences |`,
      },
      {
        id: "graphql-errors",
        title: "Error Handling",
        tags: ["graphql", "errors", "troubleshooting", "codes", "validation", "unauthorized"],
        content: `# Error Handling

## Error Response Format

When an error occurs, the response includes an \`errors\` array:

\`\`\`json
{
  "errors": [
    {
      "message": "Unauthenticated.",
      "extensions": {
        "code": "UNAUTHENTICATED"
      },
      "locations": [{ "line": 2, "column": 3 }],
      "path": ["me"]
    }
  ],
  "data": null
}
\`\`\`

Partial success is possible — \`data\` may contain results for fields that succeeded while \`errors\` lists fields that failed.

## Error Codes

| Code | HTTP Status | Meaning |
|---|---|---|
| UNAUTHENTICATED | 401 | Missing, invalid, expired, or revoked API key |
| FORBIDDEN | 403 | Valid key but missing required permission |
| NOT_FOUND | 404 | Requested resource does not exist |
| VALIDATION_ERROR | 422 | Input validation failed |
| INTERNAL_SERVER_ERROR | 500 | Unexpected server error |

## Common Errors

### Missing or Invalid API Key

\`\`\`json
{
  "errors": [{
    "message": "Unauthenticated.",
    "extensions": { "code": "UNAUTHENTICATED" }
  }]
}
\`\`\`

**Fix:** Ensure the \`Authorization: Bearer sk_...\` header is present and the key is active.

### Insufficient Permissions

\`\`\`json
{
  "errors": [{
    "message": "This action is unauthorized.",
    "extensions": { "code": "FORBIDDEN" }
  }]
}
\`\`\`

**Fix:** The API key's user must have the required permission (e.g. \`audit.view\` for \`auditLogs\`). Permissions are managed via user groups.

### Validation Error

\`\`\`json
{
  "errors": [{
    "message": "Validation failed for the field [updateProfile].",
    "extensions": {
      "code": "VALIDATION_ERROR",
      "validation": {
        "input.email": ["The email has already been taken."]
      }
    }
  }]
}
\`\`\`

**Fix:** Check the \`validation\` object for field-specific error messages and correct the input.

### Query Too Deep

\`\`\`json
{
  "errors": [{
    "message": "Query has depth of 15, which exceeds max depth of 12."
  }]
}
\`\`\`

**Fix:** Reduce query nesting. The maximum depth is configured by the administrator (default: 12).

### Query Too Complex

\`\`\`json
{
  "errors": [{
    "message": "Query has a complexity of 250, which exceeds max complexity of 200."
  }]
}
\`\`\`

**Fix:** Request fewer fields or split into multiple simpler queries. The maximum complexity is configured by the administrator (default: 200).

### Rate Limited

HTTP \`429 Too Many Requests\` response with headers and JSON body:

\`\`\`
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
Retry-After: 45
\`\`\`

\`\`\`json
{
  "message": "Rate limit exceeded",
  "retry_after": 45
}
\`\`\`

**Fix:** Wait for the \`Retry-After\` period (in seconds) before retrying.

### GraphQL Disabled

HTTP \`404 Not Found\` — the GraphQL endpoint returns 404 when the feature is disabled by an administrator.

## Troubleshooting

1. **Always check the \`extensions.code\` field** — it provides a machine-readable error category
2. **Validation errors include field details** — check \`extensions.validation\` for specific field errors
3. **Rate limit headers are always present** on successful responses — monitor \`X-RateLimit-Remaining\` proactively
4. **Stack traces are hidden in production** — only the error message and code are returned`,
      },
      {
        id: "graphql-rate-limiting",
        title: "Rate Limiting & Security",
        tags: ["graphql", "rate", "limit", "security", "depth", "complexity", "cors", "introspection"],
        content: `# Rate Limiting & Security

## Rate Limiting

Each API key has a per-minute request limit. The default is configurable by the administrator (default: 60 requests/minute).

### Rate Limit Headers

Every response includes rate limit headers:

| Header | Description |
|---|---|
| X-RateLimit-Limit | Maximum requests per minute for this key |
| X-RateLimit-Remaining | Requests remaining in the current window |
| Retry-After | Seconds to wait before retrying (only on 429 responses) |

### Handling Rate Limits

When rate limited, you receive an HTTP 429 response. Implement exponential backoff or respect the \`Retry-After\` header:

\`\`\`javascript
async function graphqlRequest(query, variables) {
  const response = await fetch("/api/graphql", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": "Bearer sk_your_key",
    },
    body: JSON.stringify({ query, variables }),
  });

  if (response.status === 429) {
    const retryAfter = parseInt(response.headers.get("Retry-After") || "60");
    await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
    return graphqlRequest(query, variables);
  }

  return response.json();
}
\`\`\`

## Query Depth Limit

Queries are limited to a maximum nesting depth (configurable, default: 12). Deeply nested queries are rejected before execution.

**Example of a deep query (may be rejected):**

\`\`\`graphql
query {
  auditLogs {
    data {
      user {  # depth increases with nested types
        ...
      }
    }
  }
}
\`\`\`

## Query Complexity Limit

Each field contributes a complexity score. The total must not exceed the configured maximum (default: 200). Requesting many fields across paginated results increases complexity.

## Max Result Size

Paginated queries return at most a configured number of items per page (default: 100). The \`first\` argument is capped at this value.

## CORS

Cross-origin requests are controlled by the CORS configuration:

- **Allowed methods:** POST, OPTIONS
- **Allowed headers:** Content-Type, Authorization, Accept
- **Allowed origins:** Configurable (default: \`*\` allows all origins)

For production, restrict origins to your specific domains.

## Introspection

Schema introspection (\`__schema\`, \`__type\` queries) can be enabled or disabled by the administrator. When disabled, introspection queries return an error. Disable introspection in production to prevent schema exposure.

When introspection is enabled, you can explore the schema with tools like GraphiQL or Postman:

\`\`\`graphql
query {
  __schema {
    queryType { name }
    mutationType { name }
    types { name kind }
  }
}
\`\`\`

## Key Rotation

API keys support rotation with a configurable grace period (default: 7 days). During the grace period, both the old and new keys remain valid. After the grace period, only the new key works.`,
      },
      {
        id: "graphql-pagination",
        title: "Pagination",
        tags: ["graphql", "pagination", "paginator", "first", "page", "cursor"],
        content: `# Pagination

Paginated queries use a consistent pattern with \`first\` (page size) and \`page\` (page number) arguments.

## Arguments

| Argument | Type | Default | Description |
|---|---|---|---|
| first | Int | 25 | Number of items per page (max is configured by admin, default: 100) |
| page | Int | 1 | Page number (1-indexed) |

## PaginatorInfo

Every paginated response includes a \`paginatorInfo\` object:

| Field | Type | Description |
|---|---|---|
| count | Int! | Number of items on the current page |
| currentPage | Int! | Current page number |
| lastPage | Int! | Last page number |
| perPage | Int! | Items per page |
| total | Int! | Total number of items across all pages |
| hasMorePages | Boolean! | Whether more pages exist |

## Example

\`\`\`graphql
query {
  myNotifications(first: 10, page: 1) {
    data {
      id
      title
      message
      createdAt
    }
    paginatorInfo {
      count
      currentPage
      lastPage
      perPage
      total
      hasMorePages
    }
  }
}
\`\`\`

**Response:**

\`\`\`json
{
  "data": {
    "myNotifications": {
      "data": [
        {
          "id": "1",
          "title": "Backup completed",
          "message": "Daily backup finished successfully.",
          "createdAt": "2026-01-15T10:30:00+00:00"
        }
      ],
      "paginatorInfo": {
        "count": 10,
        "currentPage": 1,
        "lastPage": 5,
        "perPage": 10,
        "total": 42,
        "hasMorePages": true
      }
    }
  }
}
\`\`\`

## Iterating All Pages

\`\`\`javascript
async function fetchAllNotifications(apiKey) {
  let page = 1;
  let hasMore = true;
  const allNotifications = [];

  while (hasMore) {
    const response = await fetch("/api/graphql", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Authorization": \\\`Bearer \\\${apiKey}\\\`,
      },
      body: JSON.stringify({
        query: \\\`
          query ($page: Int!) {
            myNotifications(first: 100, page: $page) {
              data { id title message createdAt }
              paginatorInfo { hasMorePages }
            }
          }
        \\\`,
        variables: { page },
      }),
    });

    const { data } = await response.json();
    allNotifications.push(...data.myNotifications.data);
    hasMore = data.myNotifications.paginatorInfo.hasMorePages;
    page++;
  }

  return allNotifications;
}
\`\`\`

## Paginated Queries

The following queries support pagination:

| Query | Return Type |
|---|---|
| myNotifications | NotificationPaginator |
| auditLogs | AuditLogPaginator |
| accessLogs | AccessLogPaginator |
| notificationDeliveries | NotificationDeliveryPaginator |
| payments | PaymentPaginator |
| usageStats | UsageStatPaginator |
| users | UserAdminPaginator |

Non-paginated queries (\`me\`, \`myApiKeys\`, \`myNotificationSettings\`, \`userGroups\`, \`usageBreakdown\`) return arrays or single objects directly.`,
      },
    ],
  },
  // --- Logs & Monitoring ---
  {
    slug: "logs-monitoring-audit",
    name: "Audit Logs",
    icon: FileText,
    permission: "audit.view",
    articles: [
      {
        id: "audit-logs",
        title: "Audit Log",
        tags: ["audit", "activity", "tracking", "events", "admin"],
        content: `# Audit Log

Review system activity and track user actions.

## Viewing Audit Logs

1. Go to **Configuration** → **Audit Log**
2. Browse the paginated log entries
3. Use filters to narrow results by user, action, severity, or date range

## What's Logged

The audit log tracks:

- **Authentication** - Logins, logouts, failed attempts
- **User Management** - Account creation, modification, deletion
- **Settings Changes** - System configuration updates
- **Backup Operations** - Backup creation, restoration, deletion
- **Admin Actions** - Manual job runs, data exports

## Filtering & Search

- Filter by **user**, **action type**, **severity**, or **correlation ID**
- Set **date ranges** to focus on specific time periods
- Use the search bar for keyword searches

## Exporting

Click **Export CSV** to download filtered audit log data for external analysis.

## Live Streaming

Enable the **Live** toggle to see new log entries appear in real-time (requires broadcasting to be configured).`,
      },
    ],
  },
  {
    slug: "logs-monitoring-app",
    name: "Application Logs",
    icon: FileText,
    permission: "logs.view",
    articles: [
      {
        id: "application-logs",
        title: "Application Logs",
        tags: ["logs", "console", "viewer", "errors", "debug"],
        content: `# Application Logs

View and export application log output in real-time.

## Log Viewer

1. Go to **Configuration** → **Application Logs**
2. View recent log entries with severity levels
3. Enable **Live** mode to stream new entries in real-time

## Log Levels

- **Debug** - Detailed diagnostic information
- **Info** - General operational events
- **Warning** - Potential issues that should be monitored
- **Error** - Failures that need attention
- **Critical** - Severe errors requiring immediate action

## Exporting Logs

Use the Export card to download log files:

1. Select a date range
2. Optionally filter by log level or correlation ID
3. Choose CSV or JSON Lines format
4. Click **Export**

## Correlation IDs

Each request gets a unique correlation ID. Use it to trace all log entries from a single request across the system.`,
      },
      {
        id: "access-logs",
        title: "Access Logs (HIPAA)",
        tags: ["hipaa", "phi", "access", "compliance", "audit trail"],
        content: `# Access Logs (HIPAA)

Track access to protected health information (PHI) for HIPAA compliance.

## What's Tracked

Access logs record every time sensitive data is accessed:

- **User** who accessed the data
- **Action** performed (view, search, export)
- **Resource type** and ID accessed
- **Fields accessed** (extracted automatically)
- **IP address** and timestamp

## Viewing Access Logs

1. Go to **Configuration** → **Access Logs**
2. Browse the log entries
3. Filter by user, action, resource type, or date range

## Exporting

Click **Export CSV** to download access log data for compliance audits.

## Enabling/Disabling

HIPAA access logging can be toggled in **Configuration** → **Log Retention**. When disabled, you can optionally delete all existing access logs (with a compliance warning).`,
      },
    ],
  },
  {
    slug: "logs-monitoring-settings",
    name: "Log Settings & Jobs",
    icon: Settings,
    permission: "settings.view",
    articles: [
      {
        id: "log-retention",
        title: "Log Retention",
        tags: ["retention", "cleanup", "logs", "hipaa", "storage"],
        content: `# Log Retention

Configure how long different types of logs are kept.

## Retention Settings

1. Go to **Configuration** → **Log Retention**
2. Set retention periods for each log type:
   - **Application Logs** - 1 to 365 days
   - **Audit Logs** - 30 to 730 days
   - **Access Logs** - Minimum 6 years (HIPAA requirement)

## HIPAA Access Logging

Toggle HIPAA access logging on or off. When disabled:

- Access log collection stops
- A **Delete All Access Logs** button becomes available
- A compliance warning is shown before deletion

## Automatic Cleanup

Old logs are automatically cleaned up via the scheduled \`log:cleanup\` command. You can also run cleanup manually from **Configuration** → **Jobs**.`,
      },
      {
        id: "scheduled-jobs",
        title: "Scheduled Jobs",
        tags: ["jobs", "scheduler", "queue", "tasks", "cron"],
        content: `# Scheduled Jobs

Monitor and manually trigger scheduled system tasks.

## Viewing Jobs

1. Go to **Configuration** → **Jobs**
2. The **Scheduled Tasks** tab shows all registered tasks with their schedule and last run time

## Running Jobs Manually

Certain tasks can be triggered manually:

- **backup:run** - Create a backup now
- **log:cleanup** - Clean up old log files
- **log:check-suspicious** - Check for suspicious activity patterns

Click **Run Now** next to a whitelisted command. A confirmation dialog is shown for potentially destructive operations.

## Queue Status

The **Queue Status** tab shows pending and failed job counts. The **Failed Jobs** tab lists failed queue jobs with options to retry or delete them.

## Run History

Each manual run is recorded with its output, duration, and exit status. All manual runs are audited.`,
      },
    ],
  },
  // --- Delivery Log (notification_deliveries.view) ---
  {
    slug: "delivery-log",
    name: "Delivery Log",
    icon: Send,
    permission: "notification_deliveries.view",
    articles: [
      {
        id: "delivery-log",
        title: "Notification Delivery Log",
        tags: ["delivery", "notifications", "log", "channels", "status", "errors"],
        content: `# Notification Delivery Log

View and troubleshoot notification delivery attempts across all channels.

## Viewing the Delivery Log

1. Go to **Configuration** → **Delivery Log**
2. Browse the paginated list of delivery attempts
3. Click an error message to view full details

## What's Tracked

Each delivery record shows:

- **Date** — When the delivery was attempted
- **User** — The notification recipient
- **Channel** — Which channel was used (email, telegram, webpush, etc.)
- **Type** — The notification type (e.g. \`backup.completed\`)
- **Status** — Success, Failed, Rate Limited, or Skipped
- **Attempt** — The retry attempt number
- **Error** — Error message if the delivery failed

## Summary Cards

The top of the page shows 7-day totals for each status: successes, failures, rate-limited, and skipped deliveries.

## Filtering

Use the filters to narrow results by:

- **Channel** — Filter by a specific notification channel
- **Status** — Show only successes, failures, rate-limited, or skipped
- **Type** — Search by notification type (e.g. \`login.alert\`)
- **Date range** — Filter by start and end date

## Troubleshooting Failed Deliveries

Click on an error message to open a detail dialog showing the full error, channel, user, and attempt information. Common issues include invalid credentials, rate limits, and unreachable endpoints.`,
      },
    ],
  },
  // --- Usage & Costs (usage.view) ---
  {
    slug: "usage-costs",
    name: "Usage & Costs",
    icon: BarChart3,
    permission: "usage.view",
    articles: [
      {
        id: "usage-costs",
        title: "Usage & Costs",
        tags: ["usage", "cost", "analytics", "budget", "billing", "llm", "tokens"],
        content: `# Usage & Costs

Track and visualize costs across all paid integrations.

## Dashboard Overview

1. Go to **Configuration** → **Usage & Costs**
2. Select a date range (7 days, 30 days, 90 days, this month, last month)
3. View summary cards for total cost and per-integration breakdown

## Tracked Integrations

- **LLM** - AI provider API calls (tokens used, estimated cost)
- **Email** - Transactional email delivery
- **SMS** - Text message sending
- **Storage** - Cloud storage operations
- **Broadcasting** - Real-time event broadcasting

## Cost Trends

The stacked area chart shows cost trends over time. Use the integration filter toggles to show/hide specific integration types.

## Provider Breakdown

The sortable table shows per-provider cost and quantity totals with a summary row.

## Budget Alerts

Configure monthly budgets per integration type in settings. When spending approaches or exceeds a budget (default 80% threshold), admin users are notified.

## Export

Click **Export CSV** to download filtered usage data for external analysis.`,
      },
    ],
  },
  // --- Backup & Data (backups.view) ---
  {
    slug: "backup-data",
    name: "Backup & Data",
    icon: Database,
    permission: "backups.view",
    articles: [
      {
        id: "backup-settings",
        title: "Backup Configuration",
        tags: ["backup", "restore", "admin", "data"],
        content: `# Backup Configuration

## Automatic Backups

Configure scheduled backups:

1. Go to **Configuration** → **Backup**
2. Enable automatic backups
3. Set backup frequency
4. Configure retention period

## Manual Backups

Create a backup immediately:

1. Go to **Configuration** → **Backup**
2. Click **Create Backup**
3. Wait for completion
4. Download if needed

## Restoring from Backup

To restore:

1. Select a backup from the list
2. Click **Restore**
3. Confirm the action
4. Wait for the process to complete

**Warning:** Restoring replaces current data!`,
      },
    ],
  },
];

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Get all help categories the user has access to based on their permissions.
 * Categories without a `permission` field are visible to all authenticated users.
 * Admin users have all permissions in their permissions array, so they see everything.
 * Categories with a `featureFlag` field are only shown when the flag is explicitly `true`.
 */
export function getAllCategories(
  permissions: string[],
  featureFlags?: Record<string, boolean>
): HelpCategory[] {
  const permissionGated = permissionHelpCategories.filter(
    (cat) =>
      (!cat.permission || permissions.includes(cat.permission)) &&
      (!cat.featureFlag || featureFlags?.[cat.featureFlag] === true)
  );
  return [...userHelpCategories, ...permissionGated];
}

/**
 * Find an article by ID across all categories the user can access.
 */
export function findArticle(
  articleId: string,
  permissions: string[],
  featureFlags?: Record<string, boolean>
): { article: HelpArticle; category: HelpCategory } | null {
  const categories = getAllCategories(permissions, featureFlags);

  for (const category of categories) {
    const article = category.articles.find((a) => a.id === articleId);
    if (article) {
      return { article, category };
    }
  }

  return null;
}

/**
 * Get all articles as searchable items, filtered by user permissions.
 */
export function getSearchableArticles(
  permissions: string[],
  featureFlags?: Record<string, boolean>
) {
  const categories = getAllCategories(permissions, featureFlags);

  return categories.flatMap((category) =>
    category.articles.map((article) => ({
      id: article.id,
      title: article.title,
      content: article.content,
      category: category.name,
      categorySlug: category.slug,
      tags: article.tags,
    }))
  );
}
