<?php

namespace App\Http\Controllers;

use App\Models\CarRentalCompany;
use App\Models\Car;
use App\Models\CarBooking;
use App\Models\Payment;
use App\Services\ChargilyService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CarRentalController extends Controller
{
    protected ChargilyService $chargilyService;

    public function __construct(ChargilyService $chargilyService)
    {
        $this->chargilyService = $chargilyService;
    }
    public function index(Request $request)
    {
        // Show all companies (active and inactive) - frontend will display badge for inactive ones
        $query = CarRentalCompany::with(['wilaya', 'images', 'cars' => function ($q) {
            $q->where('is_available', true)->with('images');
        }]);

        // Filter by wilaya
        if ($request->has('wilaya_id') || $request->has('wilaya')) {
            $wilayaId = $request->wilaya_id ?? $request->wilaya;
            $query->where('wilaya_id', $wilayaId);
        }

        // Filter by city
        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        // Filter by search query
        if ($request->has('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by verification status
        if ($request->has('verified')) {
            $query->where('verification_status', 'verified');
        }

        // Sorting
        $sortBy = $request->get('sort', 'name');
        $sortOrder = $request->get('order', 'asc');

        switch ($sortBy) {
            case 'name':
                $query->orderBy('name', $sortOrder);
                break;
            default:
                $query->orderBy('name', 'asc');
        }

        $companies = $query->paginate($request->get('per_page', 12));

        // Add cars count and images
        $companies->getCollection()->transform(function ($company) {
            $company->cars_count = $company->cars->count();

            // Get first car's image as company image
            $firstCar = $company->cars->first();
            if ($firstCar && $firstCar->images->count() > 0) {
                $company->images = $firstCar->images->map(function ($img) {
                    return [
                        'id' => $img->id,
                        'image_path' => $img->image_path,
                        'url' => asset('storage/' . $img->image_path),
                    ];
                });
            } else {
                $company->images = [];
            }

            // Get min price from cars
            $minPrice = $company->cars->min('price_per_day');
            $company->min_price = $minPrice;

            return $company;
        });

        return response()->json($companies);
    }

    public function show(CarRentalCompany $carRental)
    {
        $carRental->load(['wilaya', 'cars.images']);

        return response()->json($carRental);
    }

    public function featured()
    {
        $companies = CarRentalCompany::with(['wilaya', 'images'])
            ->where('is_active', true)
            ->withCount('cars')
            ->having('cars_count', '>', 0)
            ->orderBy('cars_count', 'desc')
            ->limit(6)
            ->get();

        // Add extra info
        $companies->transform(function ($company) {
            // Get images from company or first car
            if ($company->images->isEmpty()) {
                $firstCar = $company->cars()->with('images')->first();
                if ($firstCar && $firstCar->images->count() > 0) {
                    $primaryImage = $firstCar->images->where('is_primary', true)->first() ?? $firstCar->images->first();
                    $company->images = [[
                        'id' => $primaryImage->id,
                        'image_path' => $primaryImage->image_path,
                        'url' => asset('storage/' . $primaryImage->image_path),
                    ]];
                    $company->primary_image_url = asset('storage/' . $primaryImage->image_path);
                }
            }

            // Get min price from cars
            $company->min_price = $company->cars()->min('price_per_day');

            return $company;
        });

        return response()->json($companies);
    }

    public function cars(Request $request, CarRentalCompany $carRental)
    {
        $query = $carRental->cars()->where('is_available', true)->with('images');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by transmission
        if ($request->has('transmission')) {
            $query->where('transmission', $request->transmission);
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price_per_day', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price_per_day', '<=', $request->max_price);
        }

        // Filter by seats
        if ($request->has('min_seats')) {
            $query->where('seats', '>=', $request->min_seats);
        }

        $cars = $query->paginate($request->get('per_page', 12));

        return response()->json($cars);
    }

    public function showCar(CarRentalCompany $carRental, Car $car)
    {
        $car->load(['images', 'company.wilaya']);

        return response()->json($car);
    }

    public function showSingleCar(Car $car)
    {
        $car->load(['images', 'company.wilaya']);

        return response()->json($car);
    }

    public function search(Request $request)
    {
        // Show all companies - frontend will display badge for inactive ones
        $query = CarRentalCompany::query();

        if ($request->has('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(12));
    }

    public function checkAvailability(Request $request, Car $car)
    {
        $request->validate([
            'pickup_date' => 'required|date|after_or_equal:today',
            'return_date' => 'required|date|after:pickup_date',
        ]);

        $isAvailable = $car->isAvailableForDates($request->pickup_date, $request->return_date);
        $rentalDays = Carbon::parse($request->pickup_date)->diffInDays(Carbon::parse($request->return_date));

        return response()->json([
            'available' => $isAvailable,
            'car' => $car->load('images'),
            'rental_days' => $rentalDays,
            'price_per_day' => $car->price_per_day,
            'subtotal' => $car->price_per_day * $rentalDays,
            'deposit_amount' => $car->deposit_amount,
            'mileage_limit' => $car->mileage_limit,
            'extra_km_price' => $car->extra_km_price,
            'total_km_allowed' => ($car->mileage_limit ?? 0) * $rentalDays,
        ]);
    }

    public function bookCar(Request $request, Car $car)
    {
        $validated = $request->validate([
            'pickup_date' => 'required|date|after_or_equal:today',
            'pickup_time' => 'required|date_format:H:i',
            'return_date' => 'required|date|after:pickup_date',
            'return_time' => 'required|date_format:H:i',
            'pickup_location' => 'nullable|string|max:255',
            'return_location' => 'nullable|string|max:255',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_id_number' => 'nullable|string|max:50',
            'driver_license_number' => 'required|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        if (!$car->is_available) {
            return response()->json([
                'error' => 'This car is not available for booking.',
            ], 422);
        }

        if (!$car->isAvailableForDates($validated['pickup_date'], $validated['return_date'])) {
            return response()->json([
                'error' => 'This car is already booked for the selected dates.',
            ], 422);
        }

        $rentalDays = Carbon::parse($validated['pickup_date'])->diffInDays(Carbon::parse($validated['return_date']));

        // Check min/max rental days
        if ($car->min_rental_days && $rentalDays < $car->min_rental_days) {
            return response()->json([
                'error' => "Minimum rental period is {$car->min_rental_days} days.",
            ], 422);
        }
        if ($car->max_rental_days && $rentalDays > $car->max_rental_days) {
            return response()->json([
                'error' => "Maximum rental period is {$car->max_rental_days} days.",
            ], 422);
        }

        $pricePerDay = $car->price_per_day;
        $subtotal = $pricePerDay * $rentalDays;
        $depositAmount = $car->deposit_amount ?? 0;

        $booking = CarBooking::create([
            'car_id' => $car->id,
            'car_rental_company_id' => $car->car_rental_company_id,
            'user_id' => auth()->id(),
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $validated['customer_phone'],
            'customer_id_number' => $validated['customer_id_number'] ?? null,
            'driver_license_number' => $validated['driver_license_number'],
            'pickup_date' => $validated['pickup_date'],
            'pickup_time' => $validated['pickup_time'],
            'return_date' => $validated['return_date'],
            'return_time' => $validated['return_time'],
            'pickup_location' => $validated['pickup_location'] ?? $car->company->address,
            'return_location' => $validated['return_location'] ?? $car->company->address,
            'rental_days' => $rentalDays,
            'price_per_day' => $pricePerDay,
            'subtotal' => $subtotal,
            'deposit_amount' => $depositAmount,
            'total_amount' => $subtotal,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        // Create payment record
        $payment = Payment::create([
            'user_id' => auth()->id(),
            'payable_type' => CarBooking::class,
            'payable_id' => $booking->id,
            'amount' => $subtotal,
            'currency' => 'DZD',
            'status' => 'pending',
            'payment_method' => 'chargily',
            'description' => "Car Rental Booking #{$booking->id}",
        ]);

        // Update booking with payment ID
        $booking->update(['payment_id' => $payment->id]);

        // Initiate Chargily payment
        $paymentData = [
            'payment_id' => $payment->id,
            'amount' => $subtotal,
            'guest_name' => $validated['customer_name'],
            'guest_email' => $validated['customer_email'],
            'guest_phone' => $validated['customer_phone'],
            'description' => "Location Voiture - {$car->brand} {$car->model}",
            'items' => [
                [
                    'name' => "{$car->brand} {$car->model} - {$rentalDays} jours",
                    'quantity' => $rentalDays,
                    'price' => $pricePerDay,
                ]
            ],
        ];

        // Add user info if authenticated
        if (auth()->check()) {
            $paymentData['user'] = auth()->user();
        }

        $result = $this->chargilyService->initiatePayment($paymentData);

        if (!$result['success']) {
            // If payment initiation fails, delete the booking and payment
            $payment->delete();
            $booking->delete();

            return response()->json([
                'error' => $result['message'] ?? 'Payment initiation failed',
                'errors' => $result['errors'] ?? [],
            ], 400);
        }

        // Update payment with Chargily checkout ID
        $payment->update([
            'chargily_checkout_id' => $result['checkout_id'],
            'chargily_response' => $result['data'],
        ]);

        return response()->json([
            'message' => 'Booking created. Please complete payment.',
            'booking' => $booking->load(['car', 'company']),
            'rental_days' => $rentalDays,
            'subtotal' => $subtotal,
            'deposit_amount' => $depositAmount,
            'mileage_limit' => $car->mileage_limit,
            'total_km_allowed' => ($car->mileage_limit ?? 0) * $rentalDays,
            'extra_km_price' => $car->extra_km_price,
            'payment_url' => $result['payment_url'],
            'payment' => $payment,
        ], 201);
    }

    public function allCars(Request $request)
    {
        $query = Car::where('is_available', true)->with(['images', 'company.wilaya']);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by transmission
        if ($request->has('transmission')) {
            $query->where('transmission', $request->transmission);
        }

        // Filter by fuel type
        if ($request->has('fuel_type')) {
            $query->where('fuel_type', $request->fuel_type);
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price_per_day', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price_per_day', '<=', $request->max_price);
        }

        // Filter by seats
        if ($request->has('min_seats')) {
            $query->where('seats', '>=', $request->min_seats);
        }

        // Filter by wilaya
        if ($request->has('wilaya_id')) {
            $query->whereHas('company', function ($q) use ($request) {
                $q->where('wilaya_id', $request->wilaya_id);
            });
        }

        // Filter by availability dates
        if ($request->has('pickup_date') && $request->has('return_date')) {
            $pickupDate = $request->pickup_date;
            $returnDate = $request->return_date;

            $query->whereDoesntHave('bookings', function ($q) use ($pickupDate, $returnDate) {
                $q->whereIn('status', ['pending', 'confirmed', 'picked_up'])
                    ->where(function ($q2) use ($pickupDate, $returnDate) {
                        $q2->whereBetween('pickup_date', [$pickupDate, $returnDate])
                            ->orWhereBetween('return_date', [$pickupDate, $returnDate])
                            ->orWhere(function ($q3) use ($pickupDate, $returnDate) {
                                $q3->where('pickup_date', '<=', $pickupDate)
                                    ->where('return_date', '>=', $returnDate);
                            });
                    });
            });
        }

        // Sorting
        $sortBy = $request->get('sort', 'price');
        $sortOrder = $request->get('order', 'asc');

        switch ($sortBy) {
            case 'price':
                $query->orderBy('price_per_day', $sortOrder);
                break;
            case 'seats':
                $query->orderBy('seats', $sortOrder);
                break;
            case 'year':
                $query->orderBy('year', $sortOrder);
                break;
            default:
                $query->orderBy('price_per_day', 'asc');
        }

        $cars = $query->paginate($request->get('per_page', 12));

        return response()->json($cars);
    }
}
