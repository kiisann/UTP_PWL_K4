<?php

namespace Tests\Feature;

use App\Models\Console;
use App\Models\FnbOrder;
use App\Models\FnbOrderItem;
use App\Models\RentalSession;
use App\Services\SessionManagerService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsRentalData;
use Tests\TestCase;

class SessionManagerServiceTest extends TestCase
{
    use RefreshDatabase, BuildsRentalData;

    private SessionManagerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 13:00:00');
        $this->svc = app(SessionManagerService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Siapkan kasir + shift + kategori + 2 meja + paket 60 menit. */
    private function setUpStore(): array
    {
        $cashier = $this->makeCashier();
        $this->openShift($cashier);
        $cat = $this->makeCategory(10000);
        $a = $this->makeConsole($cat, 'A');
        $b = $this->makeConsole($cat, 'B');
        $pkg = $this->makePackage($cat, 60, 9000);

        return compact('cashier', 'cat', 'a', 'b', 'pkg');
    }

    public function test_start_paket_mengunci_meja_dan_mengisi_waktu_selesai(): void
    {
        ['cashier' => $u, 'a' => $a, 'pkg' => $pkg] = $this->setUpStore();

        $s = $this->svc->start($u, $a->id, 'package', $pkg->id, 'Budi');

        $this->assertSame(Console::OCCUPIED, $a->fresh()->status);
        $this->assertTrue($s->start_time->equalTo(Carbon::parse('2026-10-15 13:00:00')));
        $this->assertTrue($s->planned_end_time->equalTo(Carbon::parse('2026-10-15 14:00:00')));
        $this->assertEquals(9000, $s->rental_amount);
        $this->assertSame('INV-20261015-0001', $s->invoice_number);
    }

    public function test_start_ditolak_tanpa_shift_aktif(): void
    {
        $cashier = $this->makeCashier();
        $cat = $this->makeCategory();
        $a = $this->makeConsole($cat, 'A');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Belum ada shift aktif');
        $this->svc->start($cashier, $a->id, 'open');
    }

    public function test_start_ditolak_jika_meja_sedang_dipakai_atau_maintenance(): void
    {
        ['cashier' => $u, 'cat' => $cat, 'a' => $a] = $this->setUpStore();
        $maint = $this->makeConsole($cat, 'M', Console::MAINTENANCE);

        $this->svc->start($u, $a->id, 'open');

        try {
            $this->svc->start($u, $a->id, 'open');
            $this->fail('Seharusnya ditolak: meja dipakai');
        } catch (DomainException $e) {
            $this->assertStringContainsString('sedang dipakai', $e->getMessage());
        }

        $this->expectExceptionMessage('maintenance');
        $this->svc->start($u, $maint->id, 'open');
    }

    public function test_nomor_nota_berurutan_dalam_sehari(): void
    {
        ['cashier' => $u, 'a' => $a, 'b' => $b] = $this->setUpStore();

        $one = $this->svc->start($u, $a->id, 'open');
        $two = $this->svc->start($u, $b->id, 'open');

        $this->assertSame('INV-20261015-0001', $one->invoice_number);
        $this->assertSame('INV-20261015-0002', $two->invoice_number);
    }

    public function test_extend_menambah_menit_dan_biaya_dalam_satu_nota(): void
    {
        ['cashier' => $u, 'a' => $a, 'pkg' => $pkg] = $this->setUpStore();
        $s = $this->svc->start($u, $a->id, 'package', $pkg->id);

        // paket tambahan 60 menit (9.000) lalu 30 menit manual (30/60 x 10.000 = 5.000)
        $this->svc->extend($s, $pkg->id, null);
        $s = $this->svc->extend($s->fresh(), null, 30);

        $this->assertTrue($s->planned_end_time->equalTo(Carbon::parse('2026-10-15 15:30:00')));
        $this->assertSame(150, $s->total_minutes);
        $this->assertEquals(23000, $s->rental_amount);
        $this->assertEquals(23000, $s->grand_total);
        $this->assertSame(1, RentalSession::count());
    }

    public function test_extend_ditolak_untuk_open_bill(): void
    {
        ['cashier' => $u, 'a' => $a] = $this->setUpStore();
        $s = $this->svc->start($u, $a->id, 'open');

        $this->expectException(DomainException::class);
        $this->svc->extend($s, null, 15);
    }

    public function test_pindah_meja_tidak_mengubah_waktu_mulai(): void
    {
        ['cashier' => $u, 'a' => $a, 'b' => $b, 'pkg' => $pkg] = $this->setUpStore();
        $s = $this->svc->start($u, $a->id, 'package', $pkg->id);
        $startBefore = $s->start_time->copy();

        Carbon::setTestNow('2026-10-15 13:20:00');
        $s = $this->svc->move($s->fresh(), $b->id, true);

        $this->assertSame($b->id, $s->console_id);
        $this->assertTrue($s->start_time->equalTo($startBefore));
        $this->assertSame(Console::MAINTENANCE, $a->fresh()->status);
        $this->assertSame(Console::OCCUPIED, $b->fresh()->status);
        $this->assertStringContainsString('Pindah dari', $s->notes);
    }

    public function test_pindah_ke_meja_yang_dipakai_ditolak(): void
    {
        ['cashier' => $u, 'a' => $a, 'b' => $b] = $this->setUpStore();
        $s = $this->svc->start($u, $a->id, 'open');
        $this->svc->start($u, $b->id, 'open');

        $this->expectException(DomainException::class);
        $this->svc->move($s->fresh(), $b->id);
    }

    public function test_stop_open_bill_menghitung_minimum_dan_pembulatan(): void
    {
        ['cashier' => $u, 'a' => $a] = $this->setUpStore();
        $s = $this->svc->start($u, $a->id, 'open');

        Carbon::setTestNow('2026-10-15 13:10:00'); // main 10 menit, kena minimum 30 menit
        $s = $this->svc->stop($s->fresh());

        $this->assertSame(30, $s->total_minutes);
        $this->assertEquals(5000, $s->rental_amount);
        $this->assertNotNull($s->actual_end_time);
        $this->assertSame(RentalSession::RUNNING, $s->session_status); // baru completed saat checkout
    }

    public function test_stop_paket_memakai_harga_tetap(): void
    {
        ['cashier' => $u, 'a' => $a, 'pkg' => $pkg] = $this->setUpStore();
        $s = $this->svc->start($u, $a->id, 'package', $pkg->id);

        Carbon::setTestNow('2026-10-15 13:20:00');
        $s = $this->svc->stop($s->fresh());

        $this->assertEquals(9000, $s->rental_amount);
        $this->assertEquals(9000, $s->grand_total);
    }

    public function test_sesi_yang_sudah_di_stop_tidak_bisa_diubah_lagi(): void
    {
        ['cashier' => $u, 'a' => $a, 'b' => $b] = $this->setUpStore();
        $s = $this->svc->start($u, $a->id, 'open');
        $this->svc->stop($s->fresh());

        $this->expectException(DomainException::class);
        $this->svc->move($s->fresh(), $b->id);
    }

    public function test_void_membebaskan_meja_mengembalikan_stok_dan_tercatat(): void
    {
        ['cashier' => $u, 'a' => $a] = $this->setUpStore();
        $s = $this->svc->start($u, $a->id, 'open');

        // Simulasi pesanan F&B yang sudah memotong stok 10 -> 8.
        $product = $this->makeProduct(8);
        $order = FnbOrder::create([
            'order_number' => 'FNB-1',
            'rental_session_id' => $s->id,
            'shift_id' => $s->shift_id,
            'total_amount' => 10000,
            'order_status' => 'open',
        ]);
        FnbOrderItem::create([
            'fnb_order_id' => $order->id,
            'product_id' => $product->id,
            'qty' => 2,
            'unit_price' => 5000,
            'subtotal' => 10000,
        ]);

        $s = $this->svc->void($s->fresh(), 'Pelanggan batal main');

        $this->assertSame(RentalSession::CANCELLED, $s->session_status);
        $this->assertSame(RentalSession::VOID, $s->payment_status);
        $this->assertStringContainsString('VOID: Pelanggan batal main', $s->notes);
        $this->assertSame(Console::AVAILABLE, $a->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame('void', $order->fresh()->order_status);
    }

    public function test_void_tidak_menimpa_status_maintenance(): void
    {
        ['cashier' => $u, 'a' => $a, 'b' => $b] = $this->setUpStore();
        $s = $this->svc->start($u, $a->id, 'open');
        $this->svc->move($s->fresh(), $b->id, true); // meja A rusak -> maintenance

        $this->svc->void($s->fresh(), 'Batal');

        $this->assertSame(Console::MAINTENANCE, $a->fresh()->status);
        $this->assertSame(Console::AVAILABLE, $b->fresh()->status);
    }

    public function test_void_ditolak_jika_sudah_dibayar_atau_alasan_kosong(): void
    {
        ['cashier' => $u, 'a' => $a] = $this->setUpStore();
        $s = $this->svc->start($u, $a->id, 'open');

        try {
            $this->svc->void($s->fresh(), '   ');
            $this->fail('Alasan kosong harus ditolak');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Alasan', $e->getMessage());
        }

        $s->update(['payment_status' => RentalSession::PAID, 'session_status' => RentalSession::COMPLETED]);

        $this->expectException(DomainException::class);
        $this->svc->void($s->fresh(), 'Salah input');
    }
}
