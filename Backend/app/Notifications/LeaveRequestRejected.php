<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestRejected extends Notification
{

    public function __construct(
        public readonly LeaveRequest $leaveRequest,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $leaveType = $this->leaveRequest->leaveType;

        return (new MailMessage)
            ->subject('Pengajuan Cuti Ditolak')
            ->line(sprintf(
                'Pengajuan cuti %s Anda telah ditolak.',
                $leaveType->name,
            ))
            ->line(sprintf(
                'Alasan penolakan: %s',
                $this->leaveRequest->rejection_reason,
            ));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $leaveType = $this->leaveRequest->leaveType;

        return [
            'title' => 'Pengajuan Cuti Ditolak',
            'message' => sprintf(
                'Pengajuan cuti %s Anda telah ditolak.',
                $leaveType->name,
            ),
            'leave_request_id' => $this->leaveRequest->id,
            'rejection_reason' => $this->leaveRequest->rejection_reason,
        ];
    }
}
