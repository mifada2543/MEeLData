<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Memastikan halaman yang dirender benar-benar dapat dibuka (200) untuk
 * kedua guard, termasuk view yang memakai komponen Alpine/Blade.
 */
class UiRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_render(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('Selamat datang kembali')
            ->assertSee('name="_token"', false);

        $this->get(route('register'))->assertOk()
            ->assertSee('Buat akun Anda')
            ->assertSee('Ulangi kata sandi');
    }

    public function test_app_layout_renders_for_admin(): void
    {
        $admin = $this->admin(['name' => 'Rina']);

        $this->actingAs($admin, 'web')->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Rina')
            ->assertSee('Dashboard');

        $this->actingAs($admin, 'web')->get(route('admin.tasks.index'))->assertOk()
            ->assertSee('Tugas');

        $this->actingAs($admin, 'web')->get(route('admin.tasks.create'))->assertOk()
            ->assertSee('Tambah Tugas');
    }

    public function test_app_layout_renders_for_trainee(): void
    {
        $trainee = $this->trainee(['name' => 'Andi']);

        $this->actingAs($trainee, 'web')->get(route('dashboard'))->assertOk()
            ->assertSee('Andi')
            ->assertSee('Tugas Saya');

        $this->actingAs($trainee, 'web')->get(route('tasks.index'))->assertOk();
        $this->actingAs($trainee, 'web')->get(route('tasks.create'))->assertOk()
            ->assertSee('Tambah Tugas');
    }

    public function test_edit_form_renders_with_existing_values(): void
    {
        $trainee = $this->trainee(['name' => 'Andi Pratama']);
        $task = TraineeTask::factory()->create([
            'trainee_id' => $trainee->id,
            'task' => 'Maintenance Kabel',
            'desc' => 'Perbaikan kabel jaringan.',
        ]);

        $this->actingAs($trainee, 'web')->get(route('tasks.edit', $task))->assertOk()
            ->assertSee('Ubah Tugas')
            ->assertSee('Maintenance Kabel')
            ->assertSee('Andi Pratama');
    }

    public function test_trainee_select_is_disabled_and_shows_own_name(): void
    {
        $trainee = $this->trainee(['name' => 'Andi Pratama']);

        $response = $this->actingAs($trainee, 'web')->get(route('tasks.create'));

        $response->assertOk();
        // Field trainee untuk trainee adalah disabled/read-only.
        $response->assertSee('disabled', false);
        $response->assertSee('Andi Pratama');
    }

    public function test_admin_create_form_has_trainee_select_with_all_trainees(): void
    {
        $this->trainee(['name' => 'Andi Pratama']);
        $this->trainee(['name' => 'Budi Santoso']);

        $this->authAsAdmin()->get(route('admin.tasks.create'))->assertOk()
            ->assertSee('Andi Pratama')
            ->assertSee('Budi Santoso')
            ->assertSee('<select', false);
    }

    public function test_admin_task_list_hides_import_for_trainee(): void
    {
        $admin = $this->admin();
        $trainee = $this->trainee();

        $this->actingAs($admin, 'web')
            ->get(route('admin.tasks.index'))
            ->assertOk()
            ->assertSee('Impor Tugas', false)
            ->assertSee('Hapus Terpilih', false);

        $this->actingAs($trainee, 'web')
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertDontSee('Impor Tugas', false)
            ->assertDontSee('Hapus Terpilih', false)
            ->assertDontSee('bulk-delete-form', false);
    }

    public function test_admin_task_list_shows_trainee_column_and_delete_action(): void
    {
        $trainee = $this->trainee(['name' => 'Budi Santoso']);
        TraineeTask::factory()->create([
            'trainee_id' => $trainee->id,
            'task' => 'Maintenance Kabel',
        ]);

        $this->authAsAdmin()->get(route('admin.tasks.index'))->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Edit')
            ->assertSee('Delete');
    }

    public function test_empty_state_is_shown_without_tasks(): void
    {
        $this->authAsAdmin()->get(route('admin.tasks.index'))->assertOk()
            ->assertSee('Belum ada tugas');

        $this->authAsAdmin()->get(route('admin.tasks.index', ['status' => 'done']))->assertOk()
            ->assertSee('Tidak ada tugas yang cocok dengan filter')
            ->assertSee('atur ulang filter');
    }

    public function test_export_button_is_visible_for_both_roles(): void
    {
        // Ekspor tidak lagi disembunyikan di dalam modal filter, dan tidak
        // memakai label Inggris.
        $this->authAsAdmin()->get(route('admin.tasks.index'))->assertOk()
            ->assertSee('Ekspor tugas ke Excel?', false)
            ->assertSee('Ekspor Terpilih', false);

        $this->authAsTrainee()->get(route('tasks.index'))->assertOk()
            ->assertSee('Ekspor tugas ke Excel?', false);
    }

    public function test_export_dialog_message_has_no_stray_space_before_punctuation(): void
    {
        // Jumlah tugas dan sisa kalimat dirangkai di klien. Bila Blade
        // menyisipkan spasi di antara kedua <span>, tampilannya menjadi
        // "1 tugas yang dipilih . Filter ...".
        $html = $this->authAsAdmin()->get(route('admin.tasks.index'))
            ->assertOk()->getContent();

        preg_match('/<p[^>]*>.*?Hanya ekspor.*?<\/p>/s', $html, $match);
        $this->assertNotEmpty($match, 'Paragraf pesan mode "selected" tidak ditemukan.');

        // html_entity_decode + collapse whitespace mendekati teks yang dibaca user.
        $text = html_entity_decode(strip_tags($match[0]), ENT_QUOTES);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        $this->assertStringContainsString('dipilih.', $text);
        $this->assertStringNotContainsString('dipilih .', $text);
    }

    public function test_trainee_has_checkbox_for_export_but_no_bulk_delete(): void
    {
        $trainee = $this->trainee();
        TraineeTask::factory()->create(['trainee_id' => $trainee->id]);

        $this->actingAs($trainee, 'web')->get(route('tasks.index'))->assertOk()
            ->assertSee('Pilih tugas untuk diekspor', false)
            ->assertDontSee('Hapus Terpilih', false)
            ->assertDontSee('bulk-delete-form', false);
    }

    public function test_admin_checkbox_is_labelled_for_bulk_actions(): void
    {
        $this->task();

        $this->authAsAdmin()->get(route('admin.tasks.index'))->assertOk()
            ->assertSee('Pilih tugas untuk aksi massal', false)
            ->assertSee('bulk-delete-form', false);
    }

    public function test_status_badge_renders_all_status_labels(): void
    {
        $admin = $this->admin();

        foreach (TaskStatus::cases() as $case) {
            TraineeTask::factory()->create(['status' => $case]);
        }

        $this->actingAs($admin, 'web')->get(route('admin.tasks.index'))->assertOk()
            ->assertSee('Progress')
            ->assertSee('Selesai')
            ->assertSee('Terlambat')
            ->assertSee('Dibatalkan');
    }

    public function test_status_badge_itself_is_the_dropdown_trigger(): void
    {
        // design.md §8.1: "[ Progress ▼ ]" yang diklik langsung. Tidak ada
        // lagi tombol "Ubah" terpisah di sebelah badge.
        $task = TraineeTask::factory()->create(['status' => TaskStatus::Progress]);

        $html = $this->authAsAdmin()->get(route('admin.tasks.index'))
            ->assertOk()->getContent();

        preg_match('/<button[^>]*aria-haspopup="menu".*?<\/button>/s', $html, $match);
        $this->assertNotEmpty($match, 'Badge status tidak ditemukan sebagai <button>.');

        $trigger = $match[0];

        // Label status tetap jadi teks yang terlihat (bukan hanya ikon/aria).
        $this->assertStringContainsString('Progress', $trigger);
        $this->assertStringContainsString('aria-label="Ubah status: Progress"', $trigger);
        $this->assertStringContainsString('aria-expanded', $trigger);

        // Warna badge sama persis dengan <x-status-badge> statis.
        foreach ($task->status->badgeClasses() as $class) {
            $this->assertStringContainsString($class, $trigger);
        }

        // Tidak ada tombol "Ubah" terpisah.
        $this->assertDoesNotMatchRegularExpression('/>\s*Ubah\s*</', $html);

        // Sel status hanya boleh punya SATU badge. Menambah <x-status-badge>
        // di sebelah <x-status-select> akan memunculkan dua pill.
        preg_match('/<td[^>]*class="table-body-cell w-40">(.*?)<\/td>/s', $html, $cell);
        $this->assertNotEmpty($cell, 'Sel status tidak ditemukan.');
        $this->assertSame(
            1,
            substr_count($cell[1], 'bg-progress-surface'),
            'Sel status harus hanya berisi satu badge (trigger-nya).',
        );
    }

    /**
     * Dropdown status harus tetap menempel pada tombolnya saat tabel digeser.
     *
     * `.table-scroll` memakai `contain: paint`, jadi ia jadi containing block
     * untuk `position: fixed`. Koordinat menu dihitung dari kotak wadah SETELAH
     * digeser - artinya `scrollLeft`/`scrollTop` wajib ikut dikurangkan. Tanpa
     * itu menu meleset sebesar nilai scroll: di 1024px (tabel tergeser 240px)
     * menu tampak ~320px di kiri tombolnya, persis keluhan "status dropdownnya
     * tidak sesuai dengan buttonnya".
     */
    public function test_status_dropdown_position_compensates_for_scrolled_container(): void
    {
        TraineeTask::factory()->create(['status' => TaskStatus::Progress]);

        $html = $this->authAsAdmin()->get(route('admin.tasks.index'))
            ->assertOk()->getContent();

        // Blok x-data milik <x-status-select> - ditandai `fixedHost()`,
        // karena halaman ini punya beberapa komponen Alpine lain.
        preg_match_all('/x-data="\{.*?\}"/s', $html, $all);

        $script = '';
        foreach ($all[0] as $candidate) {
            if (str_contains($candidate, 'fixedHost()')) {
                $script = $candidate;
                break;
            }
        }

        $this->assertNotSame('', $script, 'Blok position() pada <x-status-select> tidak ditemukan.');

        $this->assertStringContainsString(
            'host.scrollLeft',
            $script,
            'Offset menu wajib mengurangi scrollLeft wadah, kalau tidak menu meleset saat tabel digeser.',
        );
        $this->assertStringContainsString(
            'host.scrollTop',
            $script,
            'Offset menu wajib mengurangi scrollTop wadah, kalau tidak menu meleset saat halaman digeser.',
        );

        // Menu juga harus menempel ke tombol: rata kiri secara default, dan
        // pindah ke sisi kanan tombol (bukan ke batas wadah) kalau tidak muat.
        $this->assertMatchesRegularExpression(
            '/if \(left \+ w > rightEdge\) left = r\.right - w/',
            $script,
            'Menu harus menempel pada sisi kanan tombol saat tidak muat di sisi kiri.',
        );
    }

    public function test_status_dropdown_offers_only_manual_cases(): void
    {
        TraineeTask::factory()->create(['status' => TaskStatus::Progress]);

        $html = $this->authAsAdmin()->get(route('admin.tasks.index'))
            ->assertOk()->getContent();

        // Hanya form update status yang diperiksa. Filter `?status=` memang
        // BOLEH memfilter status `late`; yang dilarang adalah menetapkan
        // status `late` secara manual.
        preg_match('/<form[^>]*action="[^"]*\/status"[^>]*>(.*?)<\/form>/s', $html, $form);
        $this->assertNotEmpty($form, 'Form update status tidak ditemukan.');

        preg_match_all('/name="status"\s+value="([^"]+)"/', $form[1], $values);

        $this->assertSame(['progress', 'done', 'canceled'], $values[1]);
        $this->assertNotContains('late', $values[1]);
    }

    public function test_status_badge_falls_back_to_native_select_without_js(): void
    {
        TraineeTask::factory()->create(['status' => TaskStatus::Progress]);

        $html = $this->authAsAdmin()->get(route('admin.tasks.index'))
            ->assertOk()->getContent();

        // Tanpa JS dropdown tidak bisa dibuka, jadi ada <noscript>.
        $this->assertStringContainsString('<noscript>', $html);
        $this->assertStringContainsString('name="status"', $html);
    }

    public function test_403_page_renders_for_trainee_on_admin_route(): void
    {
        $this->authAsTrainee()
            ->get(route('admin.tasks.index'))
            ->assertForbidden()
            ->assertSee('Akses Ditolak');
    }

    public function test_validation_errors_are_shown_near_fields(): void
    {
        $this->authAsAdmin()
            ->from(route('admin.tasks.create'))
            ->post(route('admin.tasks.store'), ['task' => '', 'start_date' => '', 'deadline' => ''])
            ->assertRedirect(route('admin.tasks.create'))
            ->assertSessionHasErrors('task');

        $this->followingRedirects()
            ->authAsAdmin()
            ->from(route('admin.tasks.create'))
            ->post(route('admin.tasks.store'), ['task' => '', 'start_date' => '', 'deadline' => ''])
            ->assertOk()
            ->assertSee('Kolom nama tugas wajib diisi.');
    }

    public function test_import_result_section_renders_errors(): void
    {
        $this->trainee(['email' => 'andi@example.com']);

        $path = tempnam(sys_get_temp_dir(), 'import-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['trainee_email', 'task', 'description', 'start_date', 'deadline', 'status'], null, 'A1');
        $sheet->fromArray(['nobody@example.com', 'X', 'D', now()->addDays(0)->format('Y-m-d H:i:s'), now()->addDays(4)->format('Y-m-d H:i:s'), 'progress'], null, 'A2');
        (new Xlsx($spreadsheet))->save($path);

        $response = $this->authAsAdmin()
            ->post(route('admin.tasks.import'), ['file' => new UploadedFile($path, 'tasks.xlsx', null, null, true)]);

        $response->assertRedirect();

        $this->followingRedirects()
            ->authAsAdmin()
            ->get(session()->previousUrl() ?: route('admin.tasks.index'))
            ->assertOk()
            ->assertSee('Hasil Impor')
            ->assertSee('0 berhasil', false)
            ->assertSee('tidak ditemukan')
            ->assertSee('nobody@example.com');

        @unlink($path);
    }

    public function test_flash_toast_renders_after_create(): void
    {
        $trainee = $this->trainee();

        $this->actingAs($trainee, 'web')
            ->followingRedirects()
            ->post(route('tasks.store'), [
                'task' => 'Task with toast',
                'desc' => 'desc',
                'start_date' => now()->addDays(0)->format('Y-m-d H:i:s'),
                'deadline' => now()->addDays(4)->format('Y-m-d H:i:s'),
            ])
            ->assertOk()
            ->assertSee('Tugas berhasil dibuat.');
    }
}
