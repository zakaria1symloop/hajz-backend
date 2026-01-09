<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAdminsController extends Controller
{
    public function index(Request $request)
    {
        // Only super_admin can view other admins
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Super admin access required.',
            ], 403);
        }

        $query = Admin::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $admins = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($admins);
    }

    public function show(Admin $admin)
    {
        return response()->json($admin);
    }

    public function store(Request $request)
    {
        // Only super_admin can create admins
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Super admin access required.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|min:8',
            'role' => 'required|in:super_admin,admin,moderator',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $admin = Admin::create($validated);

        return response()->json([
            'message' => 'Admin created successfully',
            'admin' => $admin,
        ], 201);
    }

    public function update(Request $request, Admin $admin)
    {
        // Only super_admin can update admins
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Super admin access required.',
            ], 403);
        }

        // Prevent modifying own super_admin role
        if ($admin->id === $request->user()->id && $request->has('role') && $request->role !== 'super_admin') {
            return response()->json([
                'message' => 'Cannot change your own role.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:admins,email,' . $admin->id,
            'password' => 'nullable|min:8',
            'role' => 'sometimes|in:super_admin,admin,moderator',
            'is_active' => 'sometimes|boolean',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $admin->update($validated);

        return response()->json([
            'message' => 'Admin updated successfully',
            'admin' => $admin->fresh(),
        ]);
    }

    public function destroy(Request $request, Admin $admin)
    {
        // Only super_admin can delete admins
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Super admin access required.',
            ], 403);
        }

        // Prevent deleting yourself
        if ($admin->id === $request->user()->id) {
            return response()->json([
                'message' => 'Cannot delete your own account.',
            ], 422);
        }

        $admin->delete();

        return response()->json([
            'message' => 'Admin deleted successfully',
        ]);
    }

    public function toggleActive(Request $request, Admin $admin)
    {
        // Only super_admin can toggle admin status
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Super admin access required.',
            ], 403);
        }

        // Prevent deactivating yourself
        if ($admin->id === $request->user()->id) {
            return response()->json([
                'message' => 'Cannot deactivate your own account.',
            ], 422);
        }

        $admin->update([
            'is_active' => !$admin->is_active,
        ]);

        return response()->json([
            'message' => $admin->is_active ? 'Admin activated' : 'Admin deactivated',
            'admin' => $admin->fresh(),
        ]);
    }
}
