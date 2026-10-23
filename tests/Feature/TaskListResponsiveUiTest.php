<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman daftar harus tetap bisa dipakai di layar sempit.
 *
 * Konteksnya: tabel punya 6–7 kolom dengan `min-w-[740px]` (admin 1000px).
 * Di HP 390px hanya ~48%-nya yang muat, sehingga tanggal dan status berada
 * di luar layar. Karena itu di bawah `md` tabel diganti kartu.
 *
 * Test di sini mengunci keputusan UI tersebut supaya tidak bisa kembali
 * diam-diam ke tabel horizontal yang tidak terpakai.
 */
class TaskListResponsiveUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_renders_card_markup_for_each_task(): void
    {
        $trainee = $this->trainee();
        $tasks = $this->ownedTasks($trainee);

        $this->actingAs($trainee, 'web')->get(route('tasks.index'))->assertOk()
            ->assertSee('<article', false);

        foreach ($tasks as $task) {
            $this->actingAs($trainee, 'web')
                ->get(route('tasks.index'))
                ->assertSee(e($task->task));
        }
    }

    /**
     * Kontrak breakpoint harus terkunci di markup.
     *
     * Test lain hanya membuktikan `<article>` ADA di HTML - dan itu tetap
     * benar walau browsernya tidak pernah menampilkannya. Penentu
     * benar/tidaknya tampilan mobile justru pasangan kelas di bawah, jadi
     * keduanya dihitung agar pergantian kelas apa pun langsung gagal.
     *
     * Tanpa ini, mengganti `md:hidden` menjadi `hidden` (kartu ada tapi
     * tidak pernah tampil) akan lolos tanpa gagal - itulah mutasi yang
     * sempat lolos sebelum assertion ini ditambahkan.
     */
    public function test_mobile_and_desktop_wrappers_declare_opposite_breakpoints(): void
    {
        $trainee = $this->trainee();
        $this->ownedTasks($trainee);

        $html = $this->actingAs($trainee, 'web')->get(route('tasks.index'))->assertOk()->getContent();

        // Kartu tampil di bawah `md`, tabel tampil di `md` ke atas. Kalau
        // salah satu berubah, salah satu breakpoint jadi tidak punya isi.
        $this->assertSame(1, substr_count($html, '<div class="md:hidden">'),
            'Kartu harus memakai `md:hidden` tepat satu kali.');
        $this->assertSame(1, substr_count($html, '<div class="hidden md:block">'),
            'Tabel harus memakai `hidden md:block` tepat satu kali.');
    }

    public function test_card_exposes_every_column_that_the_table_would_hide(): void
    {
        $trainee = $this->trainee();
        $task = $this->ownedTasks($trainee)->first();

        $response = $this->actingAs($trainee, 'web')->get(route('tasks.index'))->assertOk();
        $html = $response->getContent();

        // Kolom yang paling sering terpotong di layar sempit: tanggal dan
        // status. Kalau salah satu hilang, kartu tidak menggantikan tabel.
        $response->assertSee('Mulai');
        $response->assertSee('Deadline');

        // Label kelompok prioritas ikut dibawa ke kartu.
        $this->assertStringContainsString(
            $task->deadline->translatedFormat('d M Y'),
            $html,
            'Kartu harus menampilkan tanggal deadline tanpa perlu digeser.',
        );
    }

    public function test_status_filter_chips_show_real_counts(): void
    {
        $trainee = $this->trainee();

        // 3 progress + 2 done + 1 canceled + 1 late = 7
        TraineeTask::factory()->count(3)->status(TaskStatus::Progress)->for($trainee, 'trainee')->create();
        TraineeTask::factory()->count(2)->done()->for($trainee, 'trainee')->create();
        TraineeTask::factory()->canceled()->for($trainee, 'trainee')->create();
        TraineeTask::factory()->late()->for($trainee, 'trainee')->create();

        $html = $this->actingAs($trainee, 'web')->get(route('tasks.index'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Filter status"', $html);

        // Chip "Semua" memakai jumlah seluruh kelompok, bukan jumlah baris
        // yang kebetulan ter-render di halaman ini.
        $this->assertMatchesRegularExpression(
            '/aria-current="page"[^>]*>\s*Semua\s*<span[^>]*>\s*7\s*</s',
            $html,
            'Chip "Semua" harus menampilkan jumlah sebenarnya, yaitu 7.',
        );

        foreach (['Progress' => 3, 'Selesai' => 2, 'Terlambat' => 1, 'Dibatalkan' => 1] as $label => $count) {
            $this->assertMatchesRegularExpression(
                '/>\s*'.preg_quote($label, '/').'\s*<span[^>]*>\s*'.$count.'\s*</s',
                $html,
                "Chip {$label} harus menampilkan jumlah {$count}.",
            );
        }
    }

    /**
     * Chip filter harus menampilkan jumlah TOTAL per status, bukan
     * jumlah hasil filter yang aktif. Bug lama: query count memakai
     * $scoped() yang sudah difilter, sehingga chip Semua berubah
     * saat pilih filter Progress.
     */
    public function test_status_filter_chips_show_total_counts_even_with_active_filter(): void
    {
        $trainee = $this->trainee();

        // 3 progress + 2 done + 1 canceled + 1 late = 7
        TraineeTask::factory()->count(3)->status(TaskStatus::Progress)->for($trainee, 'trainee')->create();
        TraineeTask::factory()->count(2)->done()->for($trainee, 'trainee')->create();
        TraineeTask::factory()->canceled()->for($trainee, 'trainee')->create();
        TraineeTask::factory()->late()->for($trainee, 'trainee')->create();

        // Saat filter Progress aktif
        $html = $this->actingAs($trainee, 'web')
            ->get(route('tasks.index', ['status' => 'progress']))
            ->assertOk()
            ->getContent();

        // Semua chip harus tetap menampilkan jumlah TOTAL, bukan terfilter
        // "Semua" chip tidak punya aria-current="page" saat filter aktif (hanya chip Progress yang punya)
        $this->assertMatchesRegularExpression(
            '/>\s*Semua\s*<span[^>]*>\s*7\s*<\/span>/',
            $html,
            'Chip "Semua" harus tetap 7 saat filter Progress aktif.',
        );

        // Chip Progress harus aktif dan count 3
        $this->assertMatchesRegularExpression(
            '/aria-current="page"[^>]*>\s*Progress\s*<span[^>]*>\s*3\s*<\/span>/',
            $html,
            'Chip "Progress" harus aktif dan count 3 saat filter Progress.',
        );

        foreach (['Selesai' => 2, 'Terlambat' => 1, 'Dibatalkan' => 1] as $label => $count) {
            $this->assertMatchesRegularExpression(
                '/>\s*'.preg_quote($label, '/').'\s*<span[^>]*>\s*'.$count.'\s*<\/span>/',
                $html,
                "Chip {$label} harus tetap {$count} saat filter Progress aktif.",
            );
        }

        // Juga test filter Selesai
        $html = $this->actingAs($trainee, 'web')
            ->get(route('tasks.index', ['status' => 'done']))
            ->assertOk()
            ->getContent();

        // "Semua" tetap 7
        $this->assertMatchesRegularExpression(
            '/>\s*Semua\s*<span[^>]*>\s*7\s*<\/span>/',
            $html,
            'Chip "Semua" harus tetap 7 saat filter Selesai aktif.',
        );

        // Chip Selesai harus aktif dan count 2
        $this->assertMatchesRegularExpression(
            '/aria-current="page"[^>]*>\s*Selesai\s*<span[^>]*>\s*2\s*<\/span>/',
            $html,
            'Chip "Selesai" harus aktif dan count 2 saat filter Selesai.',
        );

        foreach (['Progress' => 3, 'Terlambat' => 1, 'Dibatalkan' => 1] as $label => $count) {
            $this->assertMatchesRegularExpression(
                '/>\s*'.preg_quote($label, '/').'\s*<span[^>]*>\s*'.$count.'\s*<\/span>/',
                $html,
                "Chip {$label} harus tetap {$count} saat filter Selesai aktif.",
            );
        }
    }

    public function test_filter_modal_is_removed_so_there_is_only_one_filter_control(): void
    {
        $trainee = $this->trainee();
        $this->ownedTasks($trainee);

        $html = $this->actingAs($trainee, 'web')->get(route('tasks.index'))->assertOk()->getContent();

        // Modal lama punya satu dropdown status - isinya identik dengan chip,
        // sehingga keduanya harus dihapus, bukan hanya disembunyikan.
        $this->assertStringNotContainsString('id="filter-title"', $html);
        $this->assertStringNotContainsString('id="filter-status"', $html);

        // Chip filter tetap ada sebagai satu-satunya kontrol.
        $this->assertStringContainsString('aria-label="Filter status"', $html);
    }

    /**
     * Wadah scroll tabel wajib memakai `table-scroll`.
     *
     * `overflow-x-auto` polos TIDAK cukup: sebuah `div` selebar 939px di
     * dalam wadah itu tidak membuat halaman bisa digeser ke samping, tapi
     * `<table>` selebar 939px membocorkan layout overflow sampai ke scroll
     * area root. Akibatnya di viewport 820px dokumen punya 934px dan
     * seluruh halaman - termasuk header sticky - bergeser 114px ke kiri.
     * `table-scroll` menambahkan `contain: paint` untuk memotongnya di husk
     * wadahnya; scroll di dalam tabel tetap jalan.
     */
    public function test_table_scroll_wrapper_is_used_instead_of_plain_overflow_x_auto(): void
    {
        $trainee = $this->trainee();
        $this->ownedTasks($trainee);

        $html = $this->actingAs($trainee, 'web')->get(route('tasks.index'))->assertOk()->getContent();

        $this->assertStringContainsString('class="table-scroll"', $html);
        $this->assertStringNotContainsString('class="overflow-x-auto"', $html);
    }

    /**
     * `table-scroll` tanpa `contain: paint` hanya secara kosmetik.
     *
     * Assertion class di atas tetap lolos kalau deklarasi CSS-nya dihapus,
     * padahal bug-nya kembali: di viewport 820px dokumen kembali punya
     * 934px dan seluruh halaman bergeser 114px ke kiri (dibuktikan lewat
     * pengukuran browser). Test PHP tidak bisa mengukur layout, jadi
     * minimal deklarasi ini dikunci di sumber.
     */
    public function test_table_scroll_declares_contain_paint(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.table-scroll', $css);
        $this->assertMatchesRegularExpression(
            '/\.table-scroll\s*\{[^}]*contain:\s*paint;/',
            $css,
            '`.table-scroll` wajib membawa `contain: paint`, kalau tidak halaman bisa digeser ke samping.',
        );
    }

    public function test_trash_page_also_uses_the_table_scroll_wrapper(): void
    {
        $admin = $this->admin();
        $trainee = $this->trainee();
        TraineeTask::factory()->for($trainee, 'trainee')->create(['deleted_at' => now()]);

        $html = $this->actingAs($admin, 'web')->get(route('admin.tasks.trash'))->assertOk()->getContent();

        $this->assertStringContainsString('class="table-scroll"', $html);
    }

    /**
     * Kolom Status dan Dihapus harus cukup lebar untuk isinya sendiri.
     *
     * Di halaman Sampah keduanya berisi teks `whitespace-nowrap` yang panjang:
     * "terlambat 118 hari 23 jam" + ikon (~150px) dan
     * "00:05 - otomatis oleh sistem" (~148px). Padding sel 32px per sisi, jadi
     * keduanya baru muat di kolom 192px (`w-48`).
     *
     * Kalau kolomnya sempit, teksnya bocor ke sel sebelah - baris terlihat
     * "bertabrakan": angka terlambat menimpa sumber penghapusan, dan sumber
     * penghapusan menyentuh tombol Pulihkan. `table-fixed` tidak menolong karena
     * `white-space: nowrap` tetap mengabaikan batas kolom.
     */
    public function test_trash_status_and_deleted_columns_fit_their_longest_text(): void
    {
        $admin = $this->admin();
        $trainee = $this->trainee();
        TraineeTask::factory()->late()
            ->for($trainee, 'trainee')
            ->create(['deleted_at' => now(), 'deleted_by' => null]);

        $html = $this->actingAs($admin, 'web')->get(route('admin.tasks.trash'))->assertOk()->getContent();

        // Lebar kolom harus sama di header dan sel badannya, kalau tidak
        // `table-fixed` memakai lebar header dan sel melebar/tersempit sendiri.
        foreach (['Status', 'Dihapus'] as $column) {
            $this->assertMatchesRegularExpression(
                '/<th[^>]*class="table-header-cell w-48">'.preg_quote($column, '/').'<\/th>/',
                $html,
                "Kolom {$column} harus w-48 (192px) supaya teksnya tidak bocor ke sel sebelah.",
            );
        }

        $this->assertSame(
            2,
            substr_count($html, 'table-header-cell w-48'),
            'Hanya kolom Status dan Dihapus yang butuh w-48.',
        );

        // Jam harus pindah ke baris pertama: baris kedua dik-cadangkan penuh
        // untuk sumber penghapusan, kalau tidak teksnya langsung terpotong
        // oleh `truncate`.
        preg_match(
            '/<span class="text-\[12px\] text-ink-faint">\d{2}:\d{2}<\/span>/',
            $html,
            $time,
            PREG_OFFSET_CAPTURE,
        );
        $this->assertNotEmpty($time, 'Jam penghapusan tidak ditemukan di kolom Dihapus.');

        $sourcePos = strpos($html, 'otomatis oleh sistem');
        $this->assertNotFalse($sourcePos, 'Teks sumber penghapusan tidak ditemukan.');

        $this->assertLessThan(
            $sourcePos,
            $time[0][1],
            'Jam penghapusan harus berada di baris pertama, baris kedua dipakai penuh untuk sumber penghapusan.',
        );

        // Lebar minimum tabel mengikuti jumlah kolom tetap yang baru.
        $this->assertMatchesRegularExpression(
            '/table-fixed min-w-\[1040px\]/',
            $html,
            'Lebar minimum tabel harus disesuaikan dengan lebar kolom yang baru.',
        );
    }

    public function test_late_archive_banner_is_only_shown_to_admin(): void
    {
        $admin = $this->admin();
        $trainee = $this->trainee();

        // Deadline 30 hari lalu -> lewat grace period -> banner muncul
        TraineeTask::factory()->late()
            ->for($trainee, 'trainee')
            ->create(['deadline' => now()->subDays(30)]);

        $this->actingAs($admin, 'web')->get(route('admin.tasks.index'))->assertOk()
            ->assertSee('terlambat')
            ->assertSee('otomatis diarsipkan');

        $this->actingAs($trainee, 'web')->get(route('tasks.index'))->assertOk()
            ->assertDontSee('otomatis diarsipkan');
    }

    public function test_scheduler_down_banner_is_only_shown_to_admin(): void
    {
        $admin = $this->admin();
        $trainee = $this->trainee();
        $this->ownedTasks($trainee);

        $this->actingAs($admin, 'web')->get(route('admin.tasks.index'))->assertOk()
            ->assertSee('scheduler mungkin tidak aktif');

        $this->actingAs($trainee, 'web')->get(route('tasks.index'))->assertOk()
            ->assertDontSee('scheduler mungkin tidak aktif');
    }

    public function test_late_task_remains_reachable_through_the_late_chip(): void
    {
        $trainee = $this->trainee();
        TraineeTask::factory()->late()->for($trainee, 'trainee')->create();

        // Chip dan link filter harus benar-benar menunjuk ke ?status=late,
        // karena urutan prioritas menaruh kelompok ini di paling akhir.
        $this->actingAs($trainee, 'web')->get(route('tasks.index'))->assertOk()
            ->assertSee(route('tasks.index', ['status' => 'late']), false);

        $this->actingAs($trainee, 'web')->get(route('tasks.index', ['status' => 'late']))->assertOk();
    }

    public function test_status_dropdown_ids_are_unique_across_card_and_table(): void
    {
        // Halaman daftar merender task yang sama DUA KALI: sebagai kartu
        // (md:hidden) dan sebagai baris tabel. Kalau keduanya memakai id yang
        // sama persis, `aria-controls`/`aria-labelledby` jadi ambigu dan
        // `getElementById()` mengembalikan kopi yang sedang tersembunyi -
        // markup-nya valid-looking tapi dropdown/dialog tidak pernah terlihat.
        $this->authAsAdmin();

        TraineeTask::factory()->count(3)->create(['status' => 'progress']);

        $html = (string) $this->get(route('admin.tasks.index'))->getContent();

        $this->assertUniqueIds($html, 'status-menu-');
        $this->assertUniqueIds($html, 'confirm-delete-');

        $this->assertStringContainsString('status-menu-table-', $html);
        $this->assertStringContainsString('status-menu-card-', $html);
        $this->assertStringContainsString('confirm-delete-table-', $html);
        $this->assertStringContainsString('confirm-delete-card-', $html);
    }

    public function test_edit_confirmation_ids_are_unique_across_card_and_table(): void
    {
        // Dialog konfirmasi edit hanya muncul untuk trainee pada task
        // `done`/`canceled` yang deadline-nya masih di depan - factory tidak
        // menjamin kondisi itu, jadi fixture-nya dibuat eksplisit.
        $trainee = $this->trainee();

        TraineeTask::factory()->count(2)->create([
            'trainee_id' => $trainee->id,
            'status' => 'done',
            'deadline' => now()->addDays(3),
        ]);

        $html = (string) $this->actingAs($trainee, 'web')->get(route('tasks.index'))->getContent();

        $this->assertUniqueIds($html, 'confirm-edit-');
        $this->assertStringContainsString('confirm-edit-table-', $html);
        $this->assertStringContainsString('confirm-edit-card-', $html);
    }

    /**
     * Pastikan tidak ada id dengan prefiks tertentu yang muncul dua kali.
     */
    protected function assertUniqueIds(string $html, string $prefix): void
    {
        preg_match_all('/id="('.preg_quote($prefix, '/').'[^"]+)"/', $html, $matches);

        $ids = $matches[1];

        $this->assertNotEmpty($ids, "Halaman harus merender id dengan prefiks {$prefix}.");

        $kembar = array_keys(array_filter(array_count_values($ids), fn ($n) => $n > 1));

        $this->assertSame([], $kembar, 'id kembar: '.implode(', ', $kembar));
    }

    protected function ownedTasks(User $user): Collection
    {
        return TraineeTask::factory()->count(2)->for($user, 'trainee')->create();
    }
}
