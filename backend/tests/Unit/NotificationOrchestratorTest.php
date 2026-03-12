<?php

use App\Services\Notifications\NotificationOrchestrator;
use App\Models\User;
use App\Models\Notification;

describe('NotificationOrchestrator', function () {
    
    beforeEach(function () {
        $this->orchestrator = app(NotificationOrchestrator::class);
    });

    describe('send', function () {
        it('sends via database channel by default', function () {
            $user = User::factory()->create();

            $results = $this->orchestrator->send(
                $user,
                'test',
                'Test Title',
                'Test Message',
                ['key' => 'value'],
                ['database']
            );

            expect($results)->toHaveKey('database');
            expect($results['database']['success'])->toBeTrue();
        });

        it('creates in-app notification via database channel', function () {
            $user = User::factory()->create();

            $this->orchestrator->send(
                $user,
                'test',
                'Test Title',
                'Test Message',
                [],
                ['database']
            );

            $notification = Notification::where('user_id', $user->id)->first();

            expect($notification)->not->toBeNull();
            expect($notification->title)->toBe('Test Title');
            expect($notification->message)->toBe('Test Message');
        });
    });

    describe('createInAppNotification', function () {
        it('creates a notification record', function () {
            $user = User::factory()->create();

            $notification = $this->orchestrator->createInAppNotification(
                $user,
                'info',
                'Test Title',
                'Test Message',
                ['key' => 'value']
            );

            expect($notification)->toBeInstanceOf(Notification::class);
            expect($notification->user_id)->toBe($user->id);
            expect($notification->type)->toBe('info');
            expect($notification->title)->toBe('Test Title');
            expect($notification->data)->toBe(['key' => 'value']);
        });
    });

    describe('sendTestNotification', function () {
        it('throws exception for unknown channel', function () {
            $user = User::factory()->create();

            expect(fn () => $this->orchestrator->sendTestNotification($user, 'unknown'))
                ->toThrow(RuntimeException::class, 'Unknown channel');
        });

        it('throws exception for disabled channel', function () {
            $user = User::factory()->create();

            // Telegram is likely disabled in tests
            config(['notifications.channels.telegram.enabled' => false]);

            expect(fn () => $this->orchestrator->sendTestNotification($user, 'telegram'))
                ->toThrow(RuntimeException::class, 'Channel is not enabled');
        });
    });
});
