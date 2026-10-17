<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Memeriksa role pengguna (admin / cashier).
     * Selama halaman login belum dipasang pada tahap pengembangan, middleware ini
     * mengizinkan akses langsung agar halaman Admin bisa langsung diuji.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! auth()->check()) {
            if (Route::has('login')) {
                return redirect()->route('login');
            }

            return $next($request);
        }

        $user = auth()->user();
        if (! $user->is_active || ! in_array($user->role, $roles, true)) {
            abort(403, 'Akses ditolak. Halaman ini khusus untuk peran: ' . implode(', ', $roles));
        }

        return $next($request);
    }
}
