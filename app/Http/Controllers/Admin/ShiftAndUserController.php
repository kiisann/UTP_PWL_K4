<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ShiftAndUserController extends Controller
{
    public function index()
    {
        $users  = User::orderBy('role')->orderBy('name')->get();
        $shifts = CashierShift::with('user')->latest('opened_at')->paginate(15);

        return view('admin.shifts.index', compact('users', 'shifts'));
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:users,username',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:admin,cashier',
        ]);

        User::create([
            'name'      => $validated['name'],
            'username'  => strtolower($validated['username']),
            'password'  => Hash::make($validated['password']),
            'role'      => $validated['role'],
            'is_active' => true,
        ]);

        return redirect()->route('admin.shifts.index')
            ->with('success', "Akun @{$validated['username']} berhasil dibuat.");
    }

    public function toggleUserActive(User $user)
    {
        $user->update(['is_active' => ! $user->is_active]);

        return redirect()->route('admin.shifts.index')
            ->with('success', "Status akun @{$user->username} berhasil diperbarui.");
    }
}
