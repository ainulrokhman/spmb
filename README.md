# SPMB — Sistem Penerimaan Murid Baru

[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-round&logo=laravel)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.2-777BB4?style=flat-round&logo=php)](https://php.net)
[![AdminLTE](https://img.shields.io/badge/AdminLTE-4.0.0-blue?style=flat-round)](https://adminlte.io)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=flat-round)](https://opensource.org/licenses/MIT)

SPMB adalah sistem penerimaan murid baru untuk **satu yayasan dengan banyak
unit sekolah** (PAUD sampai MA, termasuk SMK). Setiap unit — satu jenjang +
satu program (Reguler/Boarding) — mengelola sendiri jalur pendaftaran,
gelombang, jurusan, syarat berkas, formulir, biaya, dan panitianya; hanya
Tahun Ajaran yang bersifat global untuk seluruh yayasan.

Orang tua mendaftar memakai **akun Wali** (login nomor HP), dapat
mendaftarkan banyak anak ke unit yang berbeda, dan mengikuti alur yang
berurutan: **Daftar Akun → Bayar Formulir → Isi Formulir → Input Berkas →
Verifikasi → Pengumuman → Daftar Ulang**.

Rancangan lengkap, keputusan desain, dan peta implementasi ada di
[docs/rancangan-fitur-spmb.md](docs/rancangan-fitur-spmb.md).

---

## 🚀 Fitur Utama

*   **Master data per unit** — Unit Sekolah sebagai poros; jalur, jurusan,
    gelombang (otomatis berdasarkan tanggal + penimpa manual), jenis berkas
    & syarat per unit, bidang formulir dinamis per unit (termasuk tipe
    Pilihan Unit Sekolah dan Saudara Kandung), dan Status Anak sebagai
    master bersama yayasan.
*   **Panitia per unit** — petugas hanya melihat dan mengelola unit
    tugasnya; Super Admin dan Admin yayasan bersifat global.
*   **Pendaftaran wali** — satu akun untuk banyak anak; hubungan per anak
    (Ayah/Ibu/Wali) dengan formulir identitas orang tua; nomor pendaftaran
    7 digit terbit saat pendaftaran dikirim.
*   **Biaya fleksibel** — komponen biaya (SPI, SOP, DU, INFAQ, dst.) dengan
    mode `fixed`/`pilihan`/`bebas`, nominal dibedakan per jalur, jenis
    kelamin, dan status anak; komponen dapat dinonaktifkan per unit tanpa
    kehilangan nominalnya.
*   **Pembayaran multi-kanal berbentuk driver** — kanal **Manual** (wali
    menyatakan bayar dengan bukti transfer, panitia mengonfirmasi) dan
    **Midtrans** (Snap + webhook bertanda tangan terverifikasi); driver
    **BSI** menyusul setelah akses API bank tersedia. Kanal baru dapat
    ditambah tanpa mengubah alur.
*   **Keringanan & cicilan** — wali mengajukan dengan alasan, panitia yang
    menentukan bentuk keringanan (pembebasan/persen/nominal) atau memberi
    langsung; cicilan tagihan daftar ulang dirinci panitia **per
    komponen** (matriks termin × komponen).
*   **Verifikasi & seleksi poin** — verifikasi per berkas oleh panitia;
    seleksi poin opsional per unit (aturan otomatis dari umur, jawaban
    formulir, asal sekolah, saudara kandung, dan status anak + kriteria
    manual + koreksi beralasan), dengan batas poin dan penetapan massal
    **Tetapkan dari Poin**. Wali tidak melihat poin.
*   **Pengumuman per jalur** — waktu pengumuman dapat diatur per jalur
    dengan waktu global gelombang sebagai cadangan; hasil tidak terlihat
    wali sebelum waktunya.
*   **Transparansi untuk wali** — halaman Rincian Pendaftaran (semua data
    yang sudah diinput) dan Jadwal Penting, jadwal terdekat di dasbor.
*   **Notifikasi di dalam aplikasi** — petugas menerima pemberitahuan
    pengajuan & pembayaran yang menunggu keputusan; wali menerima
    pemberitahuan keputusan, hasil verifikasi, status pembayaran, dan
    **pengingat jatuh tempo termin** (3 hari sebelum dan pada hari H).
*   **Laporan** — ringkasan SPMB per unit, laporan pembayaran formulir,
    Daftar Siswa semua status, dan export CSV.

---

## 🛠️ Teknologi

*   **Backend**: Laravel 12 (PHP 8.2+)
*   **Frontend**: Blade + Bootstrap 5 + Bootstrap Icons + AdminLTE 4
*   **Tabel**: Yajra DataTables (server-side)
*   **Hak akses**: Spatie Laravel Permission (RBAC)
*   **Asset Bundler**: Vite

---

## 📋 Prasyarat

*   **PHP** >= 8.2 (ekstensi standar Laravel: `pdo`, `mbstring`, `openssl`, `xml`, dll.)
*   **Composer**
*   **Node.js** (LTS) & **NPM**
*   **Database**: MySQL (produksi & test) atau SQLite (pengembangan lokal)

---

## ⚙️ Instalasi

```bash
# 1. Clone repository
git clone https://github.com/ainulrokhman/spmb.git
cd spmb

# 2. Install dependensi PHP dan frontend
composer install
npm ci

# 3. Konfigurasi environment
cp .env.example .env
php artisan key:generate
```

Edit `.env` sesuai lingkungan Anda — terutama koneksi database, serta
variabel pada bagian **Konfigurasi Penting** di bawah.

```bash
# 4. Migrasi + data awal (role, izin, master SPMB, akun Super Admin awal)
php artisan migrate --seed

# 5. Tautan penyimpanan publik (wajib untuk berkas & bukti transfer)
php artisan storage:link

# 6. Build aset frontend
npm run build

# 7. Jalankan aplikasi
php artisan serve
```

Aplikasi berjalan di `http://localhost:8000`. Halaman info pendaftaran
publik tersedia di `/pendaftaran`, area wali di `/wali`, dan panel admin
di `/admin` (login staf).

---

## 🔧 Konfigurasi Penting

### Akun Super Admin awal

Akun awal dibuat oleh `InitialAdminSeeder` saat `db:seed`, dari variabel:

```dotenv
SUPER_ADMIN_NAME="Super Admin"
SUPER_ADMIN_EMAIL=superadmin@example.com
SUPER_ADMIN_PASSWORD=
```

`SUPER_ADMIN_PASSWORD` **wajib diisi** — bila kosong, akun awal tidak
dibuat dan seeder hanya menampilkan peringatan. Ganti kata sandi ini di
lingkungan produksi.

### Kanal pembayaran Midtrans

Isi dari dashboard Midtrans (sandbox atau production):

```dotenv
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_PRODUCTION=false
```

Kanal Manual tidak membutuhkan kunci — instruksi pembayaran untuk wali
diatur dari menu **Pengaturan** di panel admin. Kanal dapat
diaktifkan/dinonaktifkan dari Pengaturan juga.

### Penjadwal (scheduler)

Pengingat jatuh tempo termin dan pembersihan notifikasi lama berjalan
lewat penjadwal Laravel. Di server produksi, daftarkan cron berikut
(sekali saja):

```cron
* * * * * cd /path/ke/spmb && php artisan schedule:run >> /dev/null 2>&1
```

---

## 👥 Data Demo (lokal saja)

Untuk mencoba alur lengkap tanpa mengisi data dari nol:

```bash
php artisan db:seed --class=DemoSeeder
```

Semua akun demo memakai kata sandi `password`:

| Akun | Keterangan |
| --- | --- |
| `superadmin@example.com` | Super Admin |
| `admin@example.com` | Admin yayasan |
| `panitia.smp@example.com` | Panitia unit SMP |
| `panitia.smk@example.com` | Panitia unit SMK |
| HP `081200000001` s.d. `081200000003` | Wali (login dengan nomor HP) |

Data demo membuat beberapa pendaftaran contoh pada berbagai tahap alur.
**Jangan jalankan seeder ini di produksi.**

---

## 🧪 Test

Suite test memakai database MySQL tersendiri sesuai `phpunit.xml`
(database `spmb-testing`, user `root`, kata sandi `root`). Buat database
tersebut terlebih dahulu, lalu:

```bash
composer install
npm ci
npm run build   # wajib: test yang merender halaman butuh manifest Vite
php artisan test
```

Suite yang sama berjalan otomatis lewat GitHub Actions pada setiap push
dan pull request ke branch `done`.

---

## 📄 Lisensi

Proyek ini adalah perangkat lunak sumber terbuka berlisensi
[MIT license](LICENSE).
