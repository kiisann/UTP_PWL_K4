<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RentalSession;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $sessionsQuery = RentalSession::with(['console.category', 'payment', 'user'])
            ->where('payment_status', RentalSession::PAID)
            ->latest('start_time');

        if ($request->filled('billing_mode') && $request->billing_mode !== 'all') {
            $sessionsQuery->where('billing_mode', $request->billing_mode);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $sessionsQuery->whereBetween('start_time', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59',
            ]);
        }

        $completedSessions = $sessionsQuery->paginate(20);

        $voidLogs = RentalSession::with(['console.category', 'user'])
            ->where('payment_status', RentalSession::VOID)
            ->latest('updated_at')
            ->limit(25)
            ->get();

        return view('admin.reports.index', compact('completedSessions', 'voidLogs'));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $sessions = RentalSession::with(['console.category', 'payment', 'user'])
            ->where('payment_status', RentalSession::PAID)
            ->latest('start_time')
            ->get();

        $filename = 'laporan_transaksi_rental_ps_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($sessions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'No Invoice',
                'Waktu Mulai',
                'Meja PS',
                'Kategori',
                'Pelanggan',
                'Mode Billing',
                'Durasi (Menit)',
                'Biaya Sewa PS',
                'Biaya F&B',
                'Diskon',
                'Grand Total',
                'Metode Bayar',
                'Kasir',
            ]);

            foreach ($sessions as $s) {
                fputcsv($handle, [
                    $s->invoice_number,
                    $s->start_time->format('Y-m-d H:i:s'),
                    $s->console->code ?? '-',
                    $s->console->category->name ?? '-',
                    $s->customer_name ?? 'Umum',
                    strtoupper($s->billing_mode),
                    $s->total_minutes,
                    $s->rental_amount,
                    $s->fnb_amount,
                    $s->discount_amount,
                    $s->grand_total,
                    strtoupper($s->payment->payment_method ?? 'CASH'),
                    $s->user->name ?? '-',
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
