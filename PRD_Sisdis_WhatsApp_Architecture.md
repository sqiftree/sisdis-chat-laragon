# PRD & Implementation Plan — Mini Chat Client-Server (Adaptasi Arsitektur WhatsApp)

> Versi 2 — environment server **dikunci: Laragon (Windows)**. Docker/WSL hanya alat bantu developer, BUKAN bagian dari sistem yang dikumpulkan.
> Mata kuliah: Sistem Terdistribusi. Presentasi/deadline: 30 November 2026.
> Bahasa UI: Indonesia.

---

## 0. Cara Codex Menggunakan Dokumen Ini

1. Baca seluruh dokumen (terutama Bagian 5 dan 6 tentang environment) sebelum menulis kode.
2. Kerjakan **per fase** (Bagian 10). Selesaikan dan verifikasi satu fase sebelum lanjut.
3. Setelah tiap fase: jalankan checklist verifikasi, lalu ringkas apa yang dibuat + cara menjalankannya.
4. Jangan menambah fitur di luar scope (Bagian 3). Jika ambigu, pilih opsi **default** di dokumen ini.
5. Jangan pernah meng-commit kredensial. Gunakan `config.local.php` yang di-`.gitignore`.
6. **Jangan membuat Dockerfile / docker-compose untuk sistem ini.** Target runtime adalah Laragon di Windows.

---

## 1. Ringkasan Proyek

Sistem chat sederhana berbasis **Client-Server** yang mengadaptasi arsitektur WhatsApp:

- Setiap **user** punya **application server sendiri** di perangkatnya (**Laragon**: Apache + PHP).
- Semua application server terhubung ke **satu Cloud Database** bersama (user, pesan, data sistem, acuan waktu).
- Komunikasi client ↔ server memakai **HTTP**.
- Tambahan (modifikasi dari WhatsApp): **Backup Storage** di desktop/PC salah satu anggota (juga Laragon), tanpa Google Drive.

```
 User 1 (browser)      User 2 (browser)      User 3 (browser)
       │ HTTP                │ HTTP                │ HTTP
 ┌─────▼──────┐       ┌──────▼─────┐       ┌───────▼────┐
 │ App Server │       │ App Server │       │ App Server │   ← Laragon di tiap laptop
 │  (User 1)  │       │  (User 2)  │       │  (User 3)  │
 └─────┬──────┘       └──────┬─────┘       └───────┬────┘
       └──────────────┬──────┴─────────────────────┘
                      │ MySQL (TLS)
              ┌───────▼────────┐        HTTP (on-demand)       ┌──────────────────┐
              │ CLOUD DATABASE │ ────────────────────────────► │  BACKUP STORAGE  │
              │ (DB utama +    │ ◄──────── restore ─────────── │  PC + Laragon    │
              │  acuan waktu)  │                               │  (bukan GDrive)  │
              └────────────────┘                               └──────────────────┘
```

## 2. Tujuan & Kriteria Sukses

| # | Tujuan | Terukur jika |
|---|--------|--------------|
| G1 | Pertukaran data antar user | User A kirim pesan → User B (app server & perangkat berbeda) menerima ≤ 5 detik |
| G2 | Satu cloud DB bersama | Semua app server membaca/menulis ke DB yang sama |
| G3 | Waktu mengikuti server cloud | Timestamp pesan & UI dari waktu DB cloud, bukan jam lokal perangkat |
| G4 | Login per user | Login username/email + password; password di-hash |
| G5 | Isolasi data pribadi | User tidak bisa membaca data/pesan user lain (termasuk via manipulasi URL/ID) |
| G6 | Backup on-demand | User menekan "Backup" → salinan DB tersimpan di PC backup + log |
| G7 | Restore | Data dipulihkan dari file backup ke cloud DB |

## 3. Scope

### In scope (WAJIB)
- Register + login/logout (username **atau** email + password).
- Chat teks 1-on-1; daftar user/kontak; riwayat percakapan.
- Cloud DB tunggal; schema + seed.
- Endpoint waktu server cloud dan pemakaiannya di seluruh sistem.
- Backup on-demand ke PC backup + riwayat + restore.
- Proteksi akses data antar user.
- Halaman "Status Sistem" untuk demo (koneksi DB, selisih waktu, backup terakhir).

### Out of scope
- E2E encryption, grup, voice/video, push notification, XMPP/ejabberd sungguhan, app mobile native.
- Google Drive / layanan cloud storage lain untuk backup.
- Docker sebagai runtime sistem.

### Nice-to-have (hanya jika semua WAJIB selesai)
- Status ✓/✓✓, upload gambar kecil, jadwal backup otomatis (Task Scheduler), indikator online.

## 4. Peran Tim (5 atau 6 anggota)

| Peran | Jumlah | Tanggung jawab teknis |
|-------|--------|------------------------|
| Client/User + App Server | 3–4 | Laragon di laptop masing-masing, clone repo yang sama, beda hanya `config.local.php` |
| Pengelola Cloud Database | 1 | Provisioning DB cloud, user DB, schema, kredensial, CA cert |
| Pengelola Backup Storage | 1 | PC dengan Laragon, backup service, folder penyimpanan, prosedur restore |

## 5. Stack (Dikunci)

| Lapisan | Pilihan | Catatan |
|---------|---------|---------|
| App server | **Laragon** (Apache + PHP 8.x) di Windows | Document root `C:\laragon\www` |
| Backend | PHP native + **PDO MySQL** | Tanpa framework |
| Frontend | HTML + CSS + vanilla JS (fetch + polling) | Tanpa build step |
| Cloud DB | MySQL 8/MariaDB terkelola | Akses via TLS |
| Backup service | PHP + `mysqldump.exe` bawaan Laragon | Di PC backup |
| Protokol | HTTP/JSON | Wajib per soal |
| DB lokal (dev saja) | MySQL bawaan Laragon | Untuk develop sebelum cloud DB siap; **bukan** DB produksi |

---

## 6. Environment & Setup Laragon (Windows + Docker/WSL)

### 6.1 Lokasi repo — PENTING
- Simpan repo di **sisi Windows**: `C:\laragon\www\sisdis-wa-arch\` (di WSL: `/mnt/c/laragon/www/sisdis-wa-arch/`).
- **Jangan** taruh repo di dalam filesystem WSL (`\\wsl$\...` / `~/`) — Apache Laragon tidak bisa melayani file dari sana secara andal.
- URL akses lokal (default): `http://localhost/sisdis-wa-arch/app/public/`
  Semua link/aset/fetch di kode harus memakai `BASE_URL` dari config, **bukan** path absolut `/api/...`, supaya jalan di subfolder maupun di virtual host.

### 6.2 Instalasi Laragon (tiap anggota)
1. Unduh **Laragon Full** (sudah termasuk Apache, MySQL, PHP, Node, Git).
2. Install di `C:\laragon` (default).
3. Buka Laragon → **Start All**. Pastikan indikator Apache & MySQL hijau.
4. Cek PHP: menu **PHP → Version** (pakai 8.1+).
5. Cek ekstensi: menu **PHP → Extensions**, pastikan aktif: `pdo_mysql`, `mysqli`, `openssl`, `curl`, `mbstring`, `fileinfo`.
6. Clone repo ke `C:\laragon\www\`.
7. Salin `app/config/config.example.php` → `app/config/config.local.php` lalu isi kredensial cloud DB.

### 6.3 Docker/WSL ↔ Laragon (sumber masalah umum)
| Masalah | Gejala | Solusi |
|---------|--------|--------|
| **Bentrok port 3306/80/443** | Apache/MySQL Laragon merah atau tidak mau start | Cek: `netstat -ano \| findstr :3306` (dan `:80`). Hentikan container: `docker ps` lalu `docker stop <nama>`; atau ubah port Laragon di **Menu → Preferences → Services & Ports** (mis. MySQL 3307, Apache 8080) |
| Port WSL ikut "bocor" ke Windows | Port terpakai padahal Docker Desktop tertutup | `wsl --shutdown` lalu start Laragon lagi |
| Codex jalan di WSL tapi PHP di Windows | `php: command not found` di WSL | Pakai biner Windows: `/mnt/c/laragon/bin/php/php-8.x.x-.../php.exe -l file.php` (ganti versi sesuai folder). Buat alias di `~/.bashrc`: `alias php='/mnt/c/laragon/bin/php/<versi>/php.exe'` |
| `curl http://localhost/...` dari WSL gagal | Connection refused | WSL2 mode NAT tidak selalu meneruskan localhost. Solusi: aktifkan mirrored networking di `%UserProfile%\.wslconfig`: `[wsl2]` + `networkingMode=mirrored`, lalu `wsl --shutdown`. Alternatif: pakai IP host Windows (`ip route show default \| awk '{print $3}'`) |
| Line ending CRLF/LF berantakan (repo dipakai Windows + WSL) | Diff besar, warning git | Buat `.gitattributes` berisi `* text=auto eol=lf` dan jalankan `git config core.autocrlf input`. Gunakan **satu** git (pilih Git Windows atau WSL, jangan campur untuk commit) |
| Edit file di `/mnt/c` terasa lambat | Codex/IDE lemot | Normal untuk `/mnt/c`. Opsi: jalankan Codex langsung di Windows (PowerShell) untuk repo ini |

**Docker boleh dipakai** untuk hal lain (mis. MySQL eksperimen di port 3307), tetapi sistem final harus jalan murni di Laragon.

### 6.4 Koneksi ke Cloud DB dari Laragon
- Unduh **CA certificate** dari provider cloud DB (Aiven/TiDB menyediakan), simpan di `app/config/certs/ca.pem`.
- PDO (wajib TLS):
  ```php
  $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::MYSQL_ATTR_SSL_CA => __DIR__ . '/../config/certs/ca.pem',
  ]);
  $pdo->exec("SET time_zone = '+00:00'");
  ```
- Jika provider tidak memakai sertifikat bertingkat valid untuk host Windows, uji dulu dengan `mysql.exe` Laragon:
  `C:\laragon\bin\mysql\<versi>\bin\mysql.exe -h HOST -P PORT -u USER -p --ssl-ca=ca.pem`
- Jika port DB cloud diblokir jaringan kampus → pindah ke hotspot HP.

### 6.5 Setup khusus PC Backup (Laragon)
1. Laragon sama seperti 6.2; tempatkan `backup-service/` di `C:\laragon\www\backup-service\`.
2. Isi `backup-service/config/config.local.php`:
   - `MYSQLDUMP_PATH` = `C:\laragon\bin\mysql\<versi-mysql>\bin\mysqldump.exe` (cek nama folder versi yang sebenarnya)
   - `MYSQL_CLI_PATH` untuk restore (`mysql.exe` di folder yang sama)
   - `BACKUP_DIR` = `C:\laragon\www\backup-service\storage\backups` (**di luar document root jika memungkinkan**; minimal beri `.htaccess` `Deny from all` — file backup tidak boleh bisa diunduh publik)
   - `SHARED_SECRET` = token acak panjang (sama dengan di app server)
3. **Agar app server di laptop lain bisa menjangkau PC backup** (pilih satu):
   - **Tailscale (disarankan):** install di **Windows host** (bukan di dalam WSL) di PC backup dan semua laptop; pakai IP Tailscale (100.x.x.x) di `BACKUP_SERVICE_URL`.
   - **Satu LAN/hotspot:** pakai IP LAN PC backup; buka Windows Firewall inbound untuk port Apache (80) pada profil *Private*: PowerShell Admin → `New-NetFirewallRule -DisplayName "Laragon Apache" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Private`
4. Jalankan backup lewat `proc_open` dengan **array argumen** (bukan string concat) agar aman dari command injection; sertakan `--ssl-ca` ke cloud DB.
5. Uji: buka `http://<ip-pc-backup>/backup-service/public/list.php` dari laptop lain.

### 6.6 Tips Laragon lain
- Jangan edit `php.ini` sembarangan; gunakan menu **PHP → php.ini** lalu **Reload** Apache setelah perubahan.
- Error PHP saat dev: aktifkan `display_errors` hanya di mesin lokal; di demo matikan, tulis ke log (`C:\laragon\tmp` atau folder log Apache).
- Auto virtual host (`sisdis-wa-arch.test`) boleh dipakai, tapi default Laragon menunjuk ke root folder proyek, bukan `app/public`. Jika ingin URL bersih, edit `C:\laragon\etc\apache2\sites-enabled\*.conf` agar `DocumentRoot` ke `...\app\public`. Default aman: pakai `http://localhost/sisdis-wa-arch/app/public/`.
- Setelah ganti port/ekstensi/vhost: **Stop All → Start All**.
- Backup rutin folder `storage/backups` dari PC backup sebelum demo (salin ke flashdisk).

---

## 7. Struktur Repo

```
sisdis-wa-arch/                  # di C:\laragon\www\
├─ AGENTS.md
├─ README.md
├─ .gitattributes                # * text=auto eol=lf
├─ .gitignore                    # config.local.php, storage/backups/, *.sql, .env
├─ app/                          # APPLICATION SERVER (tiap user)
│  ├─ public/                    # index.php, login.php, chat.php, status.php, assets/
│  ├─ api/
│  │  ├─ auth_register.php
│  │  ├─ auth_login.php
│  │  ├─ auth_logout.php
│  │  ├─ users_list.php
│  │  ├─ messages_send.php
│  │  ├─ messages_poll.php
│  │  ├─ messages_history.php
│  │  ├─ time.php
│  │  ├─ status.php
│  │  ├─ backup_request.php      # teruskan permintaan ke PC backup
│  │  └─ backup_list.php
│  ├─ src/                       # db.php, auth.php, helpers.php, middleware.php
│  └─ config/                    # config.example.php, config.local.php (ignored), certs/ca.pem
├─ backup-service/               # di PC BACKUP
│  ├─ public/                    # backup.php, restore.php, list.php
│  ├─ storage/backups/           # *.sql (ignored)
│  └─ config/                    # config.example.php, config.local.php (ignored)
├─ database/                     # schema.sql, seed.sql
└─ docs/                         # arsitektur.md, skenario-demo.md, setup-laragon.md
```

## 8. Desain Data

```sql
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(120) NOT NULL UNIQUE,
  display_name  VARCHAR(80)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at    DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  last_login_at DATETIME(3)  NULL
);

CREATE TABLE messages (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sender_id    INT UNSIGNED NOT NULL,
  receiver_id  INT UNSIGNED NOT NULL,
  body         TEXT         NOT NULL,
  created_at   DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),   -- waktu SERVER CLOUD
  delivered_at DATETIME(3)  NULL,
  read_at      DATETIME(3)  NULL,
  origin_node  VARCHAR(50)  NULL,                                    -- nama app server pengirim (demo)
  FOREIGN KEY (sender_id)   REFERENCES users(id),
  FOREIGN KEY (receiver_id) REFERENCES users(id),
  INDEX idx_conv (sender_id, receiver_id, id),
  INDEX idx_inbox (receiver_id, id)
);

CREATE TABLE backup_logs (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requested_by  INT UNSIGNED NOT NULL,
  type          ENUM('backup','restore') NOT NULL,
  filename      VARCHAR(255) NULL,
  status        ENUM('pending','success','failed') NOT NULL,
  size_bytes    BIGINT UNSIGNED NULL,
  message       VARCHAR(255) NULL,
  created_at    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  FOREIGN KEY (requested_by) REFERENCES users(id)
);
```

`created_at` **harus** diisi DB cloud (default kolom), bukan `date()` PHP atau `Date` JS. Sesi DB di-set UTC; konversi ke WIB hanya saat tampil.

## 9. Spesifikasi Fungsional

### 9.1 Autentikasi
- Register: validasi unik username/email; `password_hash()`.
- Login: `password_verify()`, `session_regenerate_id(true)`, cookie `HttpOnly` + `SameSite=Lax`.
- Semua endpoint selain register/login/time wajib cek sesi lewat satu middleware.
- Token CSRF untuk semua POST.

### 9.2 Pesan
| Endpoint | Method | Perilaku |
|----------|--------|----------|
| `users_list.php` | GET | Daftar user lain (id, display_name saja; tanpa email/hash) |
| `messages_send.php` | POST `{receiver_id, body}` | `sender_id` dari **sesi**. Body 1–2000 karakter; escape saat render |
| `messages_history.php` | GET `?with=<id>&before=<id>` | Hanya pesan di mana user sesi = sender ATAU receiver |
| `messages_poll.php` | GET `?after_id=<id>` | Pesan baru dengan `receiver_id` = user sesi; klien polling 2–3 dtk |

Aturan privasi: setiap query pesan wajib `WHERE (sender_id = :me OR receiver_id = :me)`. Dilarang query pesan hanya berdasar `id`.

### 9.3 Sinkronisasi Waktu
- `time.php` menjalankan `SELECT UTC_TIMESTAMP(3)` ke cloud DB → `{server_time_utc, fetched_in_ms}`.
- Klien hitung offset saat load + tiap ~30 dtk; semua tampilan waktu = jam perangkat + offset.
- Halaman status: jam perangkat, jam cloud, selisih (ms).

### 9.4 Backup & Restore
- Tombol **"Backup Sekarang"** → `backup_request.php` mengirim HTTP POST ke `backup-service/public/backup.php` dengan header `X-Backup-Token: SHARED_SECRET`.
- Backup service: `mysqldump` (path dari config) → `backup_YYYYMMDD_HHMMSS.sql` di `BACKUP_DIR` → catat ke `backup_logs`.
- `list.php`: nama, ukuran, waktu.
- **Restore**: `restore.php` hanya untuk `role = admin` + konfirmasi 2 langkah; whitelist nama file `^backup_\d{8}_\d{6}\.sql$`; import via `mysql.exe`.
- Skenario demo pemulihan: kosongkan tabel `messages` → restore → pesan kembali.
- Tidak boleh memakai Google Drive.

### 9.5 UI Minimal
Login/Register, Chat (kiri daftar user, kanan percakapan), Status Sistem, Backup. Gelembung chat kiri/kanan, jam dari waktu cloud.

## 10. Fase Pengerjaan

> Hari ini 23 Nov; target siap demo ± 28 Nov, 29–30 Nov cadangan latihan.

### Fase 0 — Environment (± 0.25 hari, semua anggota)
- Install Laragon, cek ekstensi PHP, resolve konflik port Docker, clone repo ke `C:\laragon\www\`.
- **Verifikasi:** `http://localhost/sisdis-wa-arch/app/public/` terbuka; `phpinfo` menunjukkan `pdo_mysql` & `openssl`.

### Fase 1 — Fondasi (± 0.5 hari)
- Struktur folder, `.gitignore`, `.gitattributes`, config example, `schema.sql`, `seed.sql`, `src/db.php` (TLS + UTC).
- **Verifikasi:** `status.php` menampilkan "DB connected" + waktu cloud dari ≥ 2 laptop.

### Fase 2 — Auth (± 0.5 hari)
- Register, login, logout, middleware, CSRF.
- **Verifikasi:** endpoint terproteksi → 401 tanpa login; hash tidak bocor.

### Fase 3 — Chat inti (± 1.5 hari)
- users_list, send, history, poll + UI polling.
- **Verifikasi (G1):** dua laptop, dua akun, pesan ≤ 5 dtk.

### Fase 4 — Waktu & Status (± 0.5 hari)
- time.php + offset klien + halaman status.
- **Verifikasi (G3):** ubah jam laptop 5 menit → timestamp pesan tetap benar.

### Fase 5 — Backup & Restore (± 1 hari)
- Backup service di PC backup (Bagian 6.5), tombol, log, daftar, restore.
- **Verifikasi (G6, G7):** skenario hapus → restore sukses, dari laptop lain lewat jaringan.

### Fase 6 — Hardening (± 0.5 hari)
- Uji IDOR, SQL injection, XSS, CSRF, rate limit login, file backup tidak bisa diunduh via URL.

### Fase 7 — Dokumentasi & Demo (± 0.5 hari)
- `docs/setup-laragon.md`, diagram arsitektur, skenario demo, salinan backup data demo.

## 11. Checklist Penerimaan

**Fungsional**
- [ ] Register & login 3+ akun dari 3+ perangkat dengan Laragon masing-masing
- [ ] Pesan dua arah ≤ 5 dtk; riwayat bertahan setelah refresh
- [ ] Waktu pesan konsisten walau jam perangkat diubah
- [ ] Backup menghasilkan `.sql` di PC backup + tercatat di log
- [ ] Restore mengembalikan data yang sengaja dihapus

**Keamanan/Privasi**
- [ ] Ubah `?with=` / `receiver_id` / `id` ke milik orang lain → 403/kosong
- [ ] Semua query prepared statement; input pesan di-escape (`<script>alert(1)</script>`)
- [ ] Password ter-hash; tidak ada kredensial/CA privat/file backup di Git
- [ ] Backup/restore menolak request tanpa token; file `.sql` tidak bisa diakses via URL
- [ ] `sender_id` tidak pernah dari input klien

**Ketahanan**
- [ ] Cloud DB tak terjangkau → UI tampilkan pesan error jelas (tanpa stack trace)
- [ ] Port bentrok Docker tidak mengganggu (sudah didokumentasikan solusinya)

## 12. Aturan untuk Codex (salin ke `AGENTS.md`)

```
- Runtime target: Laragon di Windows (Apache + PHP 8 + MySQL client). JANGAN buat Docker/compose untuk sistem ini.
- Repo berada di C:\laragon\www\sisdis-wa-arch (di WSL: /mnt/c/laragon/www/sisdis-wa-arch). Jangan pindahkan ke filesystem WSL.
- Stack: PHP 8 native + PDO, MySQL, vanilla JS. Tanpa framework.
- Semua URL/aset/fetch memakai BASE_URL dari config; jangan hardcode path absolut "/api/..".
- Semua SQL pakai prepared statement. Dilarang string concatenation ke query.
- user_id selalu dari $_SESSION, bukan dari request.
- Waktu bisnis dari DB cloud (CURRENT_TIMESTAMP / UTC_TIMESTAMP), bukan date()/Date().
- Koneksi cloud DB wajib TLS (MYSQL_ATTR_SSL_CA) dan SET time_zone='+00:00'.
- Path mysqldump/mysql.exe SELALU dari config (Windows path, tidak hardcode); jalankan via proc_open dengan array argumen.
- Response API: JSON {ok: bool, data?: any, error?: string}; HTTP status benar (200/400/401/403/404/500).
- Jangan commit config.local.php / file backup / kredensial. Pastikan .gitattributes eol=lf.
- Cek sintaks PHP dengan php.exe Laragon (di WSL: /mnt/c/laragon/bin/php/<versi>/php.exe -l file.php).
- Backup TIDAK boleh memakai Google Drive.
- Kode sederhana & mudah dijelaskan saat presentasi; komentar singkat di bagian kunci (auth, waktu, backup).
- Setelah tiap fase: tulis cara menjalankan + hasil verifikasi.
```

## 13. Keputusan Terbuka & Opsi (default **tebal**)

| Keputusan | Opsi | Default |
|-----------|------|---------|
| Penyedia cloud DB | **Aiven MySQL (free)**, TiDB Cloud Serverless, VPS sendiri | Aiven/TiDB |
| Akses app server → cloud DB | **Langsung PDO + TLS**, atau API gateway kecil | Langsung PDO |
| Konektivitas ke PC backup | **Tailscale (di Windows host)**, satu LAN/hotspot + firewall rule, ngrok/cloudflared | Tailscale; fallback hotspot |
| URL lokal | **`http://localhost/sisdis-wa-arch/app/public/`**, atau vhost `.test` | localhost subfolder |
| Delivery pesan | **Polling 2–3 dtk**, long-polling, SSE | Polling |
| Pemicu backup | **Manual (tombol)**, + Task Scheduler (bonus) | Manual |
| Format backup | **mysqldump .sql**, atau export JSON/CSV | .sql |

## 14. Risiko & Mitigasi

| Risiko | Mitigasi |
|--------|----------|
| Port Laragon bentrok dengan Docker/WSL | Bagian 6.3; hentikan container atau ganti port Laragon |
| Codex di WSL tidak menemukan PHP | Alias ke `php.exe` Laragon (6.3) |
| `curl localhost` dari WSL gagal | Mirrored networking atau IP host Windows |
| Free tier cloud DB membatasi koneksi/IP whitelist | Pilih provider tanpa whitelist ketat atau whitelist sementara + user DB hak minimal |
| Jaringan kampus blokir port DB | Uji hari ini; siapkan hotspot |
| `mysqldump.exe` tidak ditemukan | Cek folder versi MySQL Laragon; path di config; fallback export via PHP |
| Firewall Windows blokir akses ke PC backup | Aturan inbound Private (6.5) atau Tailscale |
| File backup bisa diunduh publik | Simpan di luar docroot / `.htaccess Deny from all` |
| Kredensial bocor di Git | `.gitignore` + `config.example.php`; rotasi password sebelum demo |
| Demo gagal karena internet | Rekaman layar cadangan + data seed |

## 15. Skenario Demo (±7 menit)

1. Diagram arsitektur: WhatsApp asli vs versi kelompok + modifikasi backup.
2. 3 anggota login di 3 laptop; tunjukkan Laragon masing-masing berjalan (app server sendiri-sendiri).
3. Kirim pesan silang; tunjukkan baris baru di cloud DB.
4. Ubah jam salah satu laptop → timestamp tetap mengikuti server cloud.
5. Coba akses chat user lain via manipulasi URL → ditolak.
6. "Backup Sekarang" → tunjukkan file `.sql` di PC backup + log.
7. Hapus data pesan → restore → data kembali.

## 16. Deliverable Akhir
- Repo Git (kode + README + `docs/setup-laragon.md`).
- Cloud DB aktif + schema + seed.
- Backup service berjalan di PC backup (Laragon).
- Slide presentasi singkat + skenario demo.
