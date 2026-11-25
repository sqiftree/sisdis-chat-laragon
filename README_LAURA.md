# Modul Auth & Privasi — Bagian Laura (`sisdis-chat-laragon`)

Paket ini melengkapi fondasi Fase 1 repositori `sqiftree/sisdis-chat-laragon` untuk kebutuhan **Autentikasi (Login/Register/Logout)** dan **Isolasi Privasi Data (Anti-IDOR)** sesuai ketentuan tugas Sistem Terdistribusi.

## Daftar File

- `app/src/db.php` — Menambahkan fungsi `getPdo()` yang reusable dengan koneksi SSL wajib untuk host cloud dan timezone `+00:00` (tetap kompatibel dengan `app/api/status.php`).
- `app/src/http.php` — Helper response JSON standar (`ok`, `data`, `error`), validasi metode HTTP, dan pembaca body JSON/Form.
- `app/src/auth.php` — Session cookie aman (`HttpOnly`, `SameSite=Lax`, `session_regenerate_id`), `requireAuth()`, `requireAdmin()`, serta helper isolasi privasi (`assertSelfAccess`, `assertMessageAccess`, `assertConversationParticipant`, `privateUserView`, `publicUserView`).
- `app/api/register.php` & `app/public/api/register.php` — Registrasi user baru (`POST`).
- `app/api/login.php` & `app/public/api/login.php` — Login via `username` atau `email` + `password` (`POST`), mencatat `last_login_at` memakai `UTC_TIMESTAMP(3)` Cloud DB.
- `app/api/logout.php` & `app/public/api/logout.php` — Logout dan hapus cookie session (`POST`).
- `app/api/me.php` & `app/public/api/me.php` — Baca (`GET`) dan ubah (`PATCH`/`POST`) profil pribadi milik user yang sedang login.
- `app/api/profile.php` & `app/public/api/profile.php` — Endpoint demonstrasi anti-IDOR (`GET ?user_id=<id>`).
- `database/seed.sql` — Akun kelompok (`laura`, `giska`, `wawa`, `yashif`, `wantech`) dan aktivasi akun demo (`ayu`, `bima`, `citra`) dengan password default `sisdis123`.

## Cara Pasang ke Repo

1. Buat branch baru di repositori lokal:
   ```bash
   git checkout -b feat/auth-laura
   ```
2. Ekstrak seluruh isi ZIP ini ke root repositori `sisdis-chat-laragon` (timpa `app/src/db.php` dan `database/seed.sql`).
3. Jalankan ulang `database/seed.sql` di database MySQL (HeidiSQL / Cloud DB).
4. Commit dan push ke branch `feat/auth-laura`, lalu buka Pull Request ke `main`.

## Kontrak Integrasi untuk Tim

- **Untuk Giska (API Chat):** Panggil `require_once __DIR__ . '/../src/auth.php';` lalu `$user = requireAuth();` di awal setiap endpoint chat. Gunakan `assertConversationParticipant()` / `assertMessageAccess()` saat mengambil pesan, dan `publicUserView()` saat menampilkan daftar kontak.
- **Untuk Wawa (Frontend UI):** Semua endpoint berada di `/app/public/api/` (`login.php`, `register.php`, `logout.php`, `me.php`, `profile.php`) dan mengembalikan format `{ "ok": true, "data": {...} }` atau `{ "ok": false, "error": "..." }`. Gunakan `credentials: 'same-origin'` pada `fetch()`.

## Skenario Live Demo (Bagian Laura)

1. **Login berhasil:** Kirim `POST /app/public/api/login.php` dengan `{"identifier": "laura", "password": "sisdis123"}` (atau pakai `laura@sisdis.local`) → status `200 OK`, mengembalikan profil Laura + `server_time_utc`.
2. **Akses data sendiri:** Kirim `GET /app/public/api/profile.php?user_id=<ID_LAURA>` → status `200 OK`, data pribadi tampil.
3. **Uji privasi / anti-IDOR:** Masih memakai session Laura, kirim `GET /app/public/api/profile.php?user_id=<ID_GISKA>` → server otomatis menolak dengan `403 Forbidden` dan pesan *"Akses ditolak: Anda tidak diperbolehkan melihat, membaca, atau mengakses data pribadi milik user lain."*
4. **Akses tanpa login:** Setelah `POST /app/public/api/logout.php`, akses `GET /app/public/api/me.php` → ditolak `401 Unauthorized`.
