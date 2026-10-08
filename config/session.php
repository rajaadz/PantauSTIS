<?php

declare(strict_types=1);

/**
 * config/session.php
 * ---------------------------------------------------------------------
 * Sesi PHP + helper otentikasi admin, dipakai bersama oleh login.php,
 * logout.php, kelola_data.php, dan api/bootstrap.php (supaya semua
 * endpoint API juga bisa tahu status login lewat api/session_status.php).
 *
 * Sengaja dipisah dari config/koneksi.php: file ini murni soal SESI
 * (tidak butuh koneksi database), supaya endpoint yang tidak perlu
 * database (kalau ada nanti) tetap bisa pakai session tanpa ikut
 * membuka koneksi PDO.
 * ---------------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite'  => 'Lax',
    ]);
}

/**
 * True kalau ada admin yang sedang login di sesi ini.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['admin_id']);
}

/**
 * Wajibkan login untuk mengakses halaman ini -- panggil di baris paling
 * atas halaman yang diproteksi (kelola_data.php). Kalau belum login,
 * langsung redirect ke login.php dan hentikan eksekusi halaman.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Ambil token CSRF untuk sesi ini, buat baru kalau belum ada. Dipakai
 * sebagai <input type="hidden"> di setiap <form method="post"> pada
 * login.php & kelola_data.php supaya form tidak bisa disubmit dari
 * situs lain (proteksi CSRF sederhana, cukup untuk aplikasi sekelas ini).
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifikasi token CSRF yang dikirim lewat form POST terhadap token yang
 * tersimpan di sesi. Pakai hash_equals() (bukan ===) supaya tidak rentan
 * timing attack.
 */
function csrf_verify(?string $token): bool
{
    return is_string($token) && $token !== '' &&
        !empty($_SESSION['csrf_token']) &&
        hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Simpan pesan satu-kali ("flash message") untuk ditampilkan setelah
 * redirect (pola Post/Redirect/Get) -- misalnya "Data berhasil disimpan."
 * setelah submit form Create/Update/Delete di kelola_data.php.
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Ambil & hapus flash message (hanya tampil sekali, tidak muncul lagi
 * setelah halaman di-refresh).
 *
 * @return array{type: string, message: string}|null
 */
function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}
