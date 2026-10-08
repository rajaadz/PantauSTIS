# PantauSTIS — Tracker SPMB STIS

Aplikasi web: HTML5 + Tailwind CSS (CDN) + Vanilla JavaScript di frontend,
PHP Native (PDO, tanpa framework) + MySQL/MariaDB di backend. Login admin
pakai session PHP native, CRUD data riwayat lewat `kelola_data.php`.
Halaman publik (dashboard, tabel pesaing, detail, bandingkan) jalan dengan
data mock JSON di `assets/data/` secara default (tanpa perlu setup
database) — tinggal ubah satu baris konfigurasi untuk memakai backend PHP
asli, lihat "Menyambungkan ke backend PHP asli" di bawah. Login &
Kelola Data SELALU butuh backend PHP + database asli (sesi tidak bisa
di-mock).

## Struktur folder

```
pantaustis-frontend/
├─ index.html                 Halaman gerbang / landing (hero, ajakan buka Dashboard)
├─ dashboard.html              Dashboard Utama (daftar 3 prodi)
├─ pesaing.html                 Tabel Pesaing (daftar provinsi per prodi+tahun)
├─ detail.html                   Detail Riwayat Nilai (kartu per tahun, tab SKD/Matematika, grafik tren)
├─ bandingkan.html                Bandingkan Data (pilih beberapa kombinasi prodi+provinsi+tahun berdampingan)
├─ login.php                       Login admin (session PHP, validasi ke tabel admin)
├─ logout.php                      Hancurkan sesi admin, redirect ke login.php
├─ kelola_data.php                 Panel admin (diproteksi) -- Create/Update/Delete riwayat SPMB
├─ config/
│  ├─ koneksi.php              Koneksi PDO ke MySQL/MariaDB (satu-satunya tempat isi kredensial database)
│  └─ session.php              Sesi PHP + helper login (require_login, csrf_token/verify, flash message)
├─ api/
│  ├─ bootstrap.php            Setup bersama semua endpoint (koneksi + sesi, header JSON, cek metode GET, kirim_json/kirim_error)
│  ├─ prodi.php                GET daftar semua program studi
│  ├─ provinsi.php             GET daftar semua provinsi (urut kode_provinsi)
│  ├─ riwayat.php              GET seluruh riwayat metrik + nilai (gabungan dari database)
│  ├─ cari_prodi.php           GET live-search Prodi (?q=...)
│  ├─ cari_provinsi.php        GET live-search Provinsi (?q=...)
│  └─ session_status.php       GET status login admin (dipakai header semua halaman publik)
├─ assets/
│  ├─ css/style.css            Pelengkap di luar utility Tailwind (scrollbar, animasi, flash-highlight)
│  ├─ js/live-search.js        Komponen live-search/suggestion (JSHint-clean, lihat di bawah)
│  ├─ js/app.js                Logika tiap halaman publik + lapisan data (mock/API) + status login header + Chart.js
│  ├─ js/kelola.js             Konfirmasi sebelum hapus di kelola_data.php (JSHint-clean)
│  └─ data/
│     ├─ prodi.json            Mock 3 program studi
│     ├─ provinsi.json         Mock 34 provinsi (urut kode)
│     └─ riwayat.json          Mock riwayat 2023-2025 (di-generate langsung dari api/riwayat.php, selalu selaras dengan tracker_spmb_stis.sql)
└─ README.md                   Berkas ini
```

Skema database lengkap (termasuk data dummy 2023–2025) ada di
`tracker_spmb_stis.sql` satu folder di atas `pantaustis-frontend/`.

## Cara menjalankan (dengan backend PHP asli)

1. Import `tracker_spmb_stis.sql` ke MySQL/MariaDB Anda (lewat phpMyAdmin
   atau `mysql -u root -p < tracker_spmb_stis.sql`).
2. Buka `config/koneksi.php`, sesuaikan `DB_HOST`, `DB_NAME`, `DB_USER`,
   `DB_PASS` dengan kredensial database Anda (default sudah cocok untuk
   XAMPP standar: host `localhost`, user `root`, password kosong).
3. Set `APP_DEBUG` di `config/koneksi.php` jadi `false` kalau sudah mau
   dipakai produksi/di-hosting (supaya pesan error teknis tidak bocor ke
   pengguna, cuma dicatat lewat `error_log`).
4. Taruh folder `pantaustis-frontend/` di dalam `htdocs` XAMPP (atau
   document root server PHP Anda), lalu buka
   `http://localhost/PantauSTIS/index.html`.
5. Di `assets/js/app.js`, ubah `CONFIG.useMockData` dari `true` jadi
   `false` (lihat bagian selanjutnya) supaya dashboard/pesaing/detail/
   bandingkan memanggil `api/*.php` alih-alih file JSON mock.
6. Login admin di `login.php` dengan kredensial seed bawaan:
   **username `admin`, password `Admin#2026`** (lihat komentar di
   `tracker_spmb_stis.sql` bagian 4.1). Segera ganti lewat query `UPDATE
   admin SET password_hash = ...` (pakai `password_hash()` di PHP) kalau
   dipakai selain untuk demo/tugas.

Login & `kelola_data.php` **selalu** memanggil database asli lewat PDO,
berapa pun nilai `CONFIG.useMockData` -- sesi PHP tidak mungkin di-mock di
sisi klien, jadi kedua halaman ini butuh langkah 1–3 di atas apa pun
mode datanya.

## Cara menjalankan (tanpa PHP, mode demo/mock)

Kalau cuma mau lihat tampilan halaman publik tanpa setup database,
`CONFIG.useMockData` sudah `true` secara default. Karena `fetch()` ke file
JSON tetap butuh disajikan lewat HTTP (bukan `file://`), jalankan lewat
server lokal apa saja, contoh:

```
cd pantaustis-frontend
python -m http.server 8000
```

Di mode ini, `login.php`/`kelola_data.php` tidak akan berfungsi (butuh PHP
+ database sungguhan) -- header di halaman publik akan tetap menampilkan
tombol "Login Admin" bawaan karena permintaan status login ke
`api/session_status.php` gagal secara diam-diam (lihat `initHeaderAuthState`
di `app.js`).

## Menyambungkan ke backend PHP asli

Di `assets/js/app.js`, ubah satu baris ini:

```js
const CONFIG = {
  useMockData: false,  // <-- true = mock JSON, false = endpoint PHP asli di api/
  apiBase: 'api/',
  mockBase: 'assets/data/'
};
```

Tidak ada bagian lain di HTML/CSS/JS yang perlu diubah — endpoint PHP di
`api/` sudah dibuat mengikuti kontrak yang sama persis dengan file JSON
mock di bawah ini.

### Kontrak endpoint (sudah diimplementasikan di `api/`)

Semua respons berbentuk `{ "data": [...] }` (kecuali `session_status.php`),
dengan header `Content-Type: application/json; charset=utf-8`. Semua
endpoint hanya menerima method `GET` (selain itu dibalas `405`).

**`GET api/prodi.php`** — daftar semua program studi (`SELECT` biasa, tidak
ada input dari pengguna sehingga tidak perlu prepared statement)
```json
{ "data": [
  { "kode_prodi": "DIV-ST", "nama_prodi": "Sarjana Terapan Statistika", "jenjang": "D-IV", "deskripsi": "..." }
] }
```

**`GET api/provinsi.php`** — daftar semua provinsi, **diurutkan
berdasarkan `kode_provinsi`** (kode BPS 11–94), bukan alfabet nama
```json
{ "data": [ { "kode_provinsi": "32", "nama_provinsi": "Jawa Barat" } ] }
```

**`GET api/riwayat.php`** — seluruh riwayat metrik + nilai (2023–2025).
Query-nya menggabungkan `riwayat_metrik_kelulusan` dengan `program_studi`
dan `provinsi` lewat `JOIN` biasa, tapi ke `riwayat_nilai` lewat **`LEFT
JOIN`** (bukan lewat view `v_riwayat_nilai_lengkap` di
`tracker_spmb_stis.sql`, yang memakai `INNER JOIN`). Alasannya: kalau
admin sudah input jumlah pendaftar/kuota tapi belum sempat input nilai
SKD/Matematika untuk tahun itu (bisa terjadi lewat `kelola_data.php`, yang
mengizinkan menyimpan metrik tanpa langsung mengisi nilai), baris tersebut
tetap perlu tampil (dengan `nilai` yang tidak lengkap) — bukan hilang diam-
diam. `detail.html` menampilkan teks "Data nilai ... belum diinput untuk
tahun ini" untuk kasus ini, alih-alih error.
```json
{ "data": [
  {
    "tahun": 2025, "kode_prodi": "DIV-ST", "kode_provinsi": "32",
    "jumlah_pendaftar": 1389, "kuota": 30, "jumlah_lulus": 30,
    "nilai": {
      "SKD": { "tertinggi": 451.5, "terendah": 401.25, "rata_rata": 427.0 },
      "Matematika": { "tertinggi": 93.75, "terendah": 71.5, "rata_rata": 83.4 }
    }
  }
] }
```

**`GET api/cari_prodi.php?q=<kata kunci>`** — live-search Dashboard,
mencari lewat `nama_prodi` maupun `kode_prodi`.

**`GET api/cari_provinsi.php?q=<kata kunci>`** — live-search di Tabel
Pesaing & Bandingkan, bentuk sama seperti di atas tapi untuk
`nama_provinsi`.

Kedua endpoint pencarian di atas memakai PDO prepared statement (parameter
`:q_nama`/`:q_kode` atau `:q`, bukan concat string) sehingga aman dari SQL
Injection — sudah diuji manual dengan payload seperti `' OR '1'='1` dan
`'; DROP TABLE admin; --` dan tabel database tetap utuh.

**`GET api/session_status.php`** — status login admin untuk sesi saat ini,
dipanggil `app.js` di header SETIAP halaman publik
```json
{ "loggedIn": true, "namaLengkap": "Administrator Tracker SPMB STIS" }
```

## Login admin & Kelola Data (CRUD)

- **`login.php`** — form username/password, divalidasi lewat PDO prepared
  statement ke tabel `admin` + `password_verify()` terhadap
  `password_hash` yang tersimpan (password asli TIDAK PERNAH disimpan atau
  dibandingkan langsung). Berhasil login -> `session_regenerate_id(true)`
  (cegah session fixation) lalu redirect ke `kelola_data.php`.
- **`logout.php`** — hancurkan sesi (`session_destroy()` + hapus cookie
  sesi), redirect ke `login.php`.
- **`kelola_data.php`** — diproteksi lewat `require_login()` (baris
  pertama, sebelum HTML apa pun dikirim); kalau belum login langsung
  redirect ke `login.php`. Berisi:
  - **Create** — form "Tambah Data Riwayat Tahun Baru": prodi, provinsi,
    tahun, jumlah pendaftar/kuota/lulus, dan nilai SKD + Matematika
    (tertinggi/terendah/rata-rata) sekaligus, disimpan lewat transaksi PDO
    (`beginTransaction`/`commit`/`rollBack`) ke `riwayat_metrik_kelulusan`
    + 2 baris `riwayat_nilai`.
  - **Update** — klik "Edit" pada baris tabel -> form yang sama terisi
    otomatis (termasuk kuota/pendaftar/lulus dan nilai), simpan lagi untuk
    memperbarui (dipakai `INSERT ... ON DUPLICATE KEY UPDATE` untuk
    `riwayat_nilai`, supaya baris nilai yang tadinya belum ada ikut
    terisi, bukan cuma yang sudah ada yang ter-update).
  - **Delete** — tombol "Hapus" per baris (dengan konfirmasi JS di
    `assets/js/kelola.js`), `DELETE` ke `riwayat_metrik_kelulusan` saja —
    baris `riwayat_nilai` terkait ikut terhapus otomatis lewat
    `ON DELETE CASCADE` di skema database.
  - **Validasi** dilakukan di PHP SEBELUM query (jumlah lulus <= pendaftar,
    rentang nilai SKD 0–550 / Matematika 0–100, urutan tertinggi >= rata-
    rata >= terendah) supaya pesan errornya jelas dalam Bahasa Indonesia,
    dengan `PDOException` (mis. kombinasi tahun+prodi+provinsi duplikat)
    tetap ditangkap sebagai lapisan kedua dan diberi pesan ramah, bukan
    error mentah.
  - Proteksi **CSRF**: setiap `<form method="post">` membawa
    `csrf_token` tersembunyi dari `config/session.php`, diverifikasi
    dengan `hash_equals()` sebelum aksi apa pun dijalankan.
  - Pola **Post/Redirect/Get**: setiap submit form diakhiri `header('Location: ...')`
    + `exit`, dengan pesan hasil (sukses/gagal) disimpan sebagai flash
    message satu-kali di sesi -- supaya me-refresh halaman tidak
    mengirim ulang form.
  - Tabel bisa difilter per tahun/prodi (`?f_tahun=&f_prodi=`), dengan
    baris warna selang-seling dan badge warna (hijau/kuning/merah) untuk
    `% Lolos`, sama seperti di Tabel Pesaing.

### Tampilan admin vs pengguna umum

Halaman publik (`index.html`, `dashboard.html`, `pesaing.html`,
`detail.html`, `bandingkan.html`) semuanya statis (bisa dibuka siapa saja)
dan tidak punya kontrol edit apa pun -- CRUD hanya ada di `kelola_data.php`
yang diproteksi sesi. Untuk membedakan tampilannya, `app.js` memanggil
`api/session_status.php` di setiap halaman (`initHeaderAuthState`) dan
mengganti bagian kanan header:
- **Belum login**: tombol "Login Admin" (default, sudah ada di HTML).
- **Sudah login**: nama admin + tombol "Kelola Data" + "Logout".

Ini murni soal tampilan (memberi tahu pengguna ke mana harus klik) —
keamanan sesungguhnya ada di `require_login()` pada `kelola_data.php`
sendiri, bukan di sembunyi/tampilnya tombol ini.

## Tentang koneksi & sesi (`config/koneksi.php`, `config/session.php`)

- Pakai PDO dengan `PDO::ATTR_EMULATE_PREPARES => false`, supaya prepared
  statement benar-benar dieksekusi di sisi server MySQL (proteksi SQL
  Injection yang sesungguhnya, bukan cuma penggabungan string yang
  "disamarkan" oleh PDO).
- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` supaya kegagalan koneksi
  tertangkap rapi (dibalas JSON `{"error": "..."}` dengan HTTP 500),
  bukan menampilkan stack trace PHP mentah ke pengguna.
- Konstanta `APP_DEBUG` (default `true` untuk memudahkan development)
  mengatur apakah detail teknis error (koneksi maupun query di
  `kelola_data.php`) ditampilkan langsung, atau hanya dicatat lewat
  `error_log` dan pengguna cuma melihat pesan umum. **Wajib diubah jadi
  `false` sebelum di-hosting.**
- `config/session.php` membungkus `session_start()` (dengan
  `cookie_httponly` + `cookie_samesite=Lax`) dan helper
  `require_login()`/`is_logged_in()`/`csrf_token()`/`csrf_verify()`/
  `flash_set()`/`flash_get()`, dipakai bersama oleh `login.php`,
  `logout.php`, `kelola_data.php`, dan `api/bootstrap.php`.
- Semua endpoint di `api/` memakai `bootstrap.php` yang meng-require
  `config/koneksi.php` + `config/session.php`, jadi kredensial database
  dan logika sesi masing-masing cukup diisi/diubah di satu tempat.

## Tentang `live-search.js` & `kelola.js`

File-file JS ini yang paling perlu tetap bersih (strict mode, sudah
diverifikasi lolos [JSHint](https://jshint.com) tanpa error/warning).
Beberapa catatan kalau nanti diedit:

- Ditulis dalam satu IIFE (`(function () { 'use strict'; ... }());`),
  supaya `'use strict'` tidak memengaruhi script lain kalau nanti
  digabung/minify bersama file JS lain. `live-search.js` diekspos lewat
  `window.LiveSearch`.
- Directive `/* jshint ... */` di baris pertama mengatur opsi pengecekan
  (esversion 11, browser, devel, undef, unused, curly, eqeqeq). Kalau
  menambah kode baru, jalankan ulang `jshint <nama-file>.js` sebelum
  commit. `assets/js/app.js` memakai directive yang sama dan juga sudah
  diverifikasi bersih (termasuk setelah menambah header auth-state,
  badge warna, dan halaman Bandingkan).
- Setiap pemanggilan `LiveSearch.create()` independen (dipakai untuk
  Prodi di `dashboard.html`, Provinsi di `pesaing.html` dan
  `bandingkan.html`), dan otomatis membatalkan (`AbortController`) request
  sebelumnya kalau pengguna mengetik lebih cepat dari jawaban server.
- Semua teks dari server di-escape (`escapeHtml`) sebelum dirender lewat
  `innerHTML`, supaya aman dari XSS lewat data pencarian/nama admin.
- `kelola.js` cuma menangani konfirmasi sebelum submit form hapus
  (`window.confirm`) -- logika CRUD sesungguhnya ada di PHP.

## Tentang grafik (Chart.js)

Dipakai [Chart.js](https://www.chartjs.org/) lewat CDN
(`cdnjs.cloudflare.com`), dimuat sebagai `<script>` tambahan sebelum
`app.js` di `detail.html` dan `bandingkan.html`. Kalau halaman dibuka
tanpa koneksi internet (atau CDN diblokir), grafik otomatis disembunyikan
(dicek lewat `typeof Chart === 'undefined'`) — bagian lain halaman tetap
berfungsi normal.

- **`detail.html`**: grafik garis tunggal (biru, tanpa legenda karena
  judul kartu sudah menjelaskan datanya) menampilkan **Rata-rata Nilai
  SKD** per tahun (2023–2025) untuk kombinasi program studi + provinsi
  yang sedang dibuka. Sumbu Y diberi rentang 0–550 (skala nilai SKD).
- **`bandingkan.html`**: grafik batang berkelompok, 2 seri warna (biru =
  Rata-rata SKD, indigo muda = Rata-rata Matematika) dengan 2 sumbu Y
  terpisah (skala SKD 0–550 di kiri, skala Matematika 0–100 di kanan)
  karena kedua nilai memang tidak sebanding skalanya.

## Palet warna

Slate (netral) + Blue/Indigo (aksen/aksi) dari Tailwind default sebagai
warna utama. Untuk keterbacaan data, tiga warna status dipakai secara
konsisten di Tabel Pesaing, Bandingkan, dan Kelola Data — **emerald**
(persentase lolos longgar, &ge;3%), **amber** (sedang, 1.5–3%), **red**
(ketat, &lt;1.5%) — supaya warnanya bermakna (menunjukkan tingkat
keketatan), bukan sekadar dekorasi/pelangi.

## Yang belum termasuk (di luar permintaan saat ini)

- Multi-admin / manajemen akun admin dari UI (tambah/nonaktifkan admin
  lain) — tabel `admin` sudah mendukung banyak baris (`is_aktif`), tapi
  belum ada halaman untuk mengelolanya; saat ini hanya 1 admin seed.
- Build step Tailwind (masih pakai CDN `cdn.tailwindcss.com`, butuh
  koneksi internet saat halaman dibuka; kalau nanti mau dipakai offline
  atau di-deploy produksi, ganti dengan Tailwind CLI/PostCSS build lokal).
- Foto asli kampus STIS di halaman gerbang (`index.html`) — saat ini
  memakai ilustrasi SVG buatan sendiri (gedung + grafik statistik) supaya
  tidak bergantung pada file/lisensi foto eksternal; lihat komentar di
  `index.html` untuk cara menggantinya dengan `<img>` foto asli.
