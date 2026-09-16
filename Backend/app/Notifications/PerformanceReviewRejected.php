<?php

namespace App\Notifications;

use App\Models\PerformanceReview;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PerformanceReviewRejected extends Notification
{
    public function __construct(
        public readonly PerformanceReview $performanceReview,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Performance Review Ditolak',
            'message' => sprintf(
                'Performance review Anda untuk periode %s telah ditolak.',
                $this->performanceReview->period->name,
            ),
            'performance_review_id' => $this->performanceReview->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Performance Review Ditolak')
            ->line(sprintf(
                'Performance review Anda untuk periode %s telah ditolak.',
                $this->performanceReview->period->name,
            ))
            ->line('Performance review memerlukan tindak lanjut.');
    }
}
