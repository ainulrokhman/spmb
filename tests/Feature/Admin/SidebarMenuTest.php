<?php

namespace Tests\Feature\Admin;

use App\Models\SchoolUnit;
use App\Models\User;
use App\Support\SystemAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class SidebarMenuTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    /**
     * Extract just the sidebar menu markup from a rendered page
     * (the whole nav block, including treeview submenus).
     */
    private function sidebarOf(string $html): string
    {
        $start = strpos($html, 'nav-sidebar');
        $end = strpos($html, '</nav>', $start === false ? 0 : $start);

        return ($start === false || $end === false) ? '' : substr($html, $start, $end - $start);
    }

    public function test_frequent_menus_come_before_submenus(): void
    {
        $html = $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->getContent();
        $sidebar = $this->sidebarOf($html);

        $positions = array_map(fn (string $label) => strpos($sidebar, $label), [
            'Verifikasi Pendaftaran', 'Pembayaran Formulir', 'Tagihan Daftar Ulang', 'Laporan SPMB',
            'Master Data', 'Berkas &amp; Formulir', 'Biaya', 'Pengguna &amp; Akses', 'Sistem',
        ]);

        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, array_values($positions), 'Sidebar order is not frequency-first');
        $this->assertSame(5, substr_count($sidebar, 'nav-treeview'));
    }

    public function test_submenu_opens_on_a_child_page(): void
    {
        $html = $this->actingAs($this->superAdmin())->get(route('admin.admission-tracks.index'))->getContent();

        $this->assertStringContainsString('menu-open', $this->sidebarOf($html));
    }

    public function test_panitia_sidebar_only_contains_accessible_menus(): void
    {
        $unit = SchoolUnit::factory()->create();
        $panitia = User::factory()->create();
        $panitia->assignRole(SystemAccess::PANITIA_ROLE);
        $panitia->schoolUnits()->attach($unit);

        $html = $this->actingAs($panitia)->get(route('admin.dashboard'))->getContent();
        $sidebar = $this->sidebarOf($html);

        foreach (['Verifikasi Pendaftaran', 'Pembayaran Formulir', 'Tagihan Daftar Ulang', 'Laporan SPMB',
            'Master Data', 'Unit Sekolah', 'Jalur Pendaftaran', 'Bidang Formulir', 'Nominal Biaya'] as $label) {
            $this->assertStringContainsString($label, $sidebar, "Panitia sidebar misses {$label}");
        }

        foreach (['Tahun Ajaran', 'Program', 'Jenis Berkas', 'Komponen Biaya', 'Pengguna &amp; Akses', 'Pengaturan'] as $label) {
            $this->assertStringNotContainsString($label, $sidebar, "Panitia sidebar should hide {$label}");
        }
    }
}
