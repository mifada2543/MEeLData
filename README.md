# Trainee Task Management

Aplikasi web internal untuk mencatat, memberikan, memantau, dan mengekspor tugas
trainee. Dibangun dengan **Laravel 12**, **PHP 8.3**, **Blade**, **Tailwind CSS 4**,
**Alpine.js**, dan **maatwebsite/excel**.

Dua role:

- **Admin** — mengelola seluruh data task, import, export, dan bulk delete.
  Akun hanya dapat dibuat melalui seeder.
- **Trainee/User** — melihat dan mengelola task miliknya sendiri. Dapat
  mendaftarkan akun sendiri melalui halaman registrasi.

---

## 1. Kebutuhan Sistem

| Komponen | Versi / Keterangan |
| --- | --- |
| PHP | 8.3 dengan ekstensi `pdo_mysql`, `mbstring`, `zip`, `gd`, `xml`, `intl`, `fileinfo` |
| Composer | 2.x |
| Node.js | **20.19+ atau 22.12+** (Vite 7 / Tailwind 4 mensyaratkan ini) |
| Database | MySQL 8 atau MariaDB 10.4+ |
| Web server | Apache atau Nginx dengan document root ke `public/` |

> **Catatan Node.js:** proses build frontend gagal pada Node 18 dengan pesan
> `Cannot find native binding` / `Vite requires Node.js version 20.19+`. Gunakan
> Node 20.19+ (atau versi yang lebih baru) untuk `npm install` dan `npm run build`.

### Dependency

| Package | Kegunaan |
| --- | --- |
| `maatwebsite/excel` | Import/export `.xlsx` |
| `alpinejs` | Interaksi UI (modal, dropdown, loading state) |
| `@alpinejs/focus` | Plugin resmi Alpine untuk focus trap pada modal (accessibility) |
| `@fontsource-variable/inter` | Font Inter di-*self-host* (tanpa CDN eksternal) |

Font di-*self-host* agar tampilan konsisten di semua mesin dan tidak melakukan
request ke pihak ketiga saat production. Ikon memakai inline SVG (Heroicons
outline) melalui komponen `<x-icon>`, sehingga tidak ada library ikon tambahan.

## 2. Instalasi

```bash
# 1. Dependency PHP
composer install

# 2. Konfigurasi environment
cp .env.example .env
php artisan key:generate

# 3. Atur koneksi database pada .env
#    DB_CONNECTION=mysql
#    DB_DATABASE=MEeLData

# 4. Dependency frontend
npm install
npm run build

# 5. Migrasi + seeder
php artisan migrate --seed
```

Jalankan aplikasi:

```bash
php artisan serve
```

## 3. Konfigurasi Environment

| Key | Default | Keterangan |
| --- | --- | --- |
| `APP_TIMEZONE` | `Asia/Jakarta` | Dipakai untuk scheduler dan perbandingan deadline |
| `ADMIN_NAME` | `Program Admin` | Nama admin hasil seeder |
| `ADMIN_EMAIL` | `admin@meel.test` | Email admin hasil seeder |
| `ADMIN_PASSWORD` | `admin123` | Password admin hasil seeder |

**Ganti `ADMIN_PASSWORD` sebelum deploy ke production.**

## 4. Akun Default

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@meel.test` | `admin123` |
| Trainee | `andi@example.com` | `password` |
| Trainee | `budi@example.com` | `password` |
| Trainee | `siti@example.com` | `password` |
| Trainee | `daffa@example.com` | `password` |

`TraineeSeeder` membuat data demo. Hapus pemanggilnya dari `DatabaseSeeder`
jika tidak diperlukan. Kedua seeder aman dijalankan berulang kali
(`updateOrCreate`) dan tidak menghasilkan duplikat.

## 5. Guard & Authorization

```
guard web   → tabel users   → Trainee
guard admin → tabel admins  → Admin
```

- Tidak ada registrasi admin; halaman registrasi hanya membuat user (`web`).
- Data isolation ditegakkan di **server** melalui scope
  `TraineeTask::visibleTo($actor)`, bukan sekadar menyembunyikan tombol di UI.
- Trainee yang mengakses route `admin.*` mendapat **403** (bukan redirect).
- Task milik orang lain menghasilkan **403**; task tidak ada atau sudah
  soft-deleted menghasilkan **404**.

### Matriks akses

| Aksi | Admin | Trainee |
| --- | :---: | :---: |
| Melihat seluruh task | Ya | Tidak |
| Melihat task sendiri | Ya | Ya |
| Create task | Ya | Ya (milik sendiri) |
| Update metadata task | Ya | Ya (milik sendiri) |
| Update status | Ya | Ya (milik sendiri) |
| Delete individual | Ya | Tidak (tidak ada route) |
| Bulk delete | Ya | Tidak |
| Import Excel | Ya | Tidak |
| Export | Ya | Ya (scope sendiri) |

## 6. Struktur Data

### `admins`

```
id, name, email (unique), password, created_at, updated_at
```

### `trainee_tasks`

```
id (UUID, primary key)
trainee_id (FK → users.id, cascade on delete)
task (string)
desc (text, nullable)
start_date (datetime)
deadline (datetime)
status (enum: progress | done | late | canceled, default progress)
created_at, updated_at, deleted_at (soft delete)
```

Index: `trainee_id`, `(trainee_id, status, deadline)`, `status`, `deadline`.

UUID dihasilkan otomatis oleh trait bawaan Laravel `HasUuids` (UUID v7), bukan
generator manual.

### Status

| Nilai | Dipilih manual? | Keterangan |
| --- | :---: | --- |
| `progress` | Ya | Status default task baru |
| `done` | Ya | |
| `late` | **Tidak** | Hanya dihasilkan scheduler |
| `canceled` | Ya | |

`late` ditolak oleh form create, form update, inline status update, **dan**
import Excel.

## 7. Automatic Late (Scheduler)

Command: `tasks:mark-late`

```
status = progress AND deadline < now()  →  late
```

`done` dan `canceled` tidak pernah diubah. Perintah ini idempotent: menjalankan
berulang kali tidak menghasilkan efek tambahan.

### Cron server

```cron
* * * * * cd /path/to/MEeLData && php artisan schedule:run >> /dev/null 2>&1
```

Tanpa cron, status `late` tidak akan ter-update otomatis. Periksa dengan:

```bash
php artisan schedule:list
```

### Perilaku pemulihan status `late` (penting)

`late` **boleh** dikembalikan secara manual menjadi `progress`, `done`, atau
`canceled` melalui inline status update.

- `late → done` atau `late → canceled`: **tidak akan diubah** oleh scheduler.
- `late → progress` ketika deadline **sudah terlewati**: scheduler pada
  run berikutnya akan menandainya kembali menjadi `late`. Ini perilaku yang
  disengaja, karena task tersebut memang melewati deadline.

## 8. Import Excel

Format file `.xlsx`, maksimal **10 MB**. Gunakan tombol **Download Template**
pada modal import untuk memperoleh header + 1 baris contoh.

| Kolom | Wajib | Keterangan |
| --- | :---: | --- |
| `trainee_email` | Ya | Harus terdaftar; trainee diidentifikasi melalui email |
| `task` | Ya | Maks. 255 karakter |
| `description` | Tidak | Boleh kosong |
| `start_date` | Ya | Format `Y-m-d H:i:s`, `Y-m-d H:i`, `Y-m-d`, atau `d/m/Y` |
| `deadline` | Ya | Harus `>= start_date` |
| `status` | Tidak | `progress` (default), `done`, `canceled` |

Import memakai **partial success**: baris valid tersimpan, baris invalid
dilewati dan dilaporkan dengan nomor baris Excel asli (header = baris 1).

Contoh laporan:

```
2 tasks imported successfully.
4 rows failed and were not imported.

Row 3: Trainee "ghost@example.com" was not found.
Row 4: Status "late" is not allowed. "Late" is set automatically by the system.
Row 5: Deadline "bad-date" is not a valid date.
Row 6: Deadline must be after or equal to start date.
```

Duplikat diperbolehkan dan setiap baris dianggap task baru.

## 9. Export Excel

Tersedia untuk Admin dan Trainee. Export memakai **query yang sama** dengan
halaman daftar task, sehingga hasilnya konsisten dengan filter yang aktif:

```text
Filter Status = Late  →  export hanya berisi task late
Tanpa filter          →  export seluruh active task yang terlihat oleh user
```

Kolom: UUID, Trainee, Task, Description, Start Date, Deadline, Status,
Created At, Updated At. Soft-deleted task tidak termasuk.

## 10. Testing

```bash
composer test        # otomatis menjalankan config:clear lebih dahulu
```

Suite mencakup authentication, authorization/IDOR, CRUD, status, filter,
bulk delete, import, export, dashboard, seeder, scheduler, dan render UI.

> **`config:cache` dan test tidak dapat berjalan bersamaan.** Cache konfigurasi
> mengabaikan environment override dari `phpunit.xml`, sehingga test akan
> memakai konfigurasi production (session driver, database, dll.) dan gagal.
> Jalankan `php artisan optimize:clear` sebelum `php artisan test`. Script
> `composer test` sudah melakukan ini secara otomatis.

> **Catatan konfigurasi test:** `phpunit.xml` memakai MySQL pada database
> `MEeLData_test`, bukan SQLite, karena ekstensi PHP pada environment ini hanya
> menyediakan `pdo_mysql`. Buat database tersebut sekali:
>
> ```sql
> CREATE DATABASE MEeLData_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
> ```

## 11. Deployment

1. `composer install --no-dev --optimize-autoloader`
2. `npm ci && npm run build`
3. `php artisan migrate --force`
4. `php artisan db:seed --force` (hanya sekali, untuk membuat admin)
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
6. Arahkan document root web server ke direktori `public/`
7. Pastikan `storage/` dan `bootstrap/cache/` writable oleh web server
8. Aktifkan cron scheduler (bagian 7)
9. Set `APP_DEBUG=false`

## 12. Design System

Semua nilai visual berasal dari **design token** di `resources/css/app.css`
(bagian `@theme`), bukan dari kelas warna acak per komponen. Mengubah satu
token memperbarui seluruh tampilan.

| Token | Nilai | Dipakai untuk |
| --- | --- | --- |
| `canvas` / `surface` / `surface-sunken` |Abu terang | Latar aplikasi, kartu, header tabel |
| `line` / `line-strong` | Abu | Border, divider, scrollbar |
| `ink` → `ink-faint` | Abu gelap | Teks utama → helper text |
| `accent` | Near-black | Tombol primary, sidebar aktif, chrome |
| `progress` / `done` / `late` / `canceled` | Birijau/merah/abu | **Satu-satunya** warna bernada: kanal status |

Prinsip yang diterapkan:

- **Table-first** — tabel adalah surface utama, bukan sekadar salah satu
  widget. Header tabel sticky, gridline sangat soft, angka memakai
  `tabular-nums` agar sejajar saat dipindai.
- **Hierarki dari tipografi & spasi** — bukan dari menumpuk border. Kartu
  memakai border tipis + shadow sangat halus.
- **Warna hanya untuk status** — tidak ada warna dekoratif, sehingga merah
  selalu berarti sesuatu (terlambat / destruktif).
- **Satu verdict per halaman** — H1 besar di kiri atas; sisanya mendukung.
- **Tanpa chart di dashboard** — data di aplikasi ini adalah baris tabel, jadi
  komposisi status disajikan sebagai progress bar, bukan grafik.
- **Tidak ada dark mode** — di luar MVP design scope.

### Komponen Blade

`<x-icon>` · `<x-button>` · `<x-badge>` (status) · `<x-empty-state>` ·
`<x-field-error>` · `<x-toast>` · `<x-confirm-modal>` · `<x-status-select>`

### Aksesibilitas

- Focus trap pada semua modal (`x-trap`) + tombol close + Escape
- Focus ring selalu terlihat (`:focus-visible`)
- Label pada seluruh input, `aria-label` pada ikon-only button
- Status tidak hanya dibedakan warna (label teks selalu ada)
- `role="status"` / `role="alert"` untuk feedback
- Scrollbar tipis, tabel dapat digeser horizontal di layar kecil

## 13. Struktur Direktori

```
app/
├── Console/Commands/MarkLateTasks.php     # tasks:mark-late
├── Enums/TaskStatus.php                   # status terpusat
├── Exports/TasksExport.php                # export berbasis query
├── Exports/TaskTemplateExport.php         # template import
├── Http/Controllers/Auth/                 # login trainee & admin, register
├── Http/Controllers/TraineeTaskController.php
├── Http/Requests/                         # validasi server-side
├── Imports/TasksImport.php                # partial success
└── Models/{Admin,User,TraineeTask}.php

resources/views/
├── auth/                                  # login, admin login, register
├── components/                            # icon, button, badge, modal, toast, dll
├── dashboard/                             # dashboard + partial list
├── errors/403.blade.php
├── layouts/                               # app & guest
├── tasks/                                 # index, create, edit, partials
└── vendor/pagination/                     # pagination view kustom
```