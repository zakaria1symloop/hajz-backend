<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotelWallet;
use App\Models\RestaurantWallet;
use App\Models\CompanyWallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Models\Payment;
use App\Models\AdminSetting;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->get('type', 'all');

        $hotelWallets = HotelWallet::with('hotel')->get()->map(function ($w) {
            return [
                'id' => $w->id,
                'type' => 'hotel',
                'business_name' => $w->hotel?->name ?? 'Unknown Hotel',
                'pending_balance' => $w->pending_balance,
                'available_balance' => $w->available_balance,
                'total_earned' => $w->total_earned,
                'total_withdrawn' => $w->total_withdrawn,
            ];
        });

        $restaurantWallets = RestaurantWallet::with('restaurant')->get()->map(function ($w) {
            return [
                'id' => $w->id,
                'type' => 'restaurant',
                'business_name' => $w->restaurant?->name ?? 'Unknown Restaurant',
                'pending_balance' => $w->pending_balance,
                'available_balance' => $w->available_balance,
                'total_earned' => $w->total_earned,
                'total_withdrawn' => $w->total_withdrawn,
            ];
        });

        $companyWallets = CompanyWallet::with('company')->get()->map(function ($w) {
            return [
                'id' => $w->id,
                'type' => 'car_rental',
                'business_name' => $w->company?->name ?? 'Unknown Company',
                'pending_balance' => $w->pending_balance,
                'available_balance' => $w->available_balance,
                'total_earned' => $w->total_earned,
                'total_withdrawn' => $w->total_withdrawn,
            ];
        });

        $allWallets = collect()
            ->merge($hotelWallets)
            ->merge($restaurantWallets)
            ->merge($companyWallets)
            ->sortByDesc('total_earned')
            ->values();

        if ($type !== 'all') {
            $allWallets = $allWallets->where('type', $type)->values();
        }

        // Calculate totals
        $totalEarnedByProfessionals = HotelWallet::sum('total_earned') + RestaurantWallet::sum('total_earned') + CompanyWallet::sum('total_earned');

        // Calculate total payments received (before commission)
        $totalPayments = Payment::where('status', 'completed')->sum('amount');

        // Commission earned by platform = Total payments - What professionals earned
        $commissionRate = (float) AdminSetting::get('commission_rate', 10);
        $totalCommissions = $totalPayments - $totalEarnedByProfessionals;

        // Amount to pay to professionals = Available balance + Pending balance
        $amountToPay = HotelWallet::sum('available_balance') + RestaurantWallet::sum('available_balance') + CompanyWallet::sum('available_balance');

        $totals = [
            'total_pending' => HotelWallet::sum('pending_balance') + RestaurantWallet::sum('pending_balance') + CompanyWallet::sum('pending_balance'),
            'total_available' => $amountToPay,
            'total_earned' => $totalEarnedByProfessionals,
            'total_withdrawn' => HotelWallet::sum('total_withdrawn') + RestaurantWallet::sum('total_withdrawn') + CompanyWallet::sum('total_withdrawn'),
            'total_payments' => $totalPayments,
            'total_commissions' => $totalCommissions,
            'commission_rate' => $commissionRate,
            'amount_to_pay' => $amountToPay,
        ];

        return response()->json([
            'wallets' => $allWallets,
            'totals' => $totals,
        ]);
    }

    public function transactions(Request $request)
    {
        $query = WalletTransaction::with(['wallet.hotel', 'reservation'])
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by hotel
        if ($request->has('hotel_id')) {
            $query->whereHas('wallet', function ($q) use ($request) {
                $q->where('hotel_id', $request->hotel_id);
            });
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $transactions = $query->paginate(50);

        return response()->json($transactions);
    }

    public function withdrawals(Request $request)
    {
        $query = WithdrawalRequest::with(['wallet.hotel', 'restaurantWallet.restaurant', 'companyWallet.company', 'processedByAdmin'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $withdrawals = $query->paginate(20);

        // Add business info to each withdrawal
        $withdrawals->getCollection()->transform(function ($w) {
            if ($w->hotel_wallet_id && $w->wallet) {
                $w->business_type = 'hotel';
                $w->business_name = $w->wallet->hotel?->name ?? 'Unknown Hotel';
            } elseif ($w->restaurant_wallet_id && $w->restaurantWallet) {
                $w->business_type = 'restaurant';
                $w->business_name = $w->restaurantWallet->restaurant?->name ?? 'Unknown Restaurant';
            } elseif ($w->company_wallet_id && $w->companyWallet) {
                $w->business_type = 'car_rental';
                $w->business_name = $w->companyWallet->company?->name ?? 'Unknown Company';
            }
            return $w;
        });

        // Calculate pending totals
        $pendingTotal = WithdrawalRequest::where('status', 'pending')->sum('amount');

        return response()->json([
            'withdrawals' => $withdrawals,
            'pending_total' => $pendingTotal,
        ]);
    }

    public function approveWithdrawal(Request $request, WithdrawalRequest $withdrawal)
    {
        if (!$withdrawal->isPending()) {
            return response()->json([
                'message' => 'Only pending withdrawals can be approved.',
            ], 422);
        }

        $withdrawal->approve($request->user()->id);

        return response()->json([
            'message' => 'Withdrawal approved. Ready for processing.',
            'withdrawal' => $withdrawal->fresh()->load('wallet.hotel'),
        ]);
    }

    public function rejectWithdrawal(Request $request, WithdrawalRequest $withdrawal)
    {
        if (!$withdrawal->isPending()) {
            return response()->json([
                'message' => 'Only pending withdrawals can be rejected.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $withdrawal->reject($request->user()->id, $validated['reason']);

        return response()->json([
            'message' => 'Withdrawal rejected',
            'withdrawal' => $withdrawal->fresh()->load('wallet.hotel'),
        ]);
    }

    public function completeWithdrawal(Request $request, WithdrawalRequest $withdrawal)
    {
        if (!$withdrawal->isApproved()) {
            return response()->json([
                'message' => 'Only approved withdrawals can be completed.',
            ], 422);
        }

        // Get the appropriate wallet
        $wallet = $withdrawal->getWallet();
        if (!$wallet) {
            return response()->json([
                'message' => 'Wallet not found.',
            ], 404);
        }

        // Verify sufficient balance
        if ($wallet->available_balance < $withdrawal->amount) {
            return response()->json([
                'message' => 'Insufficient balance in wallet.',
            ], 422);
        }

        $withdrawal->complete();

        return response()->json([
            'message' => 'Withdrawal completed and funds deducted.',
            'withdrawal' => $withdrawal->fresh()->load(['wallet.hotel', 'restaurantWallet.restaurant', 'companyWallet.company']),
        ]);
    }
}
