<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class HotelBookingConfirmation extends Notification
{
    use Queueable;

    protected $booking;

    public function __construct($booking)
    {
        $this->booking = $booking;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Hotel Booking Confirmation - Hajz Algeria')
            ->greeting('Hello ' . $this->booking->guest_name . '!')
            ->line('Thank you for your booking at **' . $this->booking->room->hotel->name . '**.')
            ->line('**Booking Details:**')
            ->line('Room: ' . $this->booking->room->name)
            ->line('Check-in: ' . $this->booking->check_in_date)
            ->line('Check-out: ' . $this->booking->check_out_date)
            ->line('Guests: ' . $this->booking->guests)
            ->line('Total Amount: ' . number_format($this->booking->total_amount, 2) . ' DZD')
            ->line('**Guest Information:**')
            ->line('Name: ' . $this->booking->guest_name)
            ->line('Email: ' . $this->booking->guest_email)
            ->line('Phone: ' . $this->booking->guest_phone)
            ->line('**Special Requests:**')
            ->line($this->booking->special_requests ?: 'None')
            ->line('We look forward to welcoming you!')
            ->salutation('Best regards, Hajz Algeria Team');
    }
}
