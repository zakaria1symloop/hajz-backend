<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class RestaurantBookingConfirmation extends Notification
{
    use Queueable;

    protected $reservation;

    public function __construct($reservation)
    {
        $this->reservation = $reservation;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Restaurant Reservation Confirmation - Hajz Algeria')
            ->greeting('Hello ' . $this->reservation->guest_name . '!')
            ->line('Thank you for your reservation at **' . $this->reservation->restaurant->name . '**.')
            ->line('**Reservation Details:**')
            ->line('Date: ' . $this->reservation->reservation_date)
            ->line('Time: ' . $this->reservation->reservation_time)
            ->line('Guests: ' . $this->reservation->number_of_guests)
            ->line('Table: ' . ($this->reservation->table ? $this->reservation->table->name : 'To be assigned'))
            ->line('**Guest Information:**')
            ->line('Name: ' . $this->reservation->guest_name)
            ->line('Email: ' . $this->reservation->guest_email)
            ->line('Phone: ' . $this->reservation->guest_phone)
            ->line('**Special Requests:**')
            ->line($this->reservation->special_requests ?: 'None')
            ->line('We look forward to serving you!')
            ->salutation('Best regards, Hajz Algeria Team');
    }
}
