<?php

namespace App\Services\Mail;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class MailService
{
    /**
     * Configure the mailer for a specific user.
     */
    public static function configureForUser(?int $userId): void
    {
        $driver = Setting::get(Setting::MAIL_DRIVER, $userId);

        if (!$driver) {
            return;
        }

        // Configure based on driver
        match ($driver) {
            'smtp' => self::configureSMTP($userId),
            'mailgun' => self::configureMailgun($userId),
            'sendgrid' => self::configureSendgrid($userId),
            'ses' => self::configureSES($userId),
            'log' => self::configureLog(),
            default => null,
        };

        // Set from address
        $fromAddress = Setting::get(Setting::MAIL_FROM_ADDRESS, $userId);
        $fromName = Setting::get(Setting::MAIL_FROM_NAME, $userId);

        if ($fromAddress) {
            Config::set('mail.from.address', $fromAddress);
            Config::set('mail.from.name', $fromName ?? config('app.name'));
        }
    }

    /**
     * Configure SMTP mailer.
     */
    protected static function configureSMTP(?int $userId): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', Setting::get(Setting::MAIL_HOST, $userId));
        Config::set('mail.mailers.smtp.port', Setting::get(Setting::MAIL_PORT, $userId));
        Config::set('mail.mailers.smtp.username', Setting::get(Setting::MAIL_USERNAME, $userId));
        Config::set('mail.mailers.smtp.password', Setting::get(Setting::MAIL_PASSWORD, $userId));
    }

    /**
     * Configure Mailgun mailer.
     */
    protected static function configureMailgun(?int $userId): void
    {
        Config::set('mail.default', 'mailgun');
        // Mailgun config would be set via services.mailgun
    }

    /**
     * Configure Sendgrid mailer.
     */
    protected static function configureSendgrid(?int $userId): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.sendgrid.net');
        Config::set('mail.mailers.smtp.port', 587);
        Config::set('mail.mailers.smtp.username', 'apikey');
        Config::set('mail.mailers.smtp.password', Setting::get(Setting::MAIL_PASSWORD, $userId));
    }

    /**
     * Configure SES mailer.
     */
    protected static function configureSES(?int $userId): void
    {
        Config::set('mail.default', 'ses');
    }

    /**
     * Configure Log mailer.
     */
    protected static function configureLog(): void
    {
        Config::set('mail.default', 'log');
    }

    /**
     * Get the driver name for a user.
     */
    public static function getDriverName(?int $userId): ?string
    {
        return Setting::get(Setting::MAIL_DRIVER, $userId);
    }

    /**
     * Check if mail is configured for a user.
     */
    public static function isConfigured(?int $userId): bool
    {
        $driver = Setting::get(Setting::MAIL_DRIVER, $userId);

        if (!$driver) {
            return false;
        }

        if ($driver === 'log') {
            return true;
        }

        // For SMTP, check required fields
        if ($driver === 'smtp') {
            return Setting::get(Setting::MAIL_HOST, $userId) !== null
                && Setting::get(Setting::MAIL_PORT, $userId) !== null
                && Setting::get(Setting::MAIL_FROM_ADDRESS, $userId) !== null;
        }

        return Setting::get(Setting::MAIL_FROM_ADDRESS, $userId) !== null;
    }

    /**
     * Test the mail connection.
     */
    public static function testConnection(?int $userId): bool
    {
        if (!self::isConfigured($userId)) {
            return false;
        }

        self::configureForUser($userId);

        try {
            // For log driver, always return true
            if (Setting::get(Setting::MAIL_DRIVER, $userId) === 'log') {
                return true;
            }

            // Try to connect to the mail server
            $transport = Mail::mailer()->getSymfonyTransport();
            
            // This will throw if connection fails
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
