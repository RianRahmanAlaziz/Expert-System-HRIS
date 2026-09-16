<?php

namespace Tests\Unit\Services\Notification;

use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new NotificationService();
    }

    public function test_can_paginate_user_notifications(): void
    {
        $user = User::factory()->create();

        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\RecommendationCreated',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => 'Recommendation 1',
            ]),
        ]);

        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\RecommendationCreated',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => 'Recommendation 2',
            ]),
        ]);

        $result = $this->service->paginate(
            user: $user,
            perPage: 15,
        );

        $this->assertSame(2, $result->total());
        $this->assertCount(2, $result->items());
    }

    public function test_can_paginate_only_unread_notifications(): void
    {
        $user = User::factory()->create();

        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\RecommendationCreated',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => 'Unread',
            ]),
        ]);

        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\RecommendationCreated',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => 'Read',
            ]),
            'read_at' => now(),
        ]);

        $result = $this->service->paginate(
            user: $user,
            perPage: 15,
            unreadOnly: true,
        );

        $this->assertSame(1, $result->total());
        $this->assertCount(1, $result->items());

        $this->assertSame(
            'Unread',
            json_decode($result->items()[0]->data, true)['title'],
        );
    }

    public function test_can_find_user_notification_by_id(): void
    {
        $user = User::factory()->create();

        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\RecommendationCreated',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => 'Recommendation',
            ]),
        ]);

        $result = $this->service->findById(
            user: $user,
            id: $notification->id,
        );

        $this->assertSame(
            $notification->id,
            $result->id,
        );
    }

    public function test_cannot_find_notification_belonging_to_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\RecommendationCreated',
            'notifiable_type' => User::class,
            'notifiable_id' => $otherUser->id,
            'data' => json_encode([
                'title' => 'Private Notification',
            ]),
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->findById(
            user: $user,
            id: $notification->id,
        );
    }

    public function test_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();

        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\RecommendationCreated',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => 'Recommendation',
            ]),
        ]);

        $result = $this->service->markAsRead(
            user: $user,
            id: $notification->id,
        );

        $this->assertNotNull($result->read_at);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
        ]);
    }

    public function test_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();

        $first = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\RecommendationCreated',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => 'First',
            ]),
        ]);

        $second = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\RecommendationCreated',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => 'Second',
            ]),
        ]);

        $this->service->markAllAsRead(user: $user);

        $this->assertNotNull(
            $first->refresh()->read_at,
        );

        $this->assertNotNull(
            $second->refresh()->read_at,
        );
    }

    public function test_can_delete_user_notification(): void
    {
        $user = User::factory()->create();

        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\RecommendationCreated',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => 'Recommendation',
            ]),
        ]);

        $this->service->delete(
            user: $user,
            id: $notification->id,
        );

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
        ]);
    }

    public function test_cannot_delete_notification_belonging_to_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\RecommendationCreated',
            'notifiable_type' => User::class,
            'notifiable_id' => $otherUser->id,
            'data' => json_encode([
                'title' => 'Private Notification',
            ]),
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->delete(
            user: $user,
            id: $notification->id,
        );

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
        ]);
    }
}
