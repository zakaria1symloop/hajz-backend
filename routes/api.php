<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\HotelOwner\AuthController as HotelOwnerAuthController;
use App\Http\Controllers\HotelOwner\HotelController as HotelOwnerHotelController;
use App\Http\Controllers\HotelOwner\RoomController as HotelOwnerRoomController;
use App\Http\Controllers\HotelOwner\ReservationController as HotelOwnerReservationController;
use App\Http\Controllers\HotelOwner\WalletController as HotelOwnerWalletController;
use App\Http\Controllers\Admin\HotelController as AdminHotelController;
use App\Http\Controllers\Admin\WalletController as AdminWalletController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\WilayaController as AdminWilayaController;
use App\Http\Controllers\RestaurantOwner\AuthController as RestaurantOwnerAuthController;
use App\Http\Controllers\RestaurantOwner\RestaurantController as RestaurantOwnerRestaurantController;
use App\Http\Controllers\RestaurantOwner\PlatController as RestaurantOwnerPlatController;
use App\Http\Controllers\RestaurantOwner\TableController as RestaurantOwnerTableController;
use App\Http\Controllers\RestaurantOwner\TableReservationController as RestaurantOwnerReservationController;
use App\Http\Controllers\RestaurantOwner\WalletController as RestaurantOwnerWalletController;
use App\Http\Controllers\CompanyOwner\AuthController as CompanyOwnerAuthController;
use App\Http\Controllers\CompanyOwner\CompanyController as CompanyOwnerCompanyController;
use App\Http\Controllers\CompanyOwner\CarController as CompanyOwnerCarController;
use App\Http\Controllers\CompanyOwner\CarBookingController as CompanyOwnerBookingController;
use App\Http\Controllers\CompanyOwner\WalletController as CompanyOwnerWalletController;
use App\Http\Controllers\ProAuthController;
use App\Http\Controllers\CarRentalController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUsersController;
use App\Http\Controllers\Admin\AdminProfessionalsController;
use App\Http\Controllers\Admin\AdminHotelsController;
use App\Http\Controllers\Admin\AdminRestaurantsController;
use App\Http\Controllers\Admin\AdminCarRentalsController;
use App\Http\Controllers\Admin\AdminBookingsController;
use App\Http\Controllers\Admin\AdminAdminsController;
use App\Http\Controllers\UserBookingsController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;

// Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Google OAuth routes
Route::get('/auth/google', [AuthController::class, 'googleRedirect']);
Route::post('/auth/google/callback', [AuthController::class, 'googleCallback']);

// Public routes
Route::get('/settings/public', [AdminSettingsController::class, 'publicSettings']);
Route::get('/settings/legal', [AdminSettingsController::class, 'getPublicLegalContent']);
Route::post('/contact', [ContactMessageController::class, 'store']);
Route::get('/hotels', [HotelController::class, 'index']);
Route::get('/hotels/featured', [HotelController::class, 'featured']);
Route::get('/hotels/search', [HotelController::class, 'search']);
Route::get('/hotels/{hotel}', [HotelController::class, 'show']);

// Public room routes
Route::get('/hotels/{hotel}/rooms', [RoomController::class, 'index']);
Route::get('/hotels/{hotel}/rooms/{room}', [RoomController::class, 'show']);
Route::post('/rooms/{room}/check-availability', [RoomController::class, 'checkAvailability']);
Route::get('/rooms/{room}/booked-dates', [RoomController::class, 'getBookedDates']);
Route::get('/rooms/search', [RoomController::class, 'search']);

Route::get('/flights', [FlightController::class, 'index']);
Route::get('/flights/featured', [FlightController::class, 'featured']);
Route::get('/flights/search', [FlightController::class, 'search']);
Route::get('/flights/{flight}', [FlightController::class, 'show']);

Route::get('/restaurants', [RestaurantController::class, 'index']);
Route::get('/restaurants/featured', [RestaurantController::class, 'featured']);
Route::get('/restaurants/cuisine-types', [RestaurantController::class, 'cuisineTypes']);
Route::get('/restaurants/search', [RestaurantController::class, 'search']);
Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show']);
Route::get('/restaurants/{restaurant}/slots', [RestaurantController::class, 'getAvailableSlots']);
Route::get('/restaurants/{restaurant}/available-tables', [RestaurantController::class, 'getAvailableTables']);
Route::post('/restaurants/{restaurant}/reserve', [RestaurantController::class, 'guestReservation']);

// Car Rental routes
Route::get('/car-rentals', [CarRentalController::class, 'index']);
Route::get('/car-rentals/featured', [CarRentalController::class, 'featured']);
Route::get('/car-rentals/search', [CarRentalController::class, 'search']);
Route::get('/car-rentals/{carRental}', [CarRentalController::class, 'show']);
Route::get('/car-rentals/{carRental}/cars', [CarRentalController::class, 'cars']);
Route::get('/car-rentals/{carRental}/cars/{car}', [CarRentalController::class, 'showCar']);

// Public Car routes (for browsing all cars)
Route::get('/cars', [CarRentalController::class, 'allCars']);
Route::get('/cars/{car}', [CarRentalController::class, 'showSingleCar']);
Route::get('/cars/{car}/check-availability', [CarRentalController::class, 'checkAvailability']);
Route::post('/cars/{car}/book', [CarRentalController::class, 'bookCar']);

// Public Wilaya routes
Route::get('/wilayas', [AdminWilayaController::class, 'publicIndex']);
Route::get('/wilayas/featured', [AdminWilayaController::class, 'featured']);
Route::get('/wilayas/popular', [AdminWilayaController::class, 'popular']);

// Webhook (no auth required)
Route::post('/webhook/chargily', [WebhookController::class, 'handleChargily']);

// Payment callbacks (no auth required)
Route::any('/payments/callback', [PaymentController::class, 'callback']);
Route::post('/payments/chargily-webhook', [PaymentController::class, 'chargilyWebhook']);

// Public guest booking route
Route::post('/reservations/guest', [ReservationController::class, 'guestStore']);

// Protected routes (Client)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);
    Route::post('/user/avatar', [AuthController::class, 'uploadAvatar']);
    Route::delete('/user/avatar', [AuthController::class, 'deleteAvatar']);

    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show']);
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);

    // Table reservations (restaurant)
    Route::get('/table-reservations', [UserBookingsController::class, 'tableReservations']);
    Route::post('/table-reservations/{tableReservation}/cancel', [UserBookingsController::class, 'cancelTableReservation']);

    // Car bookings
    Route::get('/car-bookings', [UserBookingsController::class, 'carBookings']);
    Route::post('/car-bookings/{carBooking}/cancel', [UserBookingsController::class, 'cancelCarBooking']);

    // SlickPay Payments
    Route::post('/payments/hotel', [PaymentController::class, 'initiateHotelPayment']);
    Route::post('/payments/restaurant', [PaymentController::class, 'initiateRestaurantPayment']);
    Route::post('/payments/car', [PaymentController::class, 'initiateCarPayment']);
    Route::get('/payments/{paymentId}/status', [PaymentController::class, 'checkStatus']);
});

// Hotel Owner routes
Route::prefix('hotel-owner')->group(function () {
    // Public hotel owner routes
    Route::post('/register', [HotelOwnerAuthController::class, 'register']);
    Route::post('/login', [HotelOwnerAuthController::class, 'login']);

    // Protected hotel owner routes
    Route::middleware(['auth:sanctum', 'hotel-owner'])->group(function () {
        // Auth
        Route::post('/logout', [HotelOwnerAuthController::class, 'logout']);
        Route::get('/me', [HotelOwnerAuthController::class, 'me']);
        Route::put('/profile', [HotelOwnerAuthController::class, 'updateProfile']);
        Route::put('/password', [HotelOwnerAuthController::class, 'changePassword']);

        // Hotel Management
        Route::get('/hotel', [HotelOwnerHotelController::class, 'show']);
        Route::post('/hotel', [HotelOwnerHotelController::class, 'store']);
        Route::put('/hotel', [HotelOwnerHotelController::class, 'update']);
        Route::post('/hotel/images', [HotelOwnerHotelController::class, 'uploadImages']);
        Route::delete('/hotel/images/{image}', [HotelOwnerHotelController::class, 'deleteImage']);
        Route::put('/hotel/images/{image}/primary', [HotelOwnerHotelController::class, 'setImagePrimary']);

        // Room Management
        Route::get('/rooms', [HotelOwnerRoomController::class, 'index']);
        Route::post('/rooms', [HotelOwnerRoomController::class, 'store']);
        Route::get('/rooms/{room}', [HotelOwnerRoomController::class, 'show']);
        Route::put('/rooms/{room}', [HotelOwnerRoomController::class, 'update']);
        Route::delete('/rooms/{room}', [HotelOwnerRoomController::class, 'destroy']);
        Route::post('/rooms/{room}/images', [HotelOwnerRoomController::class, 'uploadImages']);
        Route::delete('/rooms/images/{image}', [HotelOwnerRoomController::class, 'deleteImage']);
        Route::get('/rooms/{room}/calendar', [HotelOwnerRoomController::class, 'getCalendar']);
        Route::put('/rooms/{room}/availability', [HotelOwnerRoomController::class, 'updateAvailability']);

        // Reservations
        Route::get('/reservations', [HotelOwnerReservationController::class, 'index']);
        Route::get('/reservations/stats', [HotelOwnerReservationController::class, 'stats']);
        Route::get('/reservations/{reservation}', [HotelOwnerReservationController::class, 'show']);
        Route::post('/reservations/{reservation}/confirm', [HotelOwnerReservationController::class, 'confirm']);
        Route::post('/reservations/{reservation}/cancel', [HotelOwnerReservationController::class, 'cancel']);
        Route::post('/reservations/{reservation}/check-in', [HotelOwnerReservationController::class, 'checkIn']);
        Route::post('/reservations/{reservation}/check-out', [HotelOwnerReservationController::class, 'checkOut']);

        // Wallet
        Route::get('/wallet', [HotelOwnerWalletController::class, 'show']);
        Route::get('/wallet/transactions', [HotelOwnerWalletController::class, 'transactions']);
        Route::post('/wallet/withdraw', [HotelOwnerWalletController::class, 'requestWithdrawal']);
        Route::get('/wallet/withdrawals', [HotelOwnerWalletController::class, 'withdrawalHistory']);
    });
});

// Unified Pro Login (for all 3 business types)
Route::prefix('pro')->group(function () {
    Route::post('/login', [ProAuthController::class, 'login']);
    Route::post('/register', [ProAuthController::class, 'register']);
});

// Restaurant Owner routes
Route::prefix('restaurant-owner')->group(function () {
    Route::post('/register', [RestaurantOwnerAuthController::class, 'register']);
    Route::post('/login', [RestaurantOwnerAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'restaurant-owner'])->group(function () {
        Route::post('/logout', [RestaurantOwnerAuthController::class, 'logout']);
        Route::get('/me', [RestaurantOwnerAuthController::class, 'me']);
        Route::put('/profile', [RestaurantOwnerAuthController::class, 'updateProfile']);
        Route::put('/password', [RestaurantOwnerAuthController::class, 'changePassword']);

        // Restaurant Management
        Route::get('/restaurant', [RestaurantOwnerRestaurantController::class, 'show']);
        Route::post('/restaurant', [RestaurantOwnerRestaurantController::class, 'store']);
        Route::put('/restaurant', [RestaurantOwnerRestaurantController::class, 'update']);
        Route::post('/restaurant/images', [RestaurantOwnerRestaurantController::class, 'uploadImages']);
        Route::delete('/restaurant/images/{image}', [RestaurantOwnerRestaurantController::class, 'deleteImage']);
        Route::put('/restaurant/images/{image}/primary', [RestaurantOwnerRestaurantController::class, 'setImagePrimary']);

        // Plats Management
        Route::get('/plats', [RestaurantOwnerPlatController::class, 'index']);
        Route::post('/plats', [RestaurantOwnerPlatController::class, 'store']);
        Route::get('/plats/{plat}', [RestaurantOwnerPlatController::class, 'show']);
        Route::put('/plats/{plat}', [RestaurantOwnerPlatController::class, 'update']);
        Route::delete('/plats/{plat}', [RestaurantOwnerPlatController::class, 'destroy']);
        Route::post('/plats/{plat}/images', [RestaurantOwnerPlatController::class, 'uploadImages']);
        Route::delete('/plats/images/{image}', [RestaurantOwnerPlatController::class, 'deleteImage']);

        // Tables Management
        Route::get('/tables', [RestaurantOwnerTableController::class, 'index']);
        Route::post('/tables', [RestaurantOwnerTableController::class, 'store']);
        Route::get('/tables/{table}', [RestaurantOwnerTableController::class, 'show']);
        Route::put('/tables/{table}', [RestaurantOwnerTableController::class, 'update']);
        Route::delete('/tables/{table}', [RestaurantOwnerTableController::class, 'destroy']);
        Route::get('/tables/{table}/availability', [RestaurantOwnerTableController::class, 'getAvailability']);

        // Reservations Management
        Route::get('/reservations', [RestaurantOwnerReservationController::class, 'index']);
        Route::post('/reservations', [RestaurantOwnerReservationController::class, 'store']);
        Route::get('/reservations/stats', [RestaurantOwnerReservationController::class, 'getStats']);
        Route::get('/reservations/slots', [RestaurantOwnerReservationController::class, 'getAvailableSlots']);
        Route::get('/reservations/{reservation}', [RestaurantOwnerReservationController::class, 'show']);
        Route::post('/reservations/{reservation}/confirm', [RestaurantOwnerReservationController::class, 'confirm']);
        Route::post('/reservations/{reservation}/cancel', [RestaurantOwnerReservationController::class, 'cancel']);
        Route::post('/reservations/{reservation}/complete', [RestaurantOwnerReservationController::class, 'complete']);
        Route::post('/reservations/{reservation}/no-show', [RestaurantOwnerReservationController::class, 'noShow']);
        Route::put('/reservations/{reservation}/plats', [RestaurantOwnerReservationController::class, 'updatePlats']);

        // Wallet
        Route::get('/wallet', [RestaurantOwnerWalletController::class, 'show']);
        Route::get('/wallet/transactions', [RestaurantOwnerWalletController::class, 'transactions']);
        Route::post('/wallet/withdraw', [RestaurantOwnerWalletController::class, 'requestWithdrawal']);
        Route::get('/wallet/withdrawals', [RestaurantOwnerWalletController::class, 'withdrawalHistory']);
    });
});

// Car Rental Company Owner routes
Route::prefix('company-owner')->group(function () {
    Route::post('/register', [CompanyOwnerAuthController::class, 'register']);
    Route::post('/login', [CompanyOwnerAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'company-owner'])->group(function () {
        Route::post('/logout', [CompanyOwnerAuthController::class, 'logout']);
        Route::get('/me', [CompanyOwnerAuthController::class, 'me']);
        Route::put('/profile', [CompanyOwnerAuthController::class, 'updateProfile']);
        Route::put('/password', [CompanyOwnerAuthController::class, 'changePassword']);

        // Company management
        Route::get('/company', [CompanyOwnerCompanyController::class, 'show']);
        Route::post('/company', [CompanyOwnerCompanyController::class, 'store']);
        Route::put('/company', [CompanyOwnerCompanyController::class, 'update']);
        Route::get('/company/stats', [CompanyOwnerCompanyController::class, 'getStats']);
        Route::post('/company/images', [CompanyOwnerCompanyController::class, 'uploadImages']);
        Route::delete('/company/images/{image}', [CompanyOwnerCompanyController::class, 'deleteImage']);
        Route::put('/company/images/{image}/primary', [CompanyOwnerCompanyController::class, 'setPrimaryImage']);

        // Car management
        Route::get('/cars', [CompanyOwnerCarController::class, 'index']);
        Route::post('/cars', [CompanyOwnerCarController::class, 'store']);
        Route::get('/cars/{car}', [CompanyOwnerCarController::class, 'show']);
        Route::put('/cars/{car}', [CompanyOwnerCarController::class, 'update']);
        Route::delete('/cars/{car}', [CompanyOwnerCarController::class, 'destroy']);
        Route::post('/cars/{car}/toggle-availability', [CompanyOwnerCarController::class, 'toggleAvailability']);
        Route::get('/cars/{car}/availability', [CompanyOwnerCarController::class, 'getAvailability']);
        Route::post('/cars/{car}/images', [CompanyOwnerCarController::class, 'uploadImages']);
        Route::delete('/cars/{car}/images/{image}', [CompanyOwnerCarController::class, 'deleteImage']);
        Route::put('/cars/{car}/images/{image}/primary', [CompanyOwnerCarController::class, 'setPrimaryImage']);

        // Booking management
        Route::get('/bookings', [CompanyOwnerBookingController::class, 'index']);
        Route::get('/bookings/stats', [CompanyOwnerBookingController::class, 'getStats']);
        Route::get('/bookings/{booking}', [CompanyOwnerBookingController::class, 'show']);
        Route::post('/bookings/{booking}/confirm', [CompanyOwnerBookingController::class, 'confirm']);
        Route::post('/bookings/{booking}/cancel', [CompanyOwnerBookingController::class, 'cancel']);
        Route::post('/bookings/{booking}/pick-up', [CompanyOwnerBookingController::class, 'pickUp']);
        Route::post('/bookings/{booking}/return', [CompanyOwnerBookingController::class, 'returnCar']);
        Route::post('/bookings/{booking}/complete', [CompanyOwnerBookingController::class, 'complete']);
        Route::post('/bookings/{booking}/no-show', [CompanyOwnerBookingController::class, 'noShow']);

        // Wallet management
        Route::get('/wallet', [CompanyOwnerWalletController::class, 'show']);
        Route::get('/wallet/transactions', [CompanyOwnerWalletController::class, 'transactions']);
        Route::post('/wallet/withdraw', [CompanyOwnerWalletController::class, 'requestWithdrawal']);
        Route::get('/wallet/withdrawals', [CompanyOwnerWalletController::class, 'withdrawalHistory']);
    });
});

// Admin Authentication routes
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
});

// Admin Protected routes
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Auth
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::put('/profile', [AdminAuthController::class, 'updateProfile']);
    Route::put('/password', [AdminAuthController::class, 'updatePassword']);

    // Dashboard
    Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);
    Route::get('/dashboard/recent-activity', [AdminDashboardController::class, 'recentActivity']);
    Route::get('/dashboard/chart', [AdminDashboardController::class, 'chartData']);

    // Users Management
    Route::get('/users', [AdminUsersController::class, 'index']);
    Route::post('/users', [AdminUsersController::class, 'store']);
    Route::get('/users/{user}', [AdminUsersController::class, 'show']);
    Route::put('/users/{user}', [AdminUsersController::class, 'update']);
    Route::delete('/users/{user}', [AdminUsersController::class, 'destroy']);

    // Professionals Management
    Route::get('/professionals', [AdminProfessionalsController::class, 'index']);
    Route::get('/professionals/{type}/{id}', [AdminProfessionalsController::class, 'show']);
    Route::put('/professionals/{type}/{id}', [AdminProfessionalsController::class, 'update']);
    Route::post('/professionals/{type}/{id}/verify', [AdminProfessionalsController::class, 'verify']);
    Route::post('/professionals/{type}/{id}/reject', [AdminProfessionalsController::class, 'reject']);
    Route::post('/professionals/{type}/{id}/toggle-active', [AdminProfessionalsController::class, 'toggleActive']);
    Route::delete('/professionals/{type}/{id}', [AdminProfessionalsController::class, 'destroy']);

    // Hotels Management
    Route::get('/hotels', [AdminHotelsController::class, 'index']);
    Route::get('/hotels/{hotel}', [AdminHotelsController::class, 'show']);
    Route::put('/hotels/{hotel}', [AdminHotelsController::class, 'update']);
    Route::post('/hotels/{hotel}/verify', [AdminHotelsController::class, 'verify']);
    Route::post('/hotels/{hotel}/toggle-active', [AdminHotelsController::class, 'toggleActive']);
    Route::delete('/hotels/{hotel}', [AdminHotelsController::class, 'destroy']);

    // Hotel Rooms Management
    Route::get('/hotels/{hotel}/rooms', [AdminHotelsController::class, 'rooms']);
    Route::post('/hotels/{hotel}/rooms', [AdminHotelsController::class, 'storeRoom']);
    Route::put('/hotels/{hotel}/rooms/{room}', [AdminHotelsController::class, 'updateRoom']);
    Route::delete('/hotels/{hotel}/rooms/{room}', [AdminHotelsController::class, 'destroyRoom']);

    // Hotel Reservations Management
    Route::get('/hotels/{hotel}/reservations', [AdminHotelsController::class, 'reservations']);
    Route::put('/hotels/{hotel}/reservations/{reservation}', [AdminHotelsController::class, 'updateReservation']);

    // Restaurants Management
    Route::get('/restaurants', [AdminRestaurantsController::class, 'index']);
    Route::get('/restaurants/{restaurant}', [AdminRestaurantsController::class, 'show']);
    Route::put('/restaurants/{restaurant}', [AdminRestaurantsController::class, 'update']);
    Route::post('/restaurants/{restaurant}/verify', [AdminRestaurantsController::class, 'verify']);
    Route::post('/restaurants/{restaurant}/toggle-active', [AdminRestaurantsController::class, 'toggleActive']);
    Route::delete('/restaurants/{restaurant}', [AdminRestaurantsController::class, 'destroy']);

    // Restaurant Tables Management
    Route::get('/restaurants/{restaurant}/tables', [AdminRestaurantsController::class, 'tables']);
    Route::post('/restaurants/{restaurant}/tables', [AdminRestaurantsController::class, 'storeTable']);
    Route::put('/restaurants/{restaurant}/tables/{table}', [AdminRestaurantsController::class, 'updateTable']);
    Route::delete('/restaurants/{restaurant}/tables/{table}', [AdminRestaurantsController::class, 'destroyTable']);

    // Restaurant Plats/Menu Management
    Route::get('/restaurants/{restaurant}/plats', [AdminRestaurantsController::class, 'plats']);
    Route::post('/restaurants/{restaurant}/plats', [AdminRestaurantsController::class, 'storePlat']);
    Route::put('/restaurants/{restaurant}/plats/{plat}', [AdminRestaurantsController::class, 'updatePlat']);
    Route::delete('/restaurants/{restaurant}/plats/{plat}', [AdminRestaurantsController::class, 'destroyPlat']);

    // Restaurant Reservations Management
    Route::get('/restaurants/{restaurant}/reservations', [AdminRestaurantsController::class, 'reservations']);
    Route::put('/restaurants/{restaurant}/reservations/{reservation}', [AdminRestaurantsController::class, 'updateReservation']);

    // Car Rentals Management
    Route::get('/car-rentals', [AdminCarRentalsController::class, 'index']);
    Route::get('/car-rentals/{carRental}', [AdminCarRentalsController::class, 'show']);
    Route::put('/car-rentals/{carRental}', [AdminCarRentalsController::class, 'update']);
    Route::post('/car-rentals/{carRental}/verify', [AdminCarRentalsController::class, 'verify']);
    Route::post('/car-rentals/{carRental}/toggle-active', [AdminCarRentalsController::class, 'toggleActive']);
    Route::delete('/car-rentals/{carRental}', [AdminCarRentalsController::class, 'destroy']);

    // Car Rental Cars Management
    Route::get('/car-rentals/{carRental}/cars', [AdminCarRentalsController::class, 'cars']);
    Route::post('/car-rentals/{carRental}/cars', [AdminCarRentalsController::class, 'storeCar']);
    Route::put('/car-rentals/{carRental}/cars/{car}', [AdminCarRentalsController::class, 'updateCar']);
    Route::delete('/car-rentals/{carRental}/cars/{car}', [AdminCarRentalsController::class, 'destroyCar']);

    // Car Rental Bookings Management
    Route::get('/car-rentals/{carRental}/bookings', [AdminCarRentalsController::class, 'bookings']);
    Route::put('/car-rentals/{carRental}/bookings/{booking}', [AdminCarRentalsController::class, 'updateBooking']);

    // Bookings Management
    Route::get('/bookings/hotels', [AdminBookingsController::class, 'hotelReservations']);
    Route::get('/bookings/hotels/{reservation}', [AdminBookingsController::class, 'showHotelReservation']);
    Route::put('/bookings/hotels/{reservation}', [AdminBookingsController::class, 'updateHotelReservation']);
    Route::get('/bookings/restaurants', [AdminBookingsController::class, 'tableReservations']);
    Route::get('/bookings/restaurants/{reservation}', [AdminBookingsController::class, 'showTableReservation']);
    Route::put('/bookings/restaurants/{reservation}', [AdminBookingsController::class, 'updateTableReservation']);
    Route::get('/bookings/cars', [AdminBookingsController::class, 'carBookings']);
    Route::get('/bookings/cars/{booking}', [AdminBookingsController::class, 'showCarBooking']);
    Route::put('/bookings/cars/{booking}', [AdminBookingsController::class, 'updateCarBooking']);

    // Admins Management (Super Admin only)
    Route::get('/admins', [AdminAdminsController::class, 'index']);
    Route::post('/admins', [AdminAdminsController::class, 'store']);
    Route::get('/admins/{admin}', [AdminAdminsController::class, 'show']);
    Route::put('/admins/{admin}', [AdminAdminsController::class, 'update']);
    Route::delete('/admins/{admin}', [AdminAdminsController::class, 'destroy']);
    Route::post('/admins/{admin}/toggle-active', [AdminAdminsController::class, 'toggleActive']);

    // Wallets & Transactions
    Route::get('/wallets', [AdminWalletController::class, 'index']);
    Route::get('/transactions', [AdminWalletController::class, 'transactions']);
    Route::get('/withdrawals', [AdminWalletController::class, 'withdrawals']);
    Route::post('/withdrawals/{withdrawal}/approve', [AdminWalletController::class, 'approveWithdrawal']);
    Route::post('/withdrawals/{withdrawal}/reject', [AdminWalletController::class, 'rejectWithdrawal']);
    Route::post('/withdrawals/{withdrawal}/complete', [AdminWalletController::class, 'completeWithdrawal']);

    // Settings
    Route::get('/settings', [AdminSettingsController::class, 'index']);
    Route::put('/settings', [AdminSettingsController::class, 'update']);

    // Legal Pages (Privacy Policy & Terms of Use)
    Route::get('/legal', [AdminSettingsController::class, 'getLegalContent']);
    Route::put('/legal', [AdminSettingsController::class, 'updateLegalContent']);

    // Wilayas
    Route::get('/wilayas', [AdminWilayaController::class, 'index']);
    Route::post('/wilayas', [AdminWilayaController::class, 'store']);
    Route::get('/wilayas/{wilaya}', [AdminWilayaController::class, 'show']);
    Route::put('/wilayas/{wilaya}', [AdminWilayaController::class, 'update']);
    Route::delete('/wilayas/{wilaya}', [AdminWilayaController::class, 'destroy']);
    Route::post('/wilayas/{wilaya}/image', [AdminWilayaController::class, 'uploadImage']);
    Route::delete('/wilayas/{wilaya}/image', [AdminWilayaController::class, 'deleteImage']);

    // Contact Messages
    Route::get('/contact-messages', [AdminContactMessageController::class, 'index']);
    Route::get('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show']);
    Route::post('/contact-messages/{contactMessage}/read', [AdminContactMessageController::class, 'markAsRead']);
    Route::post('/contact-messages/{contactMessage}/unread', [AdminContactMessageController::class, 'markAsUnread']);
    Route::delete('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'destroy']);
});

// Test email route (remove after testing)
Route::get('/test-email', function() {
    try {
        \Mail::raw('Test email from Hajz Algeria. SMTP is configured correctly!', function($message) {
            $message->to('hajzcontact@gmail.com')
                    ->subject('Hajz - SMTP Test Email');
        });
        return response()->json(['message' => 'Test email sent successfully']);
    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});
