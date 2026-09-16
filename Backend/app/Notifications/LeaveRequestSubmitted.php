<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestSubmitted extends Notification
{

    /**
     * Create a new notification instance.
     */
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
        $employee = $this->leaveRequest->employee;
        $leaveType = $this->leaveRequest->leaveType;

        return (new MailMessage)
            ->subject('Pengajuan Cuti Baru')
            ->line(sprintf(
                '%s %s mengajukan cuti %s.',
                $employee->first_name,
                $employee->last_name,
                $leaveType->name,
            ))
            ->line(sprintf(
                'Tanggal: %s - %s.',
                $this->leaveRequest->start_date->format('d-m-Y'),
                $this->leaveRequest->end_date->format('d-m-Y'),
            ))
            ->line(sprintf(
                'Total: %s hari.',
                $this->leaveRequest->total_days,
            ));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $employee = $this->leaveRequest->employee;
        $leaveType = $this->leaveRequest->leaveType;

        return [
            'title' => 'Pengajuan Cuti Baru',
            'message' => sprintf(
                '%s %s mengajukan cuti %s.',
                $employee->first_name,
                $employee->last_name,
                $leaveType->name,
            ),
            'leave_request_id' => $this->leaveRequest->id,
        ];
    }
}
