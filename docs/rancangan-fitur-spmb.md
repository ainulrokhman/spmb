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
| Panitia | Petugas verifikasi pendaftar, **terikat pada unit yang ditugaskan kepadanya** |
| Wali (orang tua) | Pemilik akun pendaftaran; **satu akun dapat mendaftarkan lebih dari satu anak**; login memakai **nomor HP** |

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
- Pendaftaran memakai **akun Wali** (role **Wali** pada tabel `users`,
  dengan kolom `phone` unik sebagai identitas login). Wali **login
  dengan nomor HP + kata sandi**; akun staf/panitia tetap login dengan
  email seperti bawaan sistem.
- Satu akun wali dapat membuat **banyak pendaftaran — satu pendaftaran
  per anak** — dan anak-anak itu boleh mendaftar di satuan/gelombang
  yang berbeda. Data anak tersimpan pada pendaftarannya masing-masing;
  data wali (nama, nomor HP) tersimpan pada akun.
- **Hubungan pendaftar** dengan anak dipilih pada setiap pendaftaran
  (satu akun yang sama dapat menjadi ayah bagi satu anak dan wali bagi
  anak lain), dengan 3 jenis: **Ayah**, **Ibu**, atau **Wali**. Pilihan
  ini menentukan bagian identitas mana yang terisi otomatis dari data
  akun (nama dan nomor HP); isinya tetap dapat diubah per pendaftaran.
- Setiap pendaftaran dilengkapi **formulir identitas orang tua**:
  identitas **Ayah** dan identitas **Ibu** selalu ada dan wajib diisi,
  sedangkan identitas **Wali** hanya diisi bila anak memiliki wali
  (ditandai secara eksplisit pada formulir). Satu identitas berisi:
  nama lengkap, status masih hidup/meninggal (khusus ayah dan ibu),
  NIK (opsional), pekerjaan, pendidikan terakhir, penghasilan bulanan,
  nomor HP, dan alamat bila berbeda; identitas wali ditambah hubungan
  dengan anak. Ketiganya disimpan sebagai data terstruktur (JSON) pada
  pendaftaran.
- Pendaftaran tiap anak mengikuti **alur berurutan yang tidak dapat
  dilompati** — tahap berikutnya terkunci sampai tahap sebelumnya
  selesai:
  1. **Register** — wali membuat akun dan masuk.
  2. **Mulai pendaftaran** — pilih Satuan (jenjang + program
     Reguler/Boarding melekat pada satuan yang dipilih), Gelombang &
     Jalur (dan Jurusan bila satuan memilikinya); biaya formulir unit
     dibekukan pada pendaftaran sejak langkah ini.
  3. **Bayar Formulir** — biaya formulir diselesaikan lebih dulu
     (Bab 3.1) melalui kanal pembayaran yang aktif (Bab 6.4); formulir
     tidak dapat dibuka sebelum tahap ini beres.
  4. **Isi Formulir** — formulir dinamis unit tersebut, dilengkapi
     identitas ayah dan ibu, serta wali bila anak memilikinya.
  5. **Input Berkas** — unggah berkas sesuai Syarat Berkas unit
     (wajib harus lengkap), lalu pendaftaran dikirim.
- **Nomor pendaftaran** terbit saat pendaftaran dikirim, dengan format
  gabungan 7 digit: kode unit 2 digit + tahun awal tahun ajaran
  2 digit + nomor urut 3 digit. Contoh: unit berkode `03`, tahun ajaran
  2026/2027, pendaftar ke-7 → **`0326007`**. Nomor urut berjalan per
  unit per tahun ajaran (kembali ke 001 setiap tahun ajaran baru);
  bila urutan melewati 999, digit ditampilkan apa adanya.
- Kemajuan tiap tahap tersimpan, sehingga pendaftaran dapat
  dilanjutkan di lain waktu selama gelombang belum ditutup; data yang
  sudah terisi dapat diubah selama belum diverifikasi panitia, tetapi
  urutan tahap tidak dapat dibalik dan pilihan satuan terkunci sejak
  biaya formulir diselesaikan.
- Status alur pendaftaran:
  `Menunggu Pembayaran Formulir → Mengisi Formulir → Mengunggah
  Berkas → Menunggu Verifikasi → Terverifikasi / Perlu Perbaikan /
  Ditolak → Lulus / Tidak Lulus → Daftar Ulang (terkonfirmasi)`.
- Pendaftar dapat mengunduh/mencetak bukti pendaftaran.

### 3.1 Biaya Formulir, Pembebasan & Diskon

- **Biaya formulir** diatur **per unit sekolah** (bidang nominal pada
  data unit); besarnya dapat berbeda antarunit, dan nilai 0 berarti
  unit tersebut tidak memungut biaya formulir. Biaya ini terpisah dari
  biaya daftar ulang pada Bab 6.
- Saat pendaftaran **dimulai**, nominal biaya formulir unit dibekukan
  sebagai potret (snapshot) pada pendaftaran, beserta **status biaya
  formulir**: `Gratis` (unit bernominal 0), `Belum Bayar`, `Lunas`,
  atau `Dibebaskan`. Perubahan nominal unit sesudahnya tidak mengubah
  pendaftaran yang sudah dibuat. Pembayaran dilakukan pada tahap
  Bayar Formulir melalui kanal pembayaran yang aktif (Bab 6.4).
- **Pembebasan**: calon siswa khusus dapat **dibebaskan** dari biaya
  formulir. Pada tahap pembayaran, wali **mengajukan** pembebasan dan
  **panitia memutuskan** pengajuan tersebut (disetujui atau ditolak)
  dengan alasan yang dicatat (mis. anak yatim, keluarga tidak mampu,
  anak karyawan); bila disetujui, status biaya formulir menjadi
  `Dibebaskan` dan alur berlanjut tanpa pembayaran.
- **Diskon**: biaya yang sudah diatur dapat diberi potongan per
  pendaftaran, diajukan wali dan diputuskan panitia pada tahap yang
  sama. Potongan diatur dengan **tipe** yang dapat dipilih —
  **persen** (0–100%) atau **nominal** (Rp) — dan rancangan tipe ini
  terbuka untuk ditambah jenis lain menyusul. Setiap potongan
  mencatat tipe, nilai, dan alasannya; nominal akhir tidak boleh
  kurang dari nol, dan sisa nominal setelah potongan dibayar wali
  melalui kanal pembayaran. Pembebasan adalah kasus khusus: potongan
  penuh (100%). Mekanisme potongan yang sama berlaku juga untuk
  tagihan daftar ulang (Bab 6.4).

## 4. Modul Verifikasi & Seleksi (Panitia)

- **Panitia terikat unit**: penugasan diatur lewat pivot
  `user_school_units` (satu panitia dapat ditugaskan ke beberapa unit).
  Daftar pendaftar, verifikasi, dan laporan yang dilihat panitia hanya
  untuk unit tugasnya; Super Admin dan Admin yayasan melihat semua
  unit. Penugasan diatur dari halaman manajemen user.
- Daftar pendaftar per unit/gelombang/jalur dengan filter status
  (DataTable).
- Panitia **memutuskan pengajuan pembebasan & diskon biaya formulir**
  pada tahap pembayaran pendaftaran (Bab 3.1), sebelum verifikasi
  berkas.
- Verifikasi per berkas: Setujui / Tolak + catatan. Berkas ditolak
  mengubah status pendaftaran menjadi **Perlu Perbaikan** dan wali
  dapat mengunggah ulang berkas tersebut.
- **Seleksi/penetapan kelulusan DITUNDA** — mekanismenya (berbasis
  **poin** maupun **manual**) diputuskan menyusul dalam rancangan
  tersendiri. Selama belum diputuskan, status akhir yang dikelola
  modul ini adalah Terverifikasi.

## 5. Modul Pengumuman & Daftar Ulang

- Halaman cek kelulusan publik: nomor pendaftaran + tanggal lahir.
- Wali yang anaknya berstatus **Lulus** melakukan daftar ulang dari
  akunnya: sistem menyusun tagihan biaya (lihat Bab 6), wali memilih
  opsi komponen pilihan dan mengisi komponen bebas, lalu membayar
  melalui kanal pembayaran yang aktif (Bab 6.4).
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
- Sebelum dibayar, tagihan dapat diberi **diskon** oleh panitia dengan
  mekanisme yang sama seperti biaya formulir (Bab 3.1): tipe persen
  atau nominal (terbuka untuk tipe lain), beserta nilai dan alasannya;
  total akhir tidak boleh kurang dari nol.
- **Pembayaran bersifat multi-kanal**: setiap kanal adalah *driver*
  di belakang kontrak yang sama (membuat pembayaran, menerima
  konfirmasi, melaporkan status), sehingga kanal baru dapat ditambah
  tanpa mengubah alur pendaftaran maupun daftar ulang. Kanal yang
  direncanakan:
  - **Manual** — wali membayar sesuai instruksi yang ditampilkan
    (transfer ke rekening yayasan atau tunai di sekolah), kemudian
    panitia mengonfirmasi penerimaan di sistem.
  - **Midtrans** — Snap; status diperbarui otomatis lewat notifikasi
    webhook Midtrans (tanda tangan diverifikasi).
  - **BSI (custom)** — integrasi khusus Bank Syariah Indonesia;
    implementasinya menunggu akses/spesifikasi API dari pihak bank,
    dipasang sebagai driver tersendiri.
  - Kanal tambahan lain menyusul sebagai driver baru.
- Admin mengaktifkan/menonaktifkan kanal dari Pengaturan; wali
  memilih di antara kanal yang aktif saat membayar. Kunci rahasia
  tiap kanal (mis. server key & client key Midtrans,
  sandbox/production) disimpan di `.env`, tidak di kode. Status
  pembayaran berlaku seragam untuk semua kanal:
  `Belum Bayar → Menunggu Pembayaran → Lunas / Kedaluwarsa / Gagal`.

## 7. Dashboard & Laporan

- Ringkasan per unit: jumlah pendaftar per gelombang, jalur, dan status.
- Rekap daftar ulang & pembayaran per unit.
- Export CSV daftar pendaftar dan rekap tagihan; cetak formulir dan
  bukti daftar ulang.

## 8. Keputusan (sudah diputuskan pemilik proyek)

1. **Format nomor pendaftaran** — kode unit 2 digit + tahun awal
   2 digit + nomor urut 3 digit (Bab 3).
2. **Akun pendaftaran** — memakai **akun Wali**; satu akun dapat
   mendaftarkan lebih dari satu anak; login dengan **nomor HP** (Bab 3).
3. **INFAQ** — bermode `bebas` (Bab 6).
4. **Pembayaran** — **multi-kanal** berbentuk driver: **Manual**,
   **Midtrans**, dan **BSI (custom)**; terbuka untuk kanal tambahan
   (Bab 6.4).
5. **Panitia** — dibatasi **sesuai unit** tugasnya (Bab 4).
6. **Gelombang** — mengikuti usulan rancangan: otomatis berdasarkan
   tanggal, dengan saklar manual sebagai penimpa.
7. **Seleksi** — **DITUNDA**: mekanismenya (poin maupun manual) akan
   dirancang tersendiri menyusul (Bab 4).
8. **Biaya formulir** — diatur **per unit**; calon siswa khusus dapat
   **dibebaskan** oleh panitia per pendaftaran (Bab 3.1).
9. **Diskon** — potongan biaya diatur panitia dengan tipe **persen**
   atau **nominal** (terbuka untuk tipe lain), berlaku untuk biaya
   formulir dan tagihan daftar ulang (Bab 3.1 dan 6.4).
10. **Identitas orang tua** — hubungan pendaftar adalah **Ayah, Ibu,
    atau Wali** per pendaftaran; identitas ayah dan ibu selalu diisi,
    identitas wali hanya bila anak memilikinya (Bab 3).
11. **Alur pendaftaran** — berurutan dan tidak dapat dilompati:
    Register → Bayar Formulir → Isi Formulir → Input Berkas (Bab 3).

## 9. Peta Fase Implementasi

| Fase | Isi |
| --- | --- |
| 1A | Tahun Ajaran, Unit Sekolah, Jalur (per unit), Jurusan (per unit) |
| 1B | Gelombang, Jenis Berkas + Syarat Berkas per Unit |
| 1C | Bidang Formulir per Unit |
| 1D | Komponen Biaya + Pemetaan Biaya per Unit |
| 1E | Penugasan Panitia per Unit (pivot user–unit + pengaturan di manajemen user) |
| 2 | Akun Wali (registrasi & login nomor HP), alur berurutan Register → Bayar Formulir → Isi Formulir → Input Berkas, pendaftaran multi-anak, pembayaran formulir multi-kanal (Manual & Midtrans; driver BSI menyusul saat akses bank tersedia), keputusan pembebasan & diskon, formulir dinamis, identitas ayah/ibu/wali, unggah berkas, nomor pendaftaran |
| 3 | Verifikasi berkas oleh panitia sesuai unit tugasnya |
| 4 | Pengumuman, daftar ulang & tagihan, pembayaran Midtrans |
| 5 | Dashboard SPMB & laporan/export |
| Menyusul | Seleksi (poin/manual) — dirancang tersendiri |

## 10. Di Luar Cakupan v1

- Notifikasi WhatsApp/email otomatis (pengingat status & pembayaran).
- Login wali dengan OTP (v1: nomor HP + kata sandi).
- Cicilan tagihan (v1: satu tagihan dibayar lunas via Midtrans).
- Multi-yayasan (satu instalasi untuk banyak yayasan).
- Aplikasi mobile.
