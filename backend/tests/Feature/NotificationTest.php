<?php

use App\Models\User;
use App\Models\Notification;

describe('Notifications', function () {
    
    describe('List Notifications', function () {
        it('returns notifications for authenticated user', function () {
            $user = User::factory()->create();
            
            Notification::factory()->count(3)->create([
                'user_id' => $user->id,
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/notifications');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'type', 'title', 'message', 'read_at'],
                    ],
                ]);
        });

        it('does not return other users notifications', function () {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            
            Notification::factory()->create([
                'user_id' => $otherUser->id,
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/notifications');

            $response->assertStatus(200)
                ->assertJsonCount(0, 'data');
        });
    });

    describe('Unread Count', function () {
        it('returns correct unread count', function () {
            $user = User::factory()->create();
            
            Notification::factory()->count(3)->create([
                'user_id' => $user->id,
                'read_at' => null,
            ]);
            
            Notification::factory()->count(2)->create([
                'user_id' => $user->id,
                'read_at' => now(),
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/notifications/unread-count');

            $response->assertStatus(200)
                ->assertJson(['count' => 3]);
        });
    });

    describe('Mark as Read', function () {
        it('can mark specific notification as read', function () {
            $user = User::factory()->create();
            
            $notification = Notification::factory()->create([
                'user_id' => $user->id,
                'read_at' => null,
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/notifications/mark-read', [
                    'ids' => [$notification->id],
                ]);

            $response->assertStatus(200);
            
            $this->assertNotNull($notification->fresh()->read_at);
        });

        it('can mark all notifications as read', function () {
            $user = User::factory()->create();
            
            Notification::factory()->count(3)->create([
                'user_id' => $user->id,
                'read_at' => null,
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/notifications/mark-all-read');

            $response->assertStatus(200);
            
            $this->assertEquals(0, $user->notifications()->whereNull('read_at')->count());
        });
    });

    describe('Delete Notification', function () {
        it('can delete own notification', function () {
            $user = User::factory()->create();
            
            $notification = Notification::factory()->create([
                'user_id' => $user->id,
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->deleteJson("/api/notifications/{$notification->id}");

            $response->assertStatus(200);
            
            $this->assertDatabaseMissing('notifications', [
                'id' => $notification->id,
            ]);
        });

        it('cannot delete other users notification', function () {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            
            $notification = Notification::factory()->create([
                'user_id' => $otherUser->id,
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->deleteJson("/api/notifications/{$notification->id}");

            // Returns 404 because notifications are scoped to user - user can't find others' notifications
            $response->assertStatus(404);
        });
    });
});
