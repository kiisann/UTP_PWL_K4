<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\Console;
use App\Models\FnbOrder;
use App\Models\PricingPackage;
use App\Models\Product;
use App\Models\RentalSession;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Siklus sesi bermain: start, extend, move, stop, void (Bab 2 dan 4.3 dokumen).
 *
 * Semua operasi berjalan di dalam DB::transaction dan mengunci baris yang
 * disentuh (lockForUpdate), supaya dua klik bersamaan tidak merusak data.
 * Aturan yang dilanggar dilempar sebagai DomainException (pesannya aman
 * ditampilkan ke kasir).
 *
 * Catatan alur: setelah stop(), sesi belum "completed". Ia ditandai lewat
 * actual_end_time yang terisi, dan baru menjadi completed + paid saat
 * checkout (bagian Niko), yang juga mengembalikan meja ke 'available'.
 */
class SessionManagerService
{
    public function __construct(private BillingCalculatorService $billing)
    {
    }

    /**
     * Mulai sesi baru. Mode 'package' wajib menyertakan $packageId.
     */
    public function start(
        User $operator,
        int $consoleId,
        string $billingMode,
        ?int $packageId = null,
        ?string $customerName = null
    ): RentalSession {
        return DB::transaction(function () use ($operator, $consoleId, $billingMode, $packageId, $customerName) {
            $shift = CashierShift::open()->latest('opened_at')->first();
            if (! $shift) {
                throw new DomainException('Belum ada shift aktif. Buka shift dulu.');
            }

            $console = Console::whereKey($consoleId)->lockForUpdate()->firstOrFail();
            if ($console->status === Console::MAINTENANCE) {
                throw new DomainException("{$console->name} sedang maintenance.");
            }
            if ($console->status !== Console::AVAILABLE) {
                throw new DomainException("{$console->name} sedang dipakai.");
            }

            $now = now();
            $plannedEnd = null;
            $package = null;
            $rental = 0.0;
            $totalMinutes = 0;

            if ($billingMode === RentalSession::MODE_PACKAGE) {
                $package = PricingPackage::active()
                    ->where('category_id', $console->category_id)
                    ->find($packageId);

                if (! $package) {
                    throw new DomainException('Paket tidak valid untuk kategori meja ini.');
                }

                $plannedEnd = $now->copy()->addMinutes($package->duration_minutes);
                $rental = (float) $package->price;
                $totalMinutes = $package->duration_minutes;
            } elseif ($billingMode !== RentalSession::MODE_OPEN) {
                throw new DomainException('Mode tagihan tidak dikenal.');
            }

            $session = RentalSession::create([
                'invoice_number' => $this->nextInvoiceNumber($now),
                'console_id' => $console->id,
                'shift_id' => $shift->id,
                'user_id' => $operator->id,
                'customer_name' => $customerName,
                'billing_mode' => $billingMode,
                'package_id' => $package?->id,
                'start_time' => $now,
                'planned_end_time' => $plannedEnd,
                'total_minutes' => $totalMinutes,
                'rental_amount' => $rental,
                'fnb_amount' => 0,
                'discount_amount' => 0,
                'grand_total' => $this->billing->grandTotal($rental),
                'payment_status' => RentalSession::UNPAID,
                'session_status' => RentalSession::RUNNING,
            ]);

            $console->update(['status' => Console::OCCUPIED]);

            return $session->load('console.category');
        });
    }

    /**
     * Tambah waktu pada sesi Paket: lewat paket tambahan ($packageId)
     * atau menit manual ($minutes, harga dihitung dari tarif per jam).
     */
    public function extend(RentalSession $session, ?int $packageId = null, ?int $minutes = null): RentalSession
    {
        return DB::transaction(function () use ($session, $packageId, $minutes) {
            $s = $this->lockRunning($session);

            if ($s->billing_mode !== RentalSession::MODE_PACKAGE) {
                throw new DomainException('Tambah waktu hanya untuk sesi Paket. Open Bill tinggal lanjut main.');
            }

            $category = $s->console->category;

            if ($packageId) {
                $package = PricingPackage::active()
                    ->where('category_id', $category->id)
                    ->find($packageId);

                if (! $package) {
                    throw new DomainException('Paket tambahan tidak valid untuk kategori meja ini.');
                }

                $addMinutes = $package->duration_minutes;
                $addPrice = (float) $package->price;
                $label = $package->name;
            } elseif ($minutes && $minutes > 0) {
                $addMinutes = $minutes;
                $addPrice = round(($minutes / 60) * (float) $category->hourly_rate, 2);
                $label = "{$minutes} menit manual";
            } else {
                throw new DomainException('Pilih paket tambahan atau isi menit manual.');
            }

            // Sesuai dokumen: menit ekstra ditambahkan ke planned_end_time
            $s->planned_end_time = ($s->planned_end_time ?? $s->start_time)->copy()->addMinutes($addMinutes);
            $s->total_minutes = $s->total_minutes + $addMinutes;
            $s->rental_amount = round((float) $s->rental_amount + $addPrice, 2);
            $s->grand_total = $this->billing->grandTotal(
                (float) $s->rental_amount,
                (float) $s->fnb_amount,
                (float) $s->discount_amount
            );
            $s->notes = $this->appendNote($s->notes, "Tambah waktu {$label} (+{$addMinutes} menit)");
            $s->save();

            return $s->load('console.category');
        });
    }

    /**
     * Pindah meja tanpa menyentuh start_time. Meja lama kembali 'available'
     * (atau 'maintenance' kalau $oldToMaintenance true).
     */
    public function move(RentalSession $session, int $targetConsoleId, bool $oldToMaintenance = false): RentalSession
    {
        return DB::transaction(function () use ($session, $targetConsoleId, $oldToMaintenance) {
            $s = $this->lockRunning($session);

            if ((int) $s->console_id === $targetConsoleId) {
                throw new DomainException('Meja tujuan sama dengan meja sekarang.');
            }

            // Kunci dua meja dengan urutan id yang sama, supaya tidak saling menunggu (deadlock)
            $consoles = Console::whereIn('id', [$s->console_id, $targetConsoleId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $old = $consoles->get((int) $s->console_id);
            $new = $consoles->get($targetConsoleId);

            if (! $new) {
                throw new DomainException('Meja tujuan tidak ditemukan.');
            }
            if ($new->status !== Console::AVAILABLE) {
                throw new DomainException("{$new->name} tidak tersedia.");
            }

            $old->update(['status' => $oldToMaintenance ? Console::MAINTENANCE : Console::AVAILABLE]);
            $new->update(['status' => Console::OCCUPIED]);

            $s->console_id = $new->id;
            $s->notes = $this->appendNote($s->notes, "Pindah dari {$old->name} ke {$new->name}");
            $s->save();

            return $s->load('console.category');
        });
    }

    /**
     * Hentikan waktu bermain dan hitung tagihan akhir.
     * Open Bill dihitung lewat BillingCalculatorService; Paket memakai harga tetapnya.
     */
    public function stop(RentalSession $session): RentalSession
    {
        return DB::transaction(function () use ($session) {
            $s = $this->lockRunning($session);
            $end = now();
            $s->actual_end_time = $end;

            if ($s->billing_mode === RentalSession::MODE_OPEN) {
                $hasil = $this->billing->forSession($s, $end);
                $s->total_minutes = $hasil['total_minutes'];
                $s->rental_amount = $hasil['rental_amount'];
                $s->grand_total = $hasil['grand_total'];
            } else {
                $s->grand_total = $this->billing->grandTotal(
                    (float) $s->rental_amount,
                    (float) $s->fnb_amount,
                    (float) $s->discount_amount
                );
            }

            $s->save();

            return $s->load('console.category');
        });
    }

    /**
     * Batalkan (void) sesi yang BELUM dibayar: meja dibebaskan, stok F&B yang sudah
     * dipotong dikembalikan, dan alasan dicatat di notes untuk void log admin.
     * Sesi yang sudah lunas tidak bisa di-void di sini (butuh pembalikan pembayaran).
     */
    public function void(RentalSession $session, string $reason): RentalSession
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Alasan pembatalan wajib diisi.');
        }

        return DB::transaction(function () use ($session, $reason) {
            $s = RentalSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($s->session_status !== RentalSession::RUNNING || $s->payment_status !== RentalSession::UNPAID) {
                throw new DomainException('Hanya sesi yang belum dibayar yang bisa dibatalkan.');
            }

            // Bebaskan meja, tapi jangan menimpa status maintenance.
            $console = Console::whereKey($s->console_id)->lockForUpdate()->first();
            if ($console && $console->status === Console::OCCUPIED) {
                $console->update(['status' => Console::AVAILABLE]);
            }

            // Kembalikan stok dari pesanan F&B meja ini.
            $orders = FnbOrder::where('rental_session_id', $s->id)
                ->where('order_status', '!=', 'void')
                ->with('items')
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    Product::whereKey($item->product_id)->increment('stock', $item->qty);
                }
                $order->update(['order_status' => 'void']);
            }

            if ($s->actual_end_time === null) {
                $s->actual_end_time = now();
            }
            $s->session_status = RentalSession::CANCELLED;
            $s->payment_status = RentalSession::VOID;
            $s->notes = $this->appendNote($s->notes, "VOID: {$reason}");
            $s->save();

            return $s->load('console.category');
        });
    }

    /** Ambil sesi dengan kunci baris; pastikan masih berjalan dan belum di-stop. */
    private function lockRunning(RentalSession $session): RentalSession
    {
        $locked = RentalSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

        if ($locked->session_status !== RentalSession::RUNNING || $locked->actual_end_time !== null) {
            throw new DomainException('Sesi ini sudah dihentikan atau selesai.');
        }

        return $locked;
    }

    /** Nomor nota harian: INV-20261015-0001, INV-20261015-0002, ... */
    private function nextInvoiceNumber(CarbonInterface $date): string
    {
        $prefix = 'INV-' . $date->format('Ymd') . '-';

        $last = RentalSession::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function appendNote(?string $notes, string $line): string
    {
        $stamp = now()->format('d/m H:i');

        return trim(($notes ? $notes . "\n" : '') . "[{$stamp}] {$line}");
    }
}
