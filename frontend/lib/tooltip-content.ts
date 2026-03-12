/**
 * Centralized tooltip content for settings pages.
 * Keep tooltips concise (1-2 sentences max).
 */

export const TOOLTIP_CONTENT = {
  // Security Settings
  security: {
    email_verification:
      "Disabled: no verification required. Optional: users can skip verification. Required: users must verify email before accessing the app.",
    two_factor_mode:
      "Disabled: 2FA not available. Optional: users can choose to enable. Required: all users must set up 2FA to access the app.",
    passkey_mode:
      "Disabled: passkeys not available. Optional: users can add passkeys as login method. Required: users must register at least one passkey.",
    password_min_length:
      "Minimum characters required for user passwords. Higher values increase security but may reduce usability.",
    max_login_attempts:
      "Number of failed login attempts before temporary lockout. Set to 0 to disable lockout.",
    session_timeout:
      "Time in minutes before inactive sessions expire. Users will need to log in again after this period.",
  },

  // SSO Settings
  sso: {
    allow_linking:
      "When enabled, users can connect multiple SSO providers to a single account.",
    auto_register:
      "Automatically create accounts for new users who sign in via SSO. Disable to require manual account creation.",
    trust_provider_email:
      "Skip email verification for SSO users. Enable only for trusted identity providers.",
    client_id:
      "The OAuth Client ID from your provider's developer console.",
    client_secret:
      "The OAuth Client Secret from your provider. Keep this value secure.",
    tenant_id:
      "Your Microsoft Azure AD tenant ID. Find this in the Azure portal.",
    oidc_issuer_url:
      "The OIDC issuer URL (discovery endpoint base). Usually ends with the tenant ID or realm name.",
  },

  // AI/LLM Settings
  ai: {
    orchestration_mode:
      "Single: use one provider. Aggregation: combine multiple responses. Council: providers vote on best response.",
    council_strategy:
      "How council mode determines the final response. Majority: most common answer. Weighted: by provider confidence. Synthesize: combine all responses.",
    timeout:
      "Maximum time in seconds to wait for AI provider responses. Increase for complex queries.",
    default_provider:
      "The AI provider used when no specific provider is requested.",
    max_tokens:
      "Maximum length of AI responses in tokens. Higher values allow longer responses but cost more.",
  },

  // System Settings
  system: {
    app_name:
      "The name displayed throughout the application, in emails, and in browser tabs.",
    app_url:
      "The public URL of your application, set via the APP_URL environment variable. Used for OAuth callbacks, email links, and URL generation.",
    registration_enabled:
      "Allow new users to create accounts. Disable to restrict access to invited users only.",
    maintenance_mode:
      "When enabled, only admins can access the application. Users see a maintenance message.",
  },

  // Backup Settings
  backup: {
    retention_days:
      "Number of days to keep backups. Older backups are automatically deleted.",
    retention_count:
      "Maximum number of backups to keep. Oldest backups are deleted when limit is reached.",
    encryption:
      "Encrypt backups with a password. Warning: you cannot restore backups without this password.",
    scheduled_enabled:
      "Automatically create backups on a schedule. Configure frequency below.",
    destination:
      "Where to store backups. Local keeps them on the server; remote options provide offsite protection.",
  },

  // Storage Settings
  storage: {
    max_upload_size:
      "Maximum file size users can upload, in bytes. Common values: 10MB = 10485760, 100MB = 104857600.",
    allowed_types:
      "Comma-separated list of allowed file extensions. Leave empty to allow all types.",
    storage_driver:
      "Where files are stored. Local is simplest; cloud options provide scalability and redundancy.",
    alert_threshold:
      "Percentage of storage used before warning alerts are triggered.",
  },

  // Email Settings
  email: {
    mail_driver:
      "How emails are sent. SMTP is most common; API drivers like Mailgun or SendGrid may be more reliable.",
    mail_encryption:
      "TLS is recommended for most servers. SSL is older but still supported. None is not recommended.",
    mail_from_address:
      "The email address shown as the sender. Should be a valid address that can receive replies.",
    mail_from_name:
      "The display name shown as the sender alongside the email address.",
  },

  // Notification Settings
  notifications: {
    telegram_bot_token:
      "Get this from @BotFather on Telegram after creating your bot.",
    telegram_chat_id:
      "Your chat ID. Send /start to your bot and check the update to find it.",
    discord_webhook_url:
      "Webhook URL from Discord server settings. Create one in Server Settings > Integrations.",
    slack_webhook_url:
      "Incoming webhook URL from Slack. Create one in your Slack app configuration.",
  },
} as const;

export type TooltipSection = keyof typeof TOOLTIP_CONTENT;
export type TooltipKey<S extends TooltipSection> = keyof (typeof TOOLTIP_CONTENT)[S];

/**
 * Get a tooltip string for a specific field.
 */
export function getTooltip<S extends TooltipSection>(
  section: S,
  key: TooltipKey<S>
): string {
  return TOOLTIP_CONTENT[section][key] as string;
}
