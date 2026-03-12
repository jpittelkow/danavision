# Notification Templates Implementation - 2026-01-30

## Overview

Implemented per-notification-type templates for push, in-app, and chat channels. Admins can customize title and body per type and channel group (push, inapp, chat) from Configuration > Notification Templates. Email continues to use the existing EmailTemplate system (ADR-016).

## Implementation Approach

- **Database:** New `notification_templates` table (type, channel_group, title, body, variables, is_system, is_active) with unique (type, channel_group). Migration runs NotificationTemplateSeeder so fresh installs get 18 default templates (6 types × 3 channel groups).
- **NotificationTemplateService:** getByTypeAndChannel(), render(), renderTemplate(), renderContent(), getAvailableVariables(), getDefaultContent(). Variable replacement matches EmailTemplateService ({{variable}}, {{user.name}}).
- **NotificationOrchestrator:** New sendByType($user, $type, $variables, $channels) resolves template per channel group and sends; existing send() unchanged. Channel-to-group mapping: database→inapp, webpush/fcm/ntfy→push, telegram/discord/slack/twilio/signal/matrix/vonage/sns→chat, email→email (variables must include title/message for email).
- **Channels:** Each channel’s send() calls resolveContent() to look up a template for (type, channel_group); if found, render and use title/body; else use passed title/message (backward compatible).
- **Admin API:** NotificationTemplateController (index, show, update, preview, reset by id) under settings.view / settings.edit.
- **Frontend:** Configuration > Notification Templates list page and edit page (title, body, is_active, live preview, reset to default). Sidebar and search-pages updated.

## Challenges Encountered

- **Orchestrator constructor:** NotificationOrchestrator was previously instantiated with no args in AppServiceProvider; added NotificationTemplateService injection and updated unit test to use app(NotificationOrchestrator::class).
- **VonageChannel:** send() overwrote $data with $response->json(); renamed to $responseData to avoid losing template-resolved content.

## Observations

- Per-type templates give admins control over wording per channel (e.g. shorter push title vs longer in-app body) without code changes.
- Backward compatibility: send($user, $type, $title, $message, $data) still works; channels optionally resolve a template when one exists.
- sendByType() skips a channel if no template exists for that type+channel_group (push/inapp/chat); for email, caller must pass title and message in variables.

## Trade-offs

- Email is not in NotificationTemplate; it continues to use EmailTemplate and the single “notification” template. sendByType() for email requires variables['title'] and variables['message'].
- No separate notification-template-editor component; edit form is inline on the [id] page (simpler than email’s TipTap editor).

## Next Steps (Future Considerations)

- Optional: Add email to NotificationTemplate (channel_group email) for per-type email notification templates.
- Optional: Add more notification types and seed them via seeder or a dedicated migration.

## Testing Notes

- Run migration: table created and 18 templates seeded.
- GET /api/notification-templates: list returned (auth + settings.view).
- GET/PUT /api/notification-templates/{id}: show and update.
- POST preview and reset: preview returns title/body; reset restores system template.
- Frontend: list page shows type, channel group, title, status; edit page saves and previews; reset works for system templates.
- Channels: send() with a type that has a template should use rendered title/body; send() with no template uses passed title/message.
- sendByType(): triggers template resolution per channel; verify in-app/push/chat receive templated content when template exists.
