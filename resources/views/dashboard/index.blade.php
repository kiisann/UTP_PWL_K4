@extends('layouts.app')

@section('title', 'Dashboard Kasir')

@section('content')
    {{-- Header atas: dikerjakan Sultan --}}
    @include('dashboard._header')

    {{-- State "selected" dipakai bersama oleh grid (kiri) dan panel tagihan (kanan) --}}
    <div x-data="{ selected: null }" class="flex min-h-0 flex-1">

        {{-- Panel kiri 70%: grid kartu meja --}}
        <main class="w-[70%] overflow-y-auto p-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @forelse ($consoles as $console)
                    @include('dashboard._console-card', ['console' => $console])
                @empty
                    <p class="col-span-full text-center text-slate-500">
                        Belum ada data meja. Jalankan: php artisan migrate:fresh --seed
                    </p>
                @endforelse
            </div>
        </main>

        {{-- Panel kanan 30%: detail tagihan & pembayaran, dikerjakan Niko --}}
        @include('dashboard._billing-panel')
    </div>
@endsection
