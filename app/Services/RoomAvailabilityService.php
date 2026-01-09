<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\Reservation;
use App\Models\RoomBooking;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RoomAvailabilityService
{
    public function isRoomAvailable(Room $room, Carbon $checkIn, Carbon $checkOut): bool
    {
        if (!$room->is_active || $room->status !== 'available') {
            return false;
        }

        $dates = $this->getDateRange($checkIn, $checkOut);

        return !RoomBooking::where('room_id', $room->id)
            ->whereIn('date', $dates)
            ->exists();
    }

    public function getAvailableRooms(Hotel $hotel, Carbon $checkIn, Carbon $checkOut, int $guests = 1): Collection
    {
        $dates = $this->getDateRange($checkIn, $checkOut);

        return $hotel->rooms()
            ->where('is_active', true)
            ->where('status', 'available')
            ->where('capacity', '>=', $guests)
            ->whereDoesntHave('bookings', function ($query) use ($dates) {
                $query->whereIn('date', $dates);
            })
            ->with(['images', 'hotel'])
            ->get();
    }

    public function blockDatesForReservation(Room $room, Reservation $reservation): void
    {
        $dates = $this->getDateRange($reservation->check_in, $reservation->check_out);

        foreach ($dates as $date) {
            RoomBooking::create([
                'room_id' => $room->id,
                'reservation_id' => $reservation->id,
                'date' => $date,
                'status' => 'booked',
            ]);
        }
    }

    public function releaseDatesForReservation(Reservation $reservation): void
    {
        RoomBooking::where('reservation_id', $reservation->id)->delete();
    }

    public function blockDates(Room $room, Carbon $startDate, Carbon $endDate): void
    {
        $dates = $this->getInclusiveDateRange($startDate, $endDate);
        $this->blockSpecificDates($room, $dates);
    }

    public function unblockDates(Room $room, Carbon $startDate, Carbon $endDate): void
    {
        $dates = $this->getInclusiveDateRange($startDate, $endDate);
        $this->unblockSpecificDates($room, $dates);
    }

    public function blockSpecificDates(Room $room, array $dates): void
    {
        foreach ($dates as $date) {
            RoomBooking::firstOrCreate(
                [
                    'room_id' => $room->id,
                    'date' => $date,
                ],
                [
                    'status' => 'blocked',
                ]
            );
        }
    }

    public function unblockSpecificDates(Room $room, array $dates): void
    {
        RoomBooking::where('room_id', $room->id)
            ->whereIn('date', $dates)
            ->where('status', 'blocked')
            ->delete();
    }

    public function getBookedDates(Room $room, Carbon $startDate, Carbon $endDate): array
    {
        return RoomBooking::where('room_id', $room->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->pluck('date')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();
    }

    public function getCalendar(Room $room, Carbon $startDate, Carbon $endDate): array
    {
        $bookings = RoomBooking::where('room_id', $room->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(fn($booking) => $booking->date->format('Y-m-d'));

        $calendar = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dateStr = $current->format('Y-m-d');
            $booking = $bookings->get($dateStr);

            // Return as object keyed by date for frontend lookup
            $calendar[$dateStr] = [
                'date' => $dateStr,
                'status' => $booking ? $booking->status : 'available',
                'reservation_id' => $booking?->reservation_id,
            ];

            $current->addDay();
        }

        return $calendar;
    }

    protected function getDateRange(Carbon $checkIn, Carbon $checkOut): array
    {
        $dates = [];
        $current = $checkIn->copy();
        $end = $checkOut->copy()->subDay();

        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $dates;
    }

    /**
     * Get inclusive date range (includes both start and end dates)
     * Used for manual blocking/unblocking
     */
    protected function getInclusiveDateRange(Carbon $startDate, Carbon $endDate): array
    {
        $dates = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $dates;
    }

    public function calculatePrice(Room $room, Carbon $checkIn, Carbon $checkOut): float
    {
        $nights = $checkIn->diffInDays($checkOut);
        return $room->price_per_night * $nights;
    }
}
