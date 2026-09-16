<?php

namespace Tests\Feature\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create([
            'name' => 'notification.view',
            'guard_name' => 'web',
        ]);

        Permission::create([
            'name' => 'notification.read',
            'guard_name' => 'web',
        ]);

        Permission::create([
            'name' => 'notification.delete',
            'guard_name' => 'web',
        ]);

        $this->user = User::factory()->create();

        $this->user->givePermissionTo([
            'notification.view',
            'notification.read',
            'notification.delete',
        ]);
    }

    private function createNotification(
        User $user,
        array $data = [],
        ?string $readAt = null,
    ): DatabaseNotification {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\RecommendationCreated',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode($data),
            'read_at' => $readAt,
        ]);
    }

    public function test_can_get_notifications(): void
    {
        $this->createNotification(
            user: $this->user,
            data: ['title' => 'Notification 1'],
        );

        $this->createNotification(
            user: $this->user,
            data: ['title' => 'Notification 2'],
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Data notifikasi berhasil diambil.')
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_unread_notifications(): void
    {
        $this->createNotification(
            user: $this->user,
            data: ['title' => 'Unread'],
        );

        $this->createNotification(
            user: $this->user,
            data: ['title' => 'Read'],
            readAt: now()->toDateTimeString(),
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications?unread_only=true');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame(
            'Unread',
            json_decode(
                $response->json('data.0.data'),
                true,
            )['title'],
        );
    }

    public function test_can_get_notification_detail(): void
    {
        $notification = $this->createNotification(
            user: $this->user,
            data: ['title' => 'Recommendation Baru'],
        );

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/notifications/{$notification->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $notification->id,
            );
    }

    public function test_cannot_get_other_user_notification(): void
    {
        $otherUser = User::factory()->create();

        $notification = $this->createNotification(
            user: $otherUser,
            data: ['title' => 'Private'],
        );

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/notifications/{$notification->id}");

        $response->assertNotFound();
    }

    public function test_can_mark_notification_as_read(): void
    {
        $notification = $this->createNotification(
            user: $this->user,
            data: ['title' => 'Unread'],
        );

        $response = $this->actingAs($this->user)
            ->patchJson(
                "/api/v1/notifications/{$notification->id}/read",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $notification->id,
            );

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
        ]);

        $this->assertNotNull(
            $notification->refresh()->read_at,
        );
    }

    public function test_can_mark_all_notifications_as_read(): void
    {
        $first = $this->createNotification(
            user: $this->user,
            data: ['title' => 'First'],
        );

        $second = $this->createNotification(
            user: $this->user,
            data: ['title' => 'Second'],
        );

        $response = $this->actingAs($this->user)
            ->patchJson('/api/v1/notifications/read-all');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Semua notifikasi berhasil ditandai sebagai sudah dibaca.',
            );

        $this->assertNotNull(
            $first->refresh()->read_at,
        );

        $this->assertNotNull(
            $second->refresh()->read_at,
        );
    }

    public function test_can_delete_notification(): void
    {
        $notification = $this->createNotification(
            user: $this->user,
            data: ['title' => 'To Delete'],
        );

        $response = $this->actingAs($this->user)
            ->deleteJson(
                "/api/v1/notifications/{$notification->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Notifikasi berhasil dihapus.',
            );

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
        ]);
    }

    public function test_cannot_delete_other_user_notification(): void
    {
        $otherUser = User::factory()->create();

        $notification = $this->createNotification(
            user: $otherUser,
            data: ['title' => 'Private'],
        );

        $response = $this->actingAs($this->user)
            ->deleteJson(
                "/api/v1/notifications/{$notification->id}",
            );

        $response->assertNotFound();

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
        ]);
    }

    public function test_requires_view_permission_to_get_notifications(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/notifications');

        $response->assertForbidden();
    }

    public function test_requires_read_permission_to_mark_notification_as_read(): void
    {
        $user = User::factory()->create();

        $notification = $this->createNotification(
            user: $user,
            data: ['title' => 'Unread'],
        );

        $user->givePermissionTo('notification.view');

        $response = $this->actingAs($user)
            ->patchJson(
                "/api/v1/notifications/{$notification->id}/read",
            );

        $response->assertForbidden();
    }

    public function test_requires_delete_permission_to_delete_notification(): void
    {
        $user = User::factory()->create();

        $notification = $this->createNotification(
            user: $user,
            data: ['title' => 'Notification'],
        );

        $user->givePermissionTo('notification.view');

        $response = $this->actingAs($user)
            ->deleteJson(
                "/api/v1/notifications/{$notification->id}",
            );

        $response->assertForbidden();
    }
}
