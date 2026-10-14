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
sh deploy/serve.sh
```

> Gunakan `sh deploy/serve.sh` - bukan `php artisan serve` - untuk pengembangan.
> Skrip itu menaikkan `upload_max_filesize` menjadi 12 MB dan `post_max_size`
> menjadi 16 MB lewat `PHP_INI_SCAN_DIR`, sehingga fitur **Impor Excel** bisa
> menerima berkas sampai 10 MB seperti yang dijanjikan UI. Dengan default PHP
> (2 MB) berkas lebih besar dipotong PHP sebelum sampai ke Laravel dan muncul
> pesan *"Kolom berkas gagal diunggah."*.
>
> Untuk production (Apache/FPM), setel nilai yang sama langsung di `php.ini`.

## 3. Konfigurasi Environment

| Key | Default | Keterangan |
| --- | --- | --- |
| `APP_TIMEZONE` | `Asia/Jakarta` | Dipakai untuk scheduler dan perbandingan deadline |
| `APP_LOCALE` | `id` | Bahasa antarmuka & pesan validasi |
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
guard web → tabel users → role: 'admin' | 'trainee'
```

**Satu gateway login** untuk admin maupun trainee: keduanya memakai halaman
`/login` yang sama. Setelah login, tujuan mengikuti role akun
(`/admin/dashboard` untuk admin, `/dashboard` untuk trainee).

- Tidak ada tabel `admins` terpisah. Akun admin dibedakan dengan kolom `role`.
- **Tidak ada registrasi admin.** Kolom `role` tidak ada di `$fillable`, sehingga
  form registrasi publik tidak pernah bisa membuat akun admin — hasilnya selalu
  `trainee`. Akun admin hanya dibuat melalui `AdminSeeder`.
- Data isolation ditegakkan di **server** melalui scope
  `TraineeTask::visibleTo($actor)`, bukan sekadar menyembunyikan tombol di UI.
- Route `admin.*` memakai middleware `can:admin`, sehingga trainee yang
  mengaksesnya mendapat **403** (bukan redirect).
- Akun admin tidak dapat dipilih sebagai assignee task; hal ini divalidasi
  di server (`Rule::exists(...)->where('role', 'trainee')`), bukan hanya
  disembunyikan di dropdown.
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
| Impor Excel | Ya | Tidak |
| Ekspor | Ya | Ya (scope sendiri) |
| Ekspor terpilih | Ya | Ya (hanya untuk checkbox ekspor) |

## 6. Struktur Data

### `users`

Satu tabel untuk admin maupun trainee:

```
id, name, email (unique), password,
role (enum: admin | trainee, default trainee),
created_at, updated_at
```

Tabel `admins` tidak lagi ada; migration
`2026_10_02_000003_fold_admins_into_users_table` memindahkan seluruh akun admin
ke `users` lalu drop tabel tersebut (dapat di-rollback).

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
| `late` | **Tidak** | Hanya dihasilkan scheduler, dan status ini **turunan** dari deadline |
| `canceled` | Ya | |

`late` ditolak oleh form create, form update, inline status update, **dan**
import Excel.

Selain itu, task yang **sudah** berstatus `late` juga tidak dapat diubah
statusnya lewat dropdown — bukan lewat UI, bukan lewat request buatan.
Statusnya berubah hanya secara otomatis: kembali menjadi `progress` bila
deadline-nya digeser ke masa depan, lalu kembali `late` lagi bila deadline
barunya terlewati. Lihat [status `late` sebagai status turunan](#perilaku-status-late-status-turunan-bukan-status-mutlak)
dan [kunci edit untuk trainee](#kunci-edit-untuk-trainee).

### Aturan tanggal (tidak boleh lampau)

| Jalur | Aturan |
| --- | --- |
| Create | `start_date >= now()` **dan** `deadline >= now()` |
| Edit | Tanggal **tidak boleh diubah ke nilai lampau**. Nilai lama yang sudah lewat tetap boleh dipertahankan. |
| Import Excel | Sama seperti create — baris bertanggal lampau gagal per baris |
| Restore | Sama seperti create (tanggal wajib diisi ulang) |

Perbandingan dilakukan **per menit**, bukan per detik: input
`datetime-local` hanya berpresisi menit, jadi memilih menit yang sedang
berjalan akan selalu ditolak bila dibandingkan per detik.

Aturan Edit sengaja lebih longgar supaya task lama (mis. mulai bulan lalu)
masih bisa diedit hanya untuk memperbaiki deskripsi. Perbandingan "berubah"
dilakukan per menit agar nilai yang tidak disentuh admin tetap dianggap
tidak berubah.

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

Baris tersebut sudah tersimpan di dalam proyek sebagai
`deploy/crontab.meeldata`, jadi tidak hilang saat proyek dipindah atau
di-clone ulang. Pasang dengan:

```bash
crontab deploy/crontab.meeldata      # memasang
crontab -e                           # melepas (hapus baris tsb)
```

Yang ada di dalam proyek: **definisi jadwalnya** (`routes/console.php`).
Yang harus didaftarkan ke sistem hanya **satu baris** di atas, karena daemon
cron OS hanya membaca dari lokasi milik sistem (`~/.config/crontab` atau
`/etc/cron.d`), bukan dari folder aplikasi.

Tanpa cron, status `late` tidak akan ter-update otomatis. Periksa dengan:

```bash
php artisan schedule:list
```

### Jendela sebelum ditandai `late`

Task `progress` yang deadline-nya lewat **ditahan 120 detik** sebelum ditandai
`late`, sehingga pengguna sempat melihat **hitung mundur** di daftar tugas:
`dalam 2:00` → `1:59` → … → `0:01`, lalu berubah menjadi `Terlambat`.

```text
deadline < now()                      -> progress (counter berjalan)
deadline < now() - 120 detik          -> late     (counter hilang, badge Terlambat)
```

Diatur lewat `LATE_MARK_AFTER_SECONDS`. Set `0` untuk kembali persis ke
PRD §7 (`deadline < now()` tanpa tenggang).

### Auto-arsip task terlambat

Command terpisah: `tasks:archive-late`, berjalan **sekali sehari** (default
pukul 00:05 WIB).

```text
status = late  AND  deadline < now() - grace  →  soft delete
```

- Masa tenggang `grace` diukur dari `deadline`, bukan dari kapan task ditandai
  `late`. Default **3 hari**, diatur lewat `LATE_ARCHIVE_GRACE_DAYS`.
- `deleted_by` dibiarkan `null` → task ditandai "dihapus otomatis oleh sistem".
  Delete manual oleh admin mengisi `deleted_by` dengan user-id-nya.
- Idempotent dan aman dijalankan berulang.
- **Bergantung pada cron yang sama.** Bila cron mati, `tasks:mark-late` juga
  tidak berjalan, tidak ada task yang menjadi `late`, dan tidak ada yang
  terarsip. Gejala cron mati itu terdeteksi di UI lewat kelompok
  prioritas 4 beserta banner peringatan.

```bash
php artisan tasks:archive-late   # bisa dijalankan manual untuk uji
```

### Perilaku status `late`: status turunan, bukan status mutlak

`late` adalah **status turunan** dari deadline + status. Nilainya berarti
"deadline sudah lewat dan belum selesai", sehingga ia **hanya sah** selama
memang deadline-nya lampau.

Scheduler menyelaraskan dua aturan yang saling melengkapi:

```
progress AND deadline < now() - 120s  ->  late      (atur ke-1)
late     AND deadline >= now()         ->  progress  (atur ke-2, baru)
```

Kedua batas itu tidak tumpang tindih, jadi tidak ada kondisi yang memicu
flip-flop bolak-balik dalam satu menit.

Aturan ke-2 menjawab masalah nyata: `tasks:mark-late` semula hanya punya satu
arah (`progress -> late`), sehingga begitu sebuah task ditandai terlambat ia
**tidak akan pernah kembali** - walau admin memperbaiki deadline-nya ke masa
depan. Badge `Terlambat` lalu menampilkan sesuatu yang jelas-jelas tidak benar.
Sekarang sistem menutup dirinya sendiri, maksimal satu menit, dari mana pun
tanggalnya berubah: edit, restore, impor, atau ubah langsung di database.

Konsekuensi: `late` tidak lagi "terminal", dan inline status update tetap
ditolak (`UpdateStatusRequest`) selama task berstatus `late` - dropdown itu
tidak ada gunanya, karena scheduler akan menandainya `late` lagi begitu
deadline-nya terlewati. **Edit** dan **Restore** adalah dua jalan keluar yang
sah, dan keduanya berakhir sama: `late -> progress`, lalu kembali `late` bila
deadline barunya lewat.

### Edit task terlambat (admin)

Task berstatus `late` masuk mode "hanya deadline": nama, deskripsi, tanggal
mulai, dan trainee terkunci. Hanya `deadline` yang dapat diubah.

- **UI** - lima field tersebut dirender `disabled`, disertai callout yang
  menjelaskan aturan main. Tanpa callout, lima field yang tiba-tiba mati akan
  terbaca sebagai bug, bukan aturan. Panel status di bawahnya diringkas jadi
  satu kalimat agar tidak mengulang penjelasan yang sama.
- **Server** - `UpdateTaskRequest` punya dua lapis, karena `disabled`/
  `readonly` bukan pengaman dan request bisa dibuat-buat:
  1. `prepareForValidation()` memaksa `task`, `desc`, dan `start_date` kembali
     ke nilai tersimpan **sebelum** validasi jalan, sehingga payload palsu
     tidak pernah ikut dievaluasi.
  2. `taskAttributes()` memangkas payload ke `['deadline']` saja, jadi
     `status` juga tidak bisa dipakai mem-bypass jalur status turunan dengan
     langsung men-set `done`.

  `status` sengaja tidak di-merge di `prepareForValidation()`: nilainya pasti
  `late`, sedangkan `late` tidak termasuk `TaskStatus::manualValues()` -
  mem-merge-nya justru membuat request sendiri gagal validasi.

  Aturan "tidak boleh lampau" pada `start_date` dilewati di mode ini: nilainya
  pasti lampau karena itulah definisi `late`, jadi aturan itu tidak bisa
  berlaku dan hanya akan menolak request yang sah.
- Bila deadline **tidak** diubah (masih lampau), status tetap `Terlambat` dan
  penyimpanan tidak mengubah apa pun.
- Setelah disimpan dengan deadline di masa depan, status menjadi `progress`
  **langsung** lewat `TraineeTask::reconcileDerivedStatus()` - tidak menunggu
  cron, supaya user tidak melihat hasil yang tertinggal.

### Kunci edit untuk trainee

| Status | Deadline | Trainee |
|---|---|---|
| `Progress` | - | boleh diedit |
| `Done` / `Canceled` | belum terlewati | boleh diedit, **dengan konfirmasi** |
| `Done` / `Canceled` | sudah terlewati | terkunci (403) |
| `Late` | - | terkunci (403) |

Alasan di balik tabel ini: task `done`/`canceled` yang deadline-nya **masih di
depan** berarti ditutup lebih awal dari jadwal, jadi metadata-nya masih mungkin
salah input - lebih baik diperingatkan daripada dikunci mati. Yang terkunci penuh
adalah yang deadline-nya **sudah lewat**: di situ tidak ada lagi yang bisa
diperbaiki karena tenggatnya selesai.

- Tombol Edit **tidak dirender** untuk task yang terkunci.
- `GET /tasks/{task}/edit` dan `PUT /tasks/{task}` membalas **403** dengan pesan
  dari `TraineeTask::editLockReason()`. Disembunyikan di UI bukan pengaman, URL
  tetap bisa diketik manual.
- Dialog konfirmasi *"Yakin ingin mengubah ini?"* muncul di daftar tugas
  **sebelum** halaman Edit dibuka, lalu tombol **Lanjut ubah** yang membuka
  form. Dialog ini murni untuk UX; yang mengunci tetap server.
- Admin tidak pernah dikunci, untuk status apa pun.


## 8. Import Excel

Format file `.xlsx`, maksimal **10 MB**. Gunakan tombol **Download Template**
pada modal import untuk memperoleh header + 1 baris contoh. Angka *maks.* di
modal mengikuti batas efektif server (`upload_max_filesize`), jadi bila
nilai php.ini lebih kecil, kecilkan file atau naikkan batas php.ini.

Header kolom dan nilai status bisa ditulis **Bahasa Indonesia** maupun bentuk
teknis. Header dari file ekspor (yang berbahasa Indonesia) bisa langsung
diimpor kembali; kolom ekspor menyertakan **Email Trainee** karena import
menemukan trainee lewat email.

| Header Indonesia | Header teknis | Wajib | Keterangan |
| --- | --- | :---: | --- |
| Email Trainee | `trainee_email` | Ya | Harus terdaftar |
| Tugas | `task` | Ya | Maks. 255 karakter |
| Deskripsi | `description` | Tidak | Boleh kosong |
| Tanggal Mulai | `start_date` | Ya | `d/m/Y H:i:s`, `Y-m-d H:i:s`, `Y-m-d`, `d/m/Y` |
| Tanggal Deadline | `deadline` | Ya | Harus `>= start_date` |
| Status | `status` | Tidak | `progress` (default), `done`, `canceled` — atau `Progress`, `Selesai`, `Dibatalkan` |

**Tanggal tanpa jam** (`Y-m-d`, `d/m/Y`, atau sel Excel bertanggal) berlaku
sepanjang hari itu: tanggal mulai dibandingkan per hari, dan deadline disimpan
pada penghujung hari (`23:59:59`) supaya task yang jatuh tempo hari ini tidak
langsung berstatus terlambat. Tanggal yang sudah lewat tetap ditolak.

> Nilai status yang **disimpan ke database dan ditulis ke file ekspor** tetap
> bentuk teknis (`progress`/`done`/`canceled`). Label Bahasa Indonesia hanya
> untuk tampilan, supaya file ekspor tidak rusak saat diimpor ulang.

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

Tersedia untuk Admin dan Trainee. Tombol Export berada langsung di action bar
(tidak disembunyikan di dalam modal Filter) dan punya dua mode:

```text
Ada baris dicentang  →  "Ekspor Terpilih (N)" : hanya baris tersebut (filter diabaikan)
Tidak ada yang dicentang →  "Ekspor" : seluruh hasil filter status yang aktif

Keduanya meminta **konfirmasi** terlebih dahulu; membatalkan tidak mengirim
permintaan apa pun.
```

Aturan "selected menang" diimplementasikan pada
`TraineeTaskController::exportQuery()`.

Query export memakai **scope yang sama** dengan halaman daftar task, sehingga:

- Admin mengekspor seluruh active task; trainee hanya miliknya sendiri.
- `ids[]` selalu diinterseksi dengan `visibleTo()`, jadi trainee yang mengirim
  UUID milik trainee lain hanya mendapatkan tasknya sendiri (perlindungan IDOR).
- Soft-deleted task tidak pernah ikut terk-export, meskipun dipilih.

Kolom: UUID, Trainee, Task, Description, Start Date, Deadline, Status,
Created At, Updated At.

Nama file:

```text
meeldata-tasks-<timestamp>.xlsx
meeldata-tasks-selected-<timestamp>.xlsx
```

Trainee juga memiliki kolom checkbox, namun **hanya** untuk Export Selected —
`Delete Selected` tetap hanya untuk admin.

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

## 12. Halaman Auth

Satu halaman (`/login`) melayani login admin maupun trainee. **Dua panel
setengah layar bertukar posisi** saat mode berpindah:

| Mode | Panel kiri | Panel kanan |
| --- | --- | --- |
| Masuk | Brand MEeLData | Kartu form |
| Daftar | Kartu form | Brand MEeLData |

- Kedua panel bergerak berlawanan arah bersamaan, memakai `translateX(±100%)`,
  sehingga bertukar tepat tanpa celah dan tetap full-height.
- Di bawah `lg` (mobile) brand disembunyikan, hanya cross-fade.
- Menghormati `prefers-reduced-motion`.
- Berpindahan mode memperbarui URL (`/login` ↔ `/register`) sehingga kedua
  alamat tetap bisa dibuka langsung.
- **Kartu kedua mode sengaja dibuat berbeda** agar mudah dikenali:

| | Masuk | Daftar |
| --- | --- | --- |
| Eyebrow | `Akses trainee & admin` | `Untuk trainee` |
| Judul | Selamat datang kembali | Buat akun Anda |
| Tombol | Masuk (netral) | **Buat akun saya** (aksen violet) |

  Aksen violet (`--color-accent-alt`, hue 295) dijauhkan dari warna status
  (biru/ hijau/merah) dan hanya dipakai di halaman auth, sehingga tidak pernah
  tertukar dengan arti status.

## 13. Bahasa

Aplikasi memakai Bahasa Indonesia (`APP_LOCALE=id`).

- Validasi: `lang/id/validation.php` (termasuk nama atribut & pesan kustom).
- Auth: `lang/id/auth.php`. Pesan reset password disiapkan di
  `lang/id/passwords.php` meskipun fitur tersebut belum dipakai.
- Carbon ikut di-set ke locale `id` di `AppServiceProvider`, sehingga
  `translatedFormat()` menghasilkan nama bulan Indonesia
  (mis. `03 Okt 2026`).

Istilah yang sengaja dibiarkan dalam bentuk Inggris karena lazim dipakai di
 Indonesia dan sudah dipahami pengguna: **Trainee, Status, Filter, Deadline,
Dashboard, Progress, Email**. Header kolom Excel memakai Bahasa Indonesia,
sedangkan nilai status di database tetap teknis (lihat seksi 8).

## 13a. Counter "Terlambat" Realtime

Daftar tugas menampilkan hitungan mundur yang berjalan tiap detik, berbasis
**waktu server**.

```http
GET /admin/tasks/live   (admin)   -> JSON
GET /tasks/live         (trainee, hanya task miliknya)
```

```json
{
  "server_time": "2026-10-03T15:20:30+07:00",
  "mark_after_seconds": 120,
  "poll_seconds": 10,
  "scheduler_down_count": 0,
  "overdue": [{ "id": "01a1…", "deadline": "2026-10-03T15:18:00+07:00" }]
}
```

Prinsipnya:

- **Waktu server adalah acuan.** Client menghitung
  `offset = serverTime − clientTime` lalu men-tick dengan jam perangkat +
  offset. Counter tetap benar walau jam perangkat salah, dan **tidak
  melompat/reset saat halaman di-refresh** karena setiap render
  mengkalibrasi ulang offset.
- **Polling 10 detik, bukan 1 detik** (`LIVE_POLL_SECONDS`). Di antara dua
  polling counter tetap bergerak tiap detik di sisi klien. 6 request per
  menit, bukan 60.
- **Hitung mundur, bukan akumulasi.** Angkanya adalah sisa waktu menuju
  penandaan `late`: `deadline + mark_after − now`. Menjelang 0 ia turun
  seperti stopwatch. Setelah habis, task seharusnya sudah `late`; kalau tidak,
  scheduler mati - dan tampilannya berubah jadi durasi sudah lewat
  (`terlambat 4 menit 48 detik`), bukan `0:00` yang membeku dan menyesatkan.
- Bila Ajax gagal, counter yang sudah dirender server tetap tampil
  (degradasi mulus).
- Payload hanya berisi task **kelompok 4** dan memakai scope `visibleTo()`
  yang sama dengan halaman list — trainee tidak pernah menerima task orang
  lain.

Dua tempat counter muncul:

Counter ini muncul pada **setiap task `progress`**, dengan tiga bentuk
menurut kondisi (`done`/`canceled` tidak perlu - task yang sudah tutup tidak
punya "sisa waktu"):

| Kondisi | Tampilan | Arah |
| --- | --- | --- |
| Deadline belum lewat | `sisa 14:32` · `sisa 2 jam 14 menit` · `sisa 2 hari 3 jam` | **menurun** |
| Deadline lewat, masih dalam jendela penandaan | `dalam 0:45` → `0:01` | **menurun** (`mm:ss`) |
| Jendela habis tapi masih `progress` | `terlambat 4 menit 48 detik` | bertambah - **scheduler mati** |
| Sampah (task `late`) | `terlambat 3 hari 0 jam` | bertambah (menuju 3 hari) |

Bentuk pertama itu yang paling sering dilihat, dan justru yang membuat
hitung mundur berguna: kalau hanya ditampilkan selama jendela 120 detik,
user hampir tidak pernah sempat menangkapnya - karena daftar tugas dibuka jauh
setelah deadline lewat.

Begitu scheduler menandai `late`, polling berikutnya tidak lagi mengirim task
itu, counter berhenti, dan badge `Terlambat` menggantikannya **tanpa reload
halaman**.

Syarat transisinya bukan sekadar "tidak ada di daftar `overdue`" - task
berdeadline depan juga tidak ada di sana. Client menyimpan `wasOverdue(id)`:
hanya task yang **pernah** terlihat overdue lalu menghilang yang berubah jadi
badge. Tanpa itu, seluruh task berdeadline depan akan berubah jadi
`Terlambat` begitu polling pertama selesai.

### Peringatan scheduler mati

Dua kondisi dibedakan dengan sengaja:

- **kelompok 4** (`awaitingScheduler()`) = seluruh progress yang lewat
  deadline. Ini kondisi **normal** selama jendela 120 detik pertama.
- **peringatan** (`overdueProgress()`) = yang lewat **melebihi** jendela
  penandaan dan masih `progress`. Hanya ini yang berarti "scheduler tidak
  jalan".

Kalau peringatan memakai hitungan kelompok 4, setiap task yang baru lewat
deadline akan memicu peringatan palsu selama 120 detik, dan peringatannya
akan diabaikan pengguna.

## 13b. Urutan Prioritas Task

Daftar task diurutkan 5 tingkat (`TraineeTask::scopeByWorkPriority`), dari
prioritas tertinggi:

| # | Kelompok | Kondisi |
| --- | --- | --- |
| 1 | **Sedang Dikerjakan** | `progress` dan deadline belum lewat |
| 2 | **Selesai** | `done` |
| 3 | **Dibatalkan** | `canceled` |
| 4 | **Lewat Deadline — Menunggu Penandaan** | `progress` dan deadline sudah lewat |
| 5 | **Terlambat** | `late` |

Catatan penting:

- **Kelompok 2 dan 3 tetap berada di atas kelompok 4.** Task yang sudah
  tutup dianggap selesai sehingga tidak ikut tenggelam, walaupun task lain
  belum dikerjakan.
- **`done`/`canceled` tidak pernah ikut turun** ke kelompok 4 walaupun
  deadline-nya sudah lewat.
- **Kelompok 4 praktis hanya buffer sesaat**: `tasks:mark-late` jalan setiap
  menit, jadi isinya menjadi kelompok 5 paling lambat satu menit kemudian.
  Kalau kelompok 4 **menetap**, berarti cron tidak aktif — dan itulah yang ditampilkan
  banner peringatan, bukan sekadar kategori biasa.
- Badge status di kelompok 4 tetap `Progress` (data apa adanya); yang berubah
  hanya label kelompok dan warna baris.
- Label kelompok dirender sebagai baris penuh tepat di atas kelompoknya.
  `colspan` 7 di panel admin, 6 di panel trainee.

Urutan yang sama dipakai oleh **export**, jadi isi file mencerminkan layar.

Implementasi memakai `orderByRaw` dengan `now()` sebagai binding, **bukan**
`NOW()` SQL: kolom tanggal menyimpan waktu `Asia/Jakarta`, sedangkan `NOW()`
memakai timezone session MySQL — di server UTC keduanya meleset 7 jam.

## 14. Design System

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
- **Responsif tanpa memaksa** — tabel tetap surface utama di `md` ke atas.
  Di bawah `md` setiap task dirender sebagai **kartu** (nama di atas, lalu
  pasangan label → nilai untuk Mulai/Deadline, baris kaki berisi status dan
  aksi) karena tabel 740–1000px hanya menyisakan ~48% lebar di layar HP
  390px. Lihat §14a.

### 14a. Tampilan Layar Sempit

Dua hal yang perlu diketahui kalau mengubah halaman bertabel:

| Hal | Aturan |
| --- | --- |
| Ganti tabel ↔ kartu | Ganti di bawah `md` berarti kartu; di `md` ke atas berarti tabel. Kedua bentuk **tetap ada di HTML**, hanya visibilitasnya yang berbeda |
| Wadah scroll tabel | WAJIB `table-scroll`, bukan `overflow-x-auto` polos. Berlaku untuk **semua** halaman bertabel, termasuk Sampah |

**Kenapa `overflow-x-auto` polos tidak cukup.** Terbukti lewat pengukuran di
browser: sebuah `div` selebar 939px di dalam wadah `overflow-x: auto` yang
sama **tidak** membuat halaman bisa digeser ke samping — tapi `<table>`
selebar 939px di dalamnya **membocorkan layout overflow** sampai ke scroll
area root. Akibatnya di viewport 820px dokumen punya 934px, dan seluruh
halaman termasuk header sticky bergeser 114px ke kiri saat digeser.

Kelas `.table-scroll` di `resources/css/app.css` menambahkan `contain: paint`
untuk memotong overflow itu di husk wadahnya:

```css
.table-scroll {
    @apply overflow-x-auto;
    contain: paint;
}
```

Scroll horizontal di dalam tabel tetap berfungsi, dan `position: sticky`
pada header tabel tetap memakai scrollport yang sama (diuji: hasilnya identik
dengan `contain: none`). Jangan menghapusnya — dengan `contain: paint`
dihapus, `scrollX` di 820px kembali menjadi 114.

**Konsekuensi yang harus diingat: `contain: paint` menjadikan `.table-scroll`
sebagai containing block untuk `position: fixed` di dalamnya.** Koordinat
dropdown status yang ber-position `fixed` karena itu dihitung **relatif ke
wadah**, bukan ke viewport. Kalau dihitung terhadap viewport, menu meleset
ratusan piksel — sering keluar dari area yang terlihat, dan di baris terakhir
sama sekali tidak terlihat.

`position()` di `<x-status-select>` karena itu memakai area yang boleh dipakai =
**irisan kotak wadah dengan viewport** (`fixedHost()` mencari ancestor yang
membuat containing block: `contain`, `transform`, `filter`, `backdrop-filter`,
`perspective`, atau `will-change`). Flutter/animasi `transform` pun akan
menyebabkan hal yang sama, jadi pemeriksaannya disengaja umum, bukan
khusus `contain: paint`.

`x-teleport="body"` terlihat sebagai solusi yang lebih bersih, tapi gagal di
Alpine untuk elemen ini (`cloneNode` dari `undefined`). Penyebabnya belum ditelusuri sampai tuntas;
sekarang Approach yang sudah terbukti dipakai, bukan teleport.


### Komponen Blade

`<x-icon>` · `<x-button>` · `<x-status-badge>` · `<x-empty-state>` ·
`<x-field-error>` · `<x-toast>` · `<x-confirm-modal>` · `<x-status-select>` ·
`<x-clock>`

### Inline status update (design.md §8.1)

Badge status di tabel **sama sekali** adalah pemicunya — tidak ada tombol
"Ubah" terpisah:

```text
 Tertutup        Dibuka
┌──────────┐    ┌──────────┐
│ ● Progress ⌄ │ → │ Progress   │
└──────────┘    │ ✔ Selesai   │
                │ Dibatalkan  │
                └───────────┘
```

- Warna badge & trigger berasal dari satu sumber: `TaskStatus::badgeClasses()`,
  dipakai bersama oleh `<x-status-badge>` dan `<x-status-select>` sehingga
  tidak mungkin berbeda warna.
- Pemecahan tanggung jawab: `<x-status-select>` **hanya** untuk status yang
  boleh diubah user. Sulit status milik sistem (`Terlambat`) tidak pernah
  dirender sebagai pemicu; `tasks/index.blade.php` yang memilih antara
  `<x-status-badge>` statis dan `<x-status-select>`.
- `aria-expanded` + `aria-controls` menunjuk menu; `aria-label="Ubah status: …"`
  tetap memuat label yang terlihat (WCAG 2.5.3 Label in Name).

#### `id` harus unik lintas kartu dan tabel

Halaman daftar merender task yang sama **dua kali**: sebagai kartu
(`md:hidden`, untuk layar < `md`) dan sebagai baris tabel. Keduanya berada di
satu dokumen, jadi komponen yang menghasilkan `id` wajib diberi akhiran
instance:

| Komponen | `id` |
| --- | --- |
| `<x-status-select>` | `status-menu-table-<uuid>` / `status-menu-card-<uuid>` |
| `edit-button` | `confirm-edit-table-<uuid>` / `confirm-edit-card-<uuid>` |
| `delete-button` | `confirm-delete-table-<uuid>` / `confirm-delete-card-<uuid>` |

Tanpa itu terjadi 25 pasang `id` kembar per halaman. Dampaknya bukan kosmetik:

- HTML tidak valid — `id` harus unik dalam satu dokumen.
- `aria-controls` dan `aria-labelledby` menunjuk elemen yang ambigu, sehingga
  screen reader bisa membacakan menu/dialog yang sedang tersembunyi.
- `document.getElementById()` mengembalikan yang pertama di document order, yaitu
  **kartu yang sedang `display: none`** di layar lebar. Akibatnya dropdown status
  "tidak terbuka" padahal `aria-expanded` sudah `true` — gejalanya sangat mirip
  bug Alpine, padahal Alpine-nya benar.

Ditutup oleh `test_status_dropdown_ids_are_unique_across_card_and_table()` dan
`test_edit_confirmation_ids_are_unique_across_card_and_table()`. Keduanya sudah
diuji dengan mutasi: mengembalikan `id` ke bentuk kembar akan menggagalkan test.
- Menu diposisikan **`fixed`** dan dihitung ulang saat scroll/resize. Alasannya:
  wrapper tabel memakai `overflow-x-auto`, dan menurut spesifikasi CSS satu
  sumbu `overflow: auto` membuat sumbu lainnya ikut terpotong — dropdown di
  baris terakhir ikut terclip. Posisi juga otomatis **flip ke atas** bila
  ruang di bawah tidak cukup.
- Tanpa JavaScript tersedia fallback `<noscript>` berisi `<select>` native
  plus tombol Simpan, karena dropdown tidak bisa dibuka tanpa JS.
- Status `Terlambat` tidak punya affordance apa pun: bukan `<button>`, tanpa
  chevron, tidak bisa difokuskan — hanya badge statis + `title` tooltip.
  Lihat [status `late` sebagai status turunan](#perilaku-status-late-status-turunan-bukan-status-mutlak).

### Aksesibilitas

- Focus trap pada semua modal (`x-trap`) + tombol close + Escape
- Focus ring selalu terlihat (`:focus-visible`)
- Label pada seluruh input, `aria-label` pada ikon-only button
- Status tidak hanya dibedakan warna (label teks selalu ada)
- `role="status"` / `role="alert"` untuk feedback
- Scrollbar tipis, tabel dapat digeser horizontal di layar kecil
- Dropdown status tetap utuh di viewport (flip ke atas + clamp kiri/kanan)

## 15. Struktur Direktori

```
app/
├── Console/Commands/MarkLateTasks.php     # tasks:mark-late
├── Enums/TaskStatus.php                   # status terpusat
├── Exports/
│   ├── TasksExport.php                    # export berbasis query
│   └── TaskTemplateExport.php             # template import
├── Http/Controllers/
│   ├── Auth/                              # login (satu gateway) & register
│   ├── DashboardController.php
│   └── TraineeTaskController.php          # CRUD + export selected
├── Http/Requests/                         # validasi server-side
├── Imports/TasksImport.php                # partial success
└── Models/
    ├── User.php                           # role: admin | trainee
    └── TraineeTask.php

resources/views/
├── auth/auth.blade.php                    # satu halaman (login | register)
├── components/                            # icon, button, badge, auth-card, dll
├── dashboard/                             # dashboard + partial list
├── errors/403.blade.php
├── layouts/                               # app & guest
├── tasks/                                 # index, create, edit, partials
└── vendor/pagination/                     # pagination view kustom
```