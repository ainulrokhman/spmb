# Rancangan Fitur SPMB (Sistem Penerimaan Murid Baru)

- **Status:** Draf — menunggu peninjauan & pengesahan pemilik proyek
- **Tanggal:** 31 Oktober 2026
- **Dasar sistem:** Laravel Admin (RBAC, Service + Form Request + DataTable, activity log)

## 1. Gambaran Umum

Satu sistem penerimaan murid baru untuk satu yayasan yang menaungi banyak
satuan pendidikan, dari PAUD sampai MA, termasuk SMK, dengan program
Reguler dan Boarding. Setiap satuan (unit) dapat memiliki jalur,
gelombang, jurusan, syarat berkas, formulir, dan biaya yang berbeda.

### Aktor

| Aktor | Peran |
| --- | --- |
| Super Admin | Pengelola penuh seluruh unit dan master data |
| Panitia | Petugas verifikasi & seleksi pendaftar, pengelola data operasional |
| Pendaftar (orang tua / calon siswa) | Mendaftar, melengkapi formulir & berkas, memantau status, daftar ulang |

### Prinsip desain

1. **Semua per unit, kecuali Tahun Ajaran.** Jalur, gelombang, jurusan,
   syarat berkas, formulir, dan biaya diatur per satuan pendidikan.
2. **Konfigurasi, bukan kode.** Perbedaan antarunit dinyatakan sebagai
   data (master & pemetaan), bukan cabang logika di kode.
3. **Pola modul mengikuti proyek dasar:** Controller tipis → Service via
   Contract → Form Request → DataTable, dilengkapi test per modul.

## 2. Modul Master Data

### 2.1 Tahun Ajaran (`academic_years`) — global

- Kolom: `name` (mis. 2026/2027), `is_active`.
- Aturan: hanya boleh ada satu tahun ajaran aktif; mengaktifkan satu
  akan menonaktifkan yang lain (dijaga di Service).

### 2.2 Unit Sekolah (`school_units`) — poros sistem

- Kolom: `name`, `code` (kode numerik **2 digit**, unik, diisi bebas
  oleh panitia; disimpan sebagai teks agar angka nol di depan tidak
  hilang; dipakai pada nomor pendaftaran), `level` (salah satu dari:
  PAUD/TK, SD/MI, SMP/MTs, SMA, SMK, MA), `program` (**Reguler** atau
  **Boarding**), `address`, `phone`, `is_active`.
- **Satu satuan = satu jenjang + satu program.** Sekolah yang membuka
  program Reguler dan Boarding didaftarkan sebagai dua satuan terpisah
  — mis. `31` SMP Reguler dan `32` SMP Boarding (skema digit adalah
  konvensi panitia, sistem hanya mewajibkan 2 digit dan unik). Dengan
  begitu gelombang, jalur, berkas, formulir, dan biaya otomatis
  terpisah mengikuti satuannya.

### 2.3 Jalur Pendaftaran (`admission_tracks`) — per unit

- Kolom: `school_unit_id`, `name` (mis. Reguler, Prestasi, Afirmasi,
  Tahfidz), `description`, `is_active`.
- Tiap unit mengelola daftar jalurnya sendiri.

### 2.4 Jurusan (`majors`) — per unit

- Kolom: `school_unit_id`, `name`, `code`, `is_active`.
- Dipakai terutama oleh unit SMK (dan peminatan SMA/MA bila perlu).

### 2.5 Gelombang Pendaftaran (`admission_waves`) — per unit

- Kolom: `school_unit_id`, `academic_year_id`, `name` (mis. Gelombang 1),
  `quota` (nullable = tanpa batas), `opens_at`, `closes_at`, `is_active`.
- Relasi many-to-many ke Jalur (`admission_wave_track`): satu gelombang
  membuka satu atau beberapa jalur **milik unit yang sama** (dijaga di
  Form Request & Service).
- Validasi: `closes_at` tidak boleh sebelum `opens_at`.

### 2.6 Jenis Berkas (`document_types`) & Syarat Berkas per Unit

- `document_types`: `name`, `code`, `allowed_extensions`
  (mis. `jpg,jpeg,png,pdf`), `max_size_kb`, `is_active`.
  Contoh awal: Pas Foto, Kartu Keluarga, Akta Kelahiran, Ijazah/SKL,
  Rapor, Sertifikat Prestasi.
- Pivot `document_requirements`: `school_unit_id` × `document_type_id` ×
  `requirement` (`required` / `optional`). Berkas yang tidak dipetakan
  berarti tidak dipakai unit tersebut.
- Diatur dari satu halaman per unit: daftar semua jenis berkas dengan
  pilihan Tidak Dipakai / Opsional / Wajib.

### 2.7 Bidang Formulir (`form_fields`) — per unit, formulir dinamis

- Kolom: `school_unit_id`, `key` (unik per unit), `label`, `type`
  (`text`, `textarea`, `number`, `date`, `select`, `radio`, `checkbox`,
  `file`), `options` (JSON, untuk tipe pilihan), `is_required`,
  `section` (pengelompokan, mis. Data Siswa / Data Orang Tua),
  `sort_order`, `help_text`, `is_active`.
- Bidang inti bersifat tetap sebagai kolom sungguhan pada pendaftaran
  (tidak didefinisikan di sini): nama lengkap, tempat & tanggal lahir,
  jenis kelamin, nama orang tua/wali, nomor HP orang tua.
- Jawaban bidang dinamis disimpan sebagai JSON pada data pendaftaran.

## 3. Modul Pendaftaran (Publik)

- Halaman info publik menampilkan unit yang membuka pendaftaran beserta
  gelombang yang sedang berjalan.
- Pendaftar membuat akun (role **Pendaftar**) lalu mengisi pendaftaran:
  1. Pilih Satuan (jenjang + program Reguler/Boarding melekat pada
     satuan yang dipilih)
  2. Pilih Gelombang & Jalur (dan Jurusan bila satuan memilikinya)
  3. Isi formulir dinamis unit tersebut
  4. Unggah berkas sesuai Syarat Berkas unit (wajib harus lengkap)
- **Nomor pendaftaran** terbit saat pendaftaran dikirim, dengan format
  gabungan 7 digit: kode unit 2 digit + tahun awal tahun ajaran
  2 digit + nomor urut 3 digit. Contoh: unit berkode `03`, tahun ajaran
  2026/2027, pendaftar ke-7 → **`0326007`**. Nomor urut berjalan per
  unit per tahun ajaran (kembali ke 001 setiap tahun ajaran baru);
  bila urutan melewati 999, digit ditampilkan apa adanya.
- Pendaftaran dapat disimpan sebagai draf dan diubah selama belum
  diverifikasi panitia dan gelombang belum ditutup.
- Status alur pendaftaran:
  `Draft → Menunggu Verifikasi → Terverifikasi / Perlu Perbaikan /
  Ditolak → Lulus / Tidak Lulus → Daftar Ulang (terkonfirmasi)`.
- Pendaftar dapat mengunduh/mencetak bukti pendaftaran.

## 4. Modul Verifikasi & Seleksi (Panitia)

- Daftar pendaftar per unit/gelombang/jalur dengan filter status
  (DataTable).
- Verifikasi per berkas: Setujui / Tolak + catatan. Berkas ditolak
  mengubah status pendaftaran menjadi **Perlu Perbaikan** dan pendaftar
  dapat mengunggah ulang berkas tersebut.
- Penetapan kelulusan oleh panitia pada pendaftaran berstatus
  Terverifikasi. Sistem memberi peringatan bila jumlah lulus melebihi
  kuota gelombang (tidak mengunci, hanya peringatan).

## 5. Modul Pengumuman & Daftar Ulang

- Halaman cek kelulusan publik: nomor pendaftaran + tanggal lahir.
- Pendaftar berstatus **Lulus** melakukan daftar ulang dari akunnya:
  sistem menyusun tagihan biaya (lihat Bab 6), pendaftar memilih opsi
  komponen pilihan dan mengisi komponen bebas, lalu mengonfirmasi.
- Daftar ulang tercatat dengan waktu konfirmasi; status pendaftaran
  menjadi **Daftar Ulang (terkonfirmasi)**.

## 6. Modul Biaya Daftar Ulang

### 6.1 Komponen Biaya (`fee_components`) — master bersama

- Kolom: `code`, `name`, `description`, `is_active`.
- Komponen awal: **SPI** (Sumbangan Pengembangan Institusi), **SOP**,
  **DU** (Daftar Ulang), **INFAQ**. Komponen lain (seragam, buku, dll.)
  dapat ditambahkan bebas.

### 6.2 Pemetaan Biaya per Unit (`fees`)

Setiap komponen yang dipakai sebuah unit dipetakan dengan satu **mode**:

| Mode | Perilaku saat daftar ulang |
| --- | --- |
| `fixed` | Nominal ditentukan sistem, langsung masuk tagihan |
| `pilihan` | Orang tua wajib memilih satu tingkat nominal yang ditawarkan |
| `bebas` | Orang tua mengisi nominal sendiri (dapat diberi batas minimum) |

Contoh penerapan: SPI dapat bermode `pilihan` di satu unit dan `fixed`
di unit lain; SOP dan DU umumnya `fixed`; INFAQ umumnya `bebas`.

### 6.3 Diferensiasi nominal

Nominal (mode `fixed`) maupun tingkat pilihan (mode `pilihan`) dapat
dibedakan menurut:

- **Global** — berlaku untuk semua pendaftar pada unit tersebut
- **Jalur** — nominal khusus untuk jalur tertentu milik unit itu
- **Jender** — nominal khusus laki-laki / perempuan

Penyelesaian memakai aturan **paling spesifik menang**:
kombinasi jalur+jender → jalur → jender → global. Panitia cukup mengisi
tarif global dan hanya menulis pengecualian yang memang berbeda.

### 6.4 Tagihan (`invoices` & `invoice_items`)

- Saat daftar ulang dimulai, sistem menyusun tagihan dari pemetaan
  biaya berdasarkan unit, jalur, dan jender pendaftar:
  - komponen `fixed` → nominal hasil penyelesaian diferensiasi
  - komponen `pilihan` → menunggu pilihan tingkat oleh orang tua
  - komponen `bebas` → menunggu isian nominal orang tua
- Tagihan berisi rincian per komponen dan total; tersimpan sebagai
  potret (snapshot) — perubahan pemetaan biaya sesudahnya tidak
  mengubah tagihan yang sudah terbit.
- Status pembayaran: `Belum Bayar → Menunggu Konfirmasi → Lunas`
  (konfirmasi manual oleh panitia; pembayaran via gateway adalah
  keputusan terpisah, lihat Bab 8).

## 7. Dashboard & Laporan

- Ringkasan per unit: jumlah pendaftar per gelombang, jalur, dan status.
- Rekap daftar ulang & pembayaran per unit.
- Export CSV daftar pendaftar dan rekap tagihan; cetak formulir dan
  bukti daftar ulang.

## 8. Keputusan Terbuka (menunggu pemilik proyek)

1. ~~Format nomor pendaftaran~~ — **SUDAH DIPUTUSKAN** (Bab 3):
   kode unit 2 digit + tahun awal 2 digit + nomor urut 3 digit.
2. Pendaftaran memakai akun pendaftar (usulan rancangan ini) atau
   formulir tanpa akun.
3. Seleksi cukup verifikasi berkas & penetapan manual (usulan v1), atau
   perlu nilai/peringkat.
4. INFAQ bermode `bebas` — benar, atau `pilihan`/`fixed`.
5. Pembayaran: konfirmasi manual dulu (usulan v1) atau langsung
   gateway (Midtrans dll.).
6. Panitia bersifat global (semua unit) dulu di v1, atau perlu dibatasi
   per unit sejak awal.
7. Gelombang dibuka/ditutup otomatis mengikuti tanggal, atau manual
   oleh panitia (usulan: keduanya — tanggal sebagai bawaan, saklar
   manual sebagai penimpa).

## 9. Peta Fase Implementasi

| Fase | Isi |
| --- | --- |
| 1A | Tahun Ajaran, Unit Sekolah, Jalur (per unit), Jurusan (per unit) |
| 1B | Gelombang, Jenis Berkas + Syarat Berkas per Unit |
| 1C | Bidang Formulir per Unit |
| 1D | Komponen Biaya + Pemetaan Biaya per Unit |
| 2 | Akun pendaftar, formulir dinamis, unggah berkas, nomor pendaftaran |
| 3 | Verifikasi berkas & penetapan kelulusan |
| 4 | Pengumuman, daftar ulang & tagihan, konfirmasi pembayaran |
| 5 | Dashboard SPMB & laporan/export |

## 10. Di Luar Cakupan v1

- Payment gateway dan notifikasi WhatsApp/email otomatis.
- Multi-yayasan (satu instalasi untuk banyak yayasan).
- Aplikasi mobile.
