<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Console;
use App\Models\ConsoleCategory;
use Illuminate\Http\Request;

class ConsoleManagementController extends Controller
{
    public function index(Request $request)
    {
        $categories = ConsoleCategory::withCount('consoles')->orderBy('hourly_rate')->get();

        $consolesQuery = Console::with('category')->orderBy('code');
        if ($request->filled('category_id') && $request->category_id !== 'all') {
            $consolesQuery->where('category_id', $request->category_id);
        }
        $consoles = $consolesQuery->get();

        return view('admin.consoles.index', compact('categories', 'consoles'));
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:50',
            'hourly_rate'        => 'required|numeric|min:0',
            'min_charge_minutes' => 'required|integer|min:1',
            'rounding_minutes'   => 'required|integer|min:1',
            'description'        => 'nullable|string|max:500',
        ]);

        ConsoleCategory::create($validated);

        return redirect()->route('admin.consoles.index')
            ->with('success', 'Kategori konsol berhasil ditambahkan.');
    }

    public function updateCategory(Request $request, ConsoleCategory $category)
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:50',
            'hourly_rate'        => 'required|numeric|min:0',
            'min_charge_minutes' => 'required|integer|min:1',
            'rounding_minutes'   => 'required|integer|min:1',
            'description'        => 'nullable|string|max:500',
        ]);

        $category->update($validated);

        return redirect()->route('admin.consoles.index')
            ->with('success', "Tarif kategori {$category->name} berhasil diperbarui.");
    }

    public function storeConsole(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:console_categories,id',
            'code'        => 'required|string|max:20|unique:consoles,code',
            'name'        => 'required|string|max:50',
        ]);

        $validated['code']   = strtoupper($validated['code']);
        $validated['status'] = Console::AVAILABLE;

        Console::create($validated);

        return redirect()->route('admin.consoles.index')
            ->with('success', "Unit {$validated['code']} berhasil ditambahkan.");
    }

    public function toggleMaintenance(Console $console)
    {
        if ($console->status === Console::OCCUPIED) {
            return back()->with('error', "Meja {$console->code} sedang digunakan sesi bermain aktif.");
        }

        $console->update([
            'status' => $console->status === Console::MAINTENANCE ? Console::AVAILABLE : Console::MAINTENANCE,
        ]);

        return redirect()->route('admin.consoles.index')
            ->with('success', "Status {$console->code} diubah menjadi " . strtoupper($console->status) . ".");
    }
}
