<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dani (junior chef, user 12 on production) has preferred_language = 'id'.
 * Until now that bought her one translated page: everything else, including
 * the sidebar she sees on every screen, was English. Worse, the Profile pages
 * were already wrapped in __() with no Indonesian to resolve to, so the
 * plumbing looked done and rendered English anyway.
 *
 * These assert the language a chef set to Indonesian actually sees. They fail
 * the moment a string is added to one of her pages without a translation.
 */
class IndonesianTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function indonesianChef(): User
    {
        return User::factory()->create([
            'role'               => User::ROLE_JUNIOR_CHEF,
            'preferred_language' => 'id',
        ]);
    }

    public function test_the_sidebar_stays_in_english_while_the_page_is_translated(): void
    {
        // The sidebar is the team's shared vocabulary — the Owner and both Head
        // Chefs work in English, so a nav label has to read the same on every
        // screen. Checked on the Leave page because none of these words appear
        // in its own content, so a hit can only have come from the sidebar.
        $this->actingAs($this->indonesianChef())
            ->get(route('leave.index'))
            ->assertOk()
            ->assertSee('Stock-take')          // nav, English
            ->assertSee('Tally Check')         // nav, English
            ->assertSee('Production')          // nav, English
            ->assertDontSee('Hitung Stok')     // its Indonesian never reaches the nav
            ->assertDontSee('Cek Hitungan')
            ->assertSee('Ajukan Cuti');        // ...while the page itself is translated
    }

    public function test_the_stock_take_sheet_is_translated(): void
    {
        $this->actingAs($this->indonesianChef())
            ->get(route('stock-take.create'))
            ->assertOk()
            ->assertSee('Hitung Stok Baru')            // New stock-take
            ->assertSee('Stok saat ini')               // Current stock
            ->assertSee('Masuk')                       // In
            ->assertSee('Keluar')                      // Out
            ->assertSee('Sisa')                        // Balance
            ->assertSee('Simpan hitung stok');         // Save stock-take
    }

    public function test_the_tally_sheet_is_translated(): void
    {
        // The count table only renders when there is something to count, and
        // the column headers live inside it.
        \App\Models\InventoryItem::create([
            'name'             => 'Bawang merah',
            'category'         => 'Sayuran',
            'unit'             => 'kg',
            'quantity_on_hand' => 3,
            'unit_cost'        => 6.00,
        ]);

        $this->actingAs($this->indonesianChef())
            ->get(route('tally.create'))
            ->assertOk()
            ->assertSee('Cek Hitungan Baru')           // New tally check
            ->assertSee('Tanggal hitung')              // Count date
            ->assertSee('Dihitung')                    // Counted
            ->assertSee('Simpan cek hitungan');        // Save tally check
    }

    public function test_the_profile_page_is_translated(): void
    {
        // Every string here was already wrapped in __() and every one of them
        // rendered in English, because none had an entry in lang/id.json.
        $this->actingAs($this->indonesianChef())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Informasi Profil')            // Profile Information
            ->assertSee('Kata Sandi')                  // Password
            ->assertSee('Bahasa Tampilan')             // Display Language
            ->assertSee('Hapus Akun');                 // Delete Account
    }

    public function test_the_leave_page_is_translated(): void
    {
        $this->actingAs($this->indonesianChef())
            ->get(route('leave.index'))
            ->assertOk()
            ->assertSee('Ajukan Cuti')                  // Apply for Leave
            ->assertSee('Alasan')                       // Reason
            ->assertSee('Dokumen pendukung')            // Supporting documents
            ->assertSee('Kirim pengajuan');             // Submit application
    }

    public function test_the_feedback_page_is_translated(): void
    {
        $this->actingAs($this->indonesianChef())
            ->get(route('feedback.index'))
            ->assertOk()
            ->assertSee('Beri Masukan')                 // Give Feedback
            ->assertSee('Komentar')                     // Comment
            ->assertSee('Kirim secara anonim')          // Send anonymously
            ->assertSee('Masukan Anda');                // Your Feedback
    }

    public function test_the_support_page_is_translated(): void
    {
        $this->actingAs($this->indonesianChef())
            ->get(route('support.index'))
            ->assertOk()
            ->assertSee('Deskripsi Masalah')            // Problem Description
            ->assertSee('Lampirkan Media')              // Attach Media
            ->assertSee('Kirim Laporan')                // Send Report
            // The page advertised "Max 1 GB" while validation capped at 128 MB.
            ->assertSee('128 MB')
            ->assertDontSee('1 GB');
    }

    public function test_the_about_page_is_translated(): void
    {
        $this->actingAs($this->indonesianChef())
            ->get(route('about.index'))
            ->assertOk()
            ->assertSee('Tentang sistem')               // About the system
            ->assertSee('Versi saat ini')               // Current version
            ->assertSee('Riwayat rilis');               // Release history
    }

    public function test_an_english_speaking_chef_is_unaffected(): void
    {
        $english = User::factory()->create([
            'role'               => User::ROLE_JUNIOR_CHEF,
            'preferred_language' => null,
        ]);

        $this->actingAs($english)
            ->get(route('stock-take.create'))
            ->assertOk()
            ->assertSee('New stock-take')
            ->assertSee('Save stock-take')
            ->assertDontSee('Hitung Stok Baru');
    }

    public function test_every_key_the_views_ask_for_has_an_indonesian_answer(): void
    {
        $found = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($files as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $source = file_get_contents($file->getPathname());

            // Both quote styles: a string containing an apostrophe is written
            // with double quotes to avoid escaping it, and those keys need
            // translating just the same.
            if (preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*\)/", $source, $m)) {
                foreach ($m[1] as $key) {
                    $found[str_replace("\\'", "'", $key)] = true;
                }
            }
            if (preg_match_all('/__\(\s*"((?:[^"\\\\]|\\\\.)*)"\s*\)/', $source, $m)) {
                foreach ($m[1] as $key) {
                    $found[str_replace('\\"', '"', $key)] = true;
                }
            }
        }

        // Sidebar nav labels are intentionally excluded: they stay English for
        // every account, so they are not translatable strings and must not be
        // demanded here. See test_the_sidebar_stays_in_english_...

        $translated = json_decode(file_get_contents(lang_path('id.json')), true);
        $missing    = array_diff(array_keys($found), array_keys($translated));

        $this->assertSame([], array_values($missing),
            'These strings render in English for an Indonesian account — add them to lang/id.json.');
    }
}
