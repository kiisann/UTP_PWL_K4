<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsoleCategory;
use App\Models\PricingPackage;
use Illuminate\Http\Request;

class PricingPackageController extends Controller
{
    public function index()
    {
        $packages   = PricingPackage::with('category')->orderBy('category_id')->orderBy('duration_minutes')->get();
        $categories = ConsoleCategory::orderBy('hourly_rate')->get();

        return view('admin.packages.index', compact('packages', 'categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id'      => 'required|exists:console_categories,id',
            'name'             => 'required|string|max:100',
            'duration_minutes' => 'required|integer|min:15',
            'price'            => 'required|numeric|min:0',
        ]);

        $validated['is_active'] = true;
        PricingPackage::create($validated);

        return redirect()->route('admin.packages.index')
            ->with('success', "Paket harga {$validated['name']} berhasil dibuat.");
    }

    public function toggleActive(PricingPackage $package)
    {
        $package->update(['is_active' => ! $package->is_active]);

        return redirect()->route('admin.packages.index')
            ->with('success', "Status paket {$package->name} diperbarui.");
    }

    public function destroy(PricingPackage $package)
    {
        $package->delete();

        return redirect()->route('admin.packages.index')
            ->with('success', 'Paket harga berhasil dihapus.');
    }
}
