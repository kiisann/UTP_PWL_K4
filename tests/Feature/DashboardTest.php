<?php

namespace Tests\Feature;

use App\Services\SessionManagerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsRentalData;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase, BuildsRentalData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-15 13:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_menampilkan_kartu_sesi_open_bill_dengan_estimasi_dan_tombol_void(): void
    {
        $u = $this->makeCashier();
        $this->openShift($u);
        $console = $this->makeConsole($this->makeCategory(10000), 'A');
        app(SessionManagerService::class)->start($u, $console->id, 'open', null, 'Budi');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Budi')
            ->assertSee('Total sementara')
            ->assertSee('estimatedRental', false)
            ->assertSee('Batalkan sesi (void)')
            ->assertSee('Batalkan Sesi');
    }

    public function test_live_status_memberi_estimasi_tagihan_open_bill_yang_berjalan(): void
    {
        $u = $this->makeCashier();
        $this->openShift($u);
        $console = $this->makeConsole($this->makeCategory(10000), 'A');
        app(SessionManagerService::class)->start($u, $console->id, 'open');

        Carbon::setTestNow('2026-10-15 13:40:00'); // 40 menit -> ditagih 45 menit

        $session = $this->getJson(route('api.consoles.live'))
            ->assertOk()
            ->json('consoles.0.session');

        $this->assertEquals(0, $session['rental_amount']);              // belum di-Stop
        $this->assertEquals(7500, $session['estimated_rental_amount']); // 45/60 x 10.000
        $this->assertEquals(7500, $session['estimated_grand_total']);
    }

    public function test_void_lewat_http_mewajibkan_alasan_dan_membebaskan_meja(): void
    {
        $u = $this->makeCashier();
        $this->openShift($u);
        $console = $this->makeConsole($this->makeCategory(), 'A');
        $s = app(SessionManagerService::class)->start($u, $console->id, 'open');

        $this->post(route('sessions.void', $s), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('occupied', $console->fresh()->status);

        $this->post(route('sessions.void', $s), ['reason' => 'Pelanggan batal'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $this->assertSame('available', $console->fresh()->status);
        $this->assertSame('void', $s->fresh()->payment_status);
    }
}
