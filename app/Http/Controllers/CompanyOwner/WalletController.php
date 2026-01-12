<?php

namespace App\Http\Controllers\CompanyOwner;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Models\AdminSetting;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request)
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company found.',
            ], 404);
        }

        $wallet = $company->getOrCreateWallet();

        return response()->json([
            'wallet' => $wallet,
            'stats' => [
                'available_balance' => $wallet->available_balance,
                'pending_balance' => $wallet->pending_balance,
                'total_earned' => $wallet->total_earned,
                'total_withdrawn' => $wallet->total_withdrawn,
            ],
            'commission_rate' => (float) AdminSetting::get('commission_rate', 10),
        ]);
    }

    public function transactions(Request $request)
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company found.',
            ], 404);
        }

        $wallet = $company->wallet;

        if (!$wallet) {
            return response()->json([
                'transactions' => [],
            ]);
        }

        $query = $wallet->transactions()
            ->with(['carBooking.car'])
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $transactions = $query->paginate(20);

        return response()->json($transactions);
    }

    public function requestWithdrawal(Request $request)
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company found.',
            ], 404);
        }

        $wallet = $company->wallet;

        if (!$wallet) {
            return response()->json([
                'message' => 'No wallet found.',
            ], 404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000',
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_holder_name' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        // Check available balance
        if ($wallet->available_balance < $validated['amount']) {
            return response()->json([
                'message' => 'Insufficient available balance.',
                'available_balance' => $wallet->available_balance,
            ], 422);
        }

        // Check for pending withdrawal
        $pendingWithdrawal = $wallet->withdrawalRequests()
            ->where('status', 'pending')
            ->first();

        if ($pendingWithdrawal) {
            return response()->json([
                'message' => 'You already have a pending withdrawal request.',
            ], 422);
        }

        // Hold the money (deduct from available balance)
        $wallet->holdForWithdrawal($validated['amount']);

        $withdrawalRequest = WithdrawalRequest::create([
            'company_wallet_id' => $wallet->id,
            'amount' => $validated['amount'],
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_holder_name' => $validated['account_holder_name'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Withdrawal request submitted successfully',
            'withdrawal_request' => $withdrawalRequest,
            'new_available_balance' => $wallet->fresh()->available_balance,
        ], 201);
    }

    public function withdrawalHistory(Request $request)
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json([
                'message' => 'No company found.',
            ], 404);
        }

        $wallet = $company->wallet;

        if (!$wallet) {
            return response()->json([
                'withdrawals' => [],
            ]);
        }

        $query = $wallet->withdrawalRequests()
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $withdrawals = $query->paginate(20);

        return response()->json($withdrawals);
    }
}
