<?php

namespace Tests\Unit\Notifications;

use App\Models\Recommendation;
use App\Notifications\RecommendationCreated;
use Illuminate\Notifications\Messages\MailMessage;
use PHPUnit\Framework\TestCase;

class RecommendationCreatedTest extends TestCase
{
    public function test_notification_uses_database_and_mail_channels(): void
    {
        $recommendation = new Recommendation([
            'title' => 'Promosi Karyawan',
            'description' => 'Karyawan direkomendasikan untuk promosi.',
        ]);

        $notification = new RecommendationCreated($recommendation);

        $this->assertSame(
            ['database', 'mail'],
            $notification->via(new \stdClass()),
        );
    }

    public function test_notification_database_payload_is_correct(): void
    {
        $recommendation = new Recommendation([
            'title' => 'Promosi Karyawan',
            'description' => 'Karyawan direkomendasikan untuk promosi.',
        ]);

        $recommendation->id = 10;

        $notification = new RecommendationCreated($recommendation);

        $payload = $notification->toDatabase(new \stdClass());

        $this->assertSame([
            'title' => 'Recommendation Baru',
            'message' => 'Promosi Karyawan',
            'recommendation_id' => 10,
        ], $payload);
    }

    public function test_notification_mail_message_is_correct(): void
    {
        $recommendation = new Recommendation([
            'title' => 'Promosi Karyawan',
            'description' => 'Karyawan direkomendasikan untuk promosi.',
        ]);

        $notification = new RecommendationCreated($recommendation);

        $mail = $notification->toMail(new \stdClass());

        $this->assertInstanceOf(
            MailMessage::class,
            $mail,
        );

        $this->assertSame(
            'Recommendation Baru',
            $mail->subject,
        );

        $this->assertSame(
            'Promosi Karyawan',
            $mail->introLines[0],
        );
    }

    public function test_notification_contains_recommendation(): void
    {
        $recommendation = new Recommendation([
            'title' => 'Promosi Karyawan',
            'description' => 'Karyawan direkomendasikan untuk promosi.',
        ]);

        $notification = new RecommendationCreated($recommendation);

        $this->assertSame(
            $recommendation,
            $notification->recommendation,
        );
    }

    public function test_notification_should_be_queued(): void
    {
        $recommendation = new Recommendation([
            'title' => 'Promosi Karyawan',
            'description' => 'Karyawan direkomendasikan untuk promosi.',
        ]);

        $notification = new RecommendationCreated($recommendation);

        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            $notification,
        );
    }
}
