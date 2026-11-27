# Modul Chat & Backup Request — Bagian Giska

## Daftar File

- `app/api/contacts.php` — `GET` daftar kontak (semua user lain) + pesan terakhir dengan masing-masing kontak. `?q=` untuk cari nama.
- `app/api/messages.php` — `GET` polling/riwayat pesan, `POST` kirim pesan.
- `app/api/backup_request.php` — `POST` meneruskan permintaan backup ke PC backup (`backup_service_url`), `GET` riwayat `backup_logs`.
- `app/src/csrf.php` — `requireCsrf()` untuk memvalidasi token CSRF di setiap `POST`.
- `app/public/api/{contacts,messages,backup_request}.php` — wrapper publik, sama seperti endpoint Laura.

Semua endpoint memakai `requireAuth()` dari `auth.php` dan format respons `{ "ok": true, "data": {...} }`.

## Endpoint

| Endpoint | Method | Keterangan |
|---|---|---|
| `contacts.php` | GET | Kontak via `publicUserView()` (tanpa email/hash) + `last_message` |
| `messages.php?with=ID` | GET | 50 pesan terakhir dengan user ID |
| `messages.php?with=ID&after_id=N` | GET | Polling percakapan tiap 2–3 detik, simpan `last_id` dari respons |
| `messages.php?with=ID&before=N` | GET | Riwayat lebih lama (scroll ke atas) |
| `messages.php?after_id=N` | GET | Polling kotak masuk: pesan baru untuk saya dari siapa pun |
| `messages.php` | POST | `{receiver_id, body}` + CSRF. Body 1–2000 karakter |
| `backup_request.php` | POST | CSRF saja. Mencatat log `pending`, memanggil PC backup, lalu update `success`/`failed` |
| `backup_request.php` | GET | Riwayat backup: user biasa melihat miliknya sendiri, admin melihat semua |

## Kontrak Integrasi untuk Tim

- **Wawa (UI):** kirim token dari `login.php`/`me.php` (`data.csrf_token`) pada setiap `POST` lewat header `X-CSRF-Token` atau field `csrf_token`. Waktu dikirim dalam UTC (`..._utc`, akhiran `Z`); ubah ke WIB saat ditampilkan. Escape `body` sebelum dirender (pakai `textContent`).
- **Pengelola Backup (`backup-service/public/backup.php`):** menerima JSON `{log_id, requested_by, origin_node}` dengan header `X-Backup-Token` = `shared_secret`. Balas HTTP 2xx berisi `{"ok":true,"data":{"filename":"backup_YYYYMMDD_HHMMSS.sql","size_bytes":123}}`. Jangan `INSERT` baris `backup_logs` baru karena app server sudah mencatatnya. Kalau perlu, cukup `UPDATE ... WHERE id = log_id`.
- **Tiap laptop:** isi `node_name` (unik), `backup_service_url` (mis. `http://100.x.x.x/backup-service/public/backup.php`) dan `backup_token` di `app/config/config.local.php`.

## Poin Sistem Terdistribusi (untuk presentasi)

1. **Waktu cloud:** `created_at` pesan dan log diisi oleh Cloud DB (`CURRENT_TIMESTAMP(3)`, sesi UTC), tidak pernah oleh jam laptop. `delivered_at` juga memakai `UTC_TIMESTAMP(3)`.
2. **origin_node:** setiap pesan mencatat app server mana yang mengirimnya, sehingga terlihat bahwa beberapa server memakai satu database.
3. **Privasi:** `sender_id` selalu diambil dari sesi. Setiap query memakai `sender_id = saya OR receiver_id = saya`, lalu dicek ulang dengan `assertMessageAccess()`. User lain yang memakai `?with=` hanya mendapat percakapan miliknya sendiri.
4. **Backup:** komunikasi antar-server lewat HTTP dengan token rahasia. Kegagalan (PC backup mati) tetap tercatat sebagai `failed`.

## Skenario Demo

1. Login sebagai `giska` di laptop A dan `laura` di laptop B, lalu kirim pesan. Pesan muncul di B dalam 2–3 detik, dengan `origin_node` laptop A.
2. Login sebagai `wawa`, lalu buka `messages.php?with=<id laura>`. Hasilnya kosong karena percakapan Giska–Laura tidak bocor.
3. Tekan "Backup Sekarang". Status `success` dan nama file `.sql` muncul di tabel riwayat.
4. Matikan Apache di PC backup lalu backup lagi. Status `failed` tercatat beserta alasannya.
