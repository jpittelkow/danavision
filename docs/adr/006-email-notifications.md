# ADR 006: Email Notification System

## Status

Accepted

## Date

2024-12-31

## Context

Users want to be notified when:

1. Prices drop on tracked items
2. Items reach their target price
3. All-time low prices are detected
4. Daily/weekly price summary

Email is the most reliable notification channel for these alerts.

## Decision

We will implement an **Email Notification System** with:

### Notification Types

1. **Price Drop Alert** - Immediate notification when price drops
2. **Target Price Alert** - When item reaches user's target price
3. **All-Time Low Alert** - When lowest price ever is detected
4. **Daily Summary** - Digest of all price changes

### User Preferences

Users can configure:
- Email address for notifications
- Which notification types to receive
- Frequency (immediate, daily, weekly)

### Mail Providers

Support multiple mail providers via Laravel Mail:
- SMTP (default)
- Mailgun
- SendGrid
- Amazon SES
- Postmark

### Implementation

1. **Notification Model** - Track sent notifications
2. **MailService** - Abstract mail sending
3. **DailyPriceCheck Job** - Scheduled job to check prices and send alerts
4. **Settings** - User mail preferences

### Database Schema

```php
Schema::create('notifications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('type'); // price_drop, target_reached, etc.
    $table->foreignId('list_item_id')->nullable();
    $table->text('message');
    $table->boolean('is_read')->default(false);
    $table->timestamp('sent_at')->nullable();
    $table->timestamps();
});
```

## Consequences

### Positive

- Users stay informed about price changes
- Configurable notification preferences
- Multiple mail provider support
- Queued sending for performance

### Negative

- Email deliverability concerns
- Need to handle bounces/unsubscribes
- Cost for transactional email services

## Related Decisions

- [ADR 005: User-Based Lists](005-user-based-lists.md) - Notifications tied to user's lists
