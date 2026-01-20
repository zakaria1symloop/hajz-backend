<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CarRentalBookingConfirmation extends Notification
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
            ->subject('Car Rental Booking Confirmation - Hajz Algeria')
            ->greeting('Hello ' . $this->booking->customer_name . '!')
            ->line('Thank you for your car rental booking with **' . $this->booking->car->company->name . '**.')
            ->line('**Booking Details:**')
            ->line('Car: ' . $this->booking->car->brand . ' ' . $this->booking->car->model)
            ->line('Pickup Date: ' . $this->booking->pickup_date)
            ->line('Return Date: ' . $this->booking->return_date)
            ->line('Pickup Location: ' . $this->booking->pickup_location)
            ->line('Return Location: ' . $this->booking->return_location)
            ->line('Total Amount: ' . number_format($this->booking->total_amount, 2) . ' DZD')
            ->line('Deposit: ' . number_format($this->booking->deposit_amount, 2) . ' DZD')
            ->line('**Customer Information:**')
            ->line('Name: ' . $this->booking->customer_name)
            ->line('Email: ' . $this->booking->customer_email)
            ->line('Phone: ' . $this->booking->customer_phone)
            ->line('**Special Requests:**')
            ->line($this->booking->special_requests ?: 'None')
            ->line('Please bring your driver\'s license and a valid ID when picking up the car.')
            ->line('We wish you a safe and pleasant journey!')
            ->salutation('Best regards, Hajz Algeria Team');
    }
}
