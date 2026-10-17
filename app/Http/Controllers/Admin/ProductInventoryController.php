<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductInventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->orderBy('sku');

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        $products = $query->get();

        return view('admin.products.index', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku'        => 'required|string|max:30|unique:products,sku',
            'name'       => 'required|string|max:100',
            'category'   => 'required|in:makanan,minuman,camilan,food,beverage,snack',
            'cost_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'stock'      => 'required|integer|min:0',
        ]);

        $validated['sku']       = strtoupper($validated['sku']);
        $validated['is_active'] = true;

        Product::create($validated);

        return redirect()->route('admin.products.index')
            ->with('success', "Menu {$validated['name']} berhasil ditambahkan ke inventori.");
    }

    public function restock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'added_stock' => 'required|integer|min:1',
        ]);

        $product->increment('stock', $validated['added_stock']);

        return redirect()->route('admin.products.index')
            ->with('success', "Stok {$product->name} berhasil ditambah +{$validated['added_stock']} pcs.");
    }
}
