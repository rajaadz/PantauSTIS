<?php

declare(strict_types=1);

/**
 * GET api/session_status.php
 * Dipakai oleh header di setiap halaman statis (dashboard.html,
 * pesaing.html, detail.html, bandingkan.html, dan index.html) untuk
 * membedakan tampilan admin vs pengguna biasa: kalau sedang login,
 * header menampilkan "Kelola Data" + nama admin + tombol Logout;
 * kalau belum, header menampilkan "Login Admin" saja. Halaman edit
 * datanya sendiri (kelola_data.php) tetap diproteksi di sisi server
 * lewat require_login(), jadi endpoint ini murni untuk tampilan --
 * bukan satu-satunya lapisan keamanan.
 */

require_once __DIR__ . '/bootstrap.php';

kirim_json([
    'loggedIn'    => is_logged_in(),
    'namaLengkap' => is_logged_in() ? $_SESSION['admin_nama'] : null,
]);
