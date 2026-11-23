# Tutorial Setup — Laragon + PHP di Windows (dengan Docker/WSL sudah terpasang)

Panduan langkah demi langkah untuk proyek **Sisdis — Arsitektur WhatsApp**.
Keputusan final: **PHP native + Laragon** sebagai application server. Docker/WSL hanya alat bantu (Codex, DB lokal opsional), bukan bagian sistem yang dipresentasikan.

Pendamping dokumen: `PRD_Sisdis_WhatsApp_Architecture.md`.

---

## Gambaran Lingkungan

```
Windows (host)
├─ Laragon  ← APPLICATION SERVER (Apache + PHP + MySQL lokal)  ← yang dipresentasikan
│   └─ C:\laragon\www\sisdis-wa-arch\   ← repo ada DI SINI
├─ Docker (WSL2) ← alat bantu: MySQL dev opsional (port 3307)
└─ WSL (Ubuntu)  ← tempat Codex / terminal; akses repo via /mnt/c/laragon/www/...
```

Aturan emas:
1. Repo **di sisi Windows** (`C:\laragon\www\...`), bukan di `~/` WSL.
2. Laragon yang menjalankan web, bukan Docker.
3. Port Laragon dan Docker **tidak boleh bentrok**.

---

## Langkah 1 — Cek Port Dulu (Sebelum Install)

Buka **PowerShell** (Windows), cek port yang akan dipakai Laragon:

```powershell
netstat -ano | findstr ":80 "
netstat -ano | findstr ":443 "
netstat -ano | findstr ":3306"
```

Kalau ada hasil (LISTENING), cari siapa pemiliknya:

```powershell
tasklist /FI "PID eq <PID>"
```

Jika pemiliknya Docker/WSL (`wslrelay.exe`, `com.docker.backend.exe`, `vmmem`):

```bash
# di terminal WSL
docker ps
docker stop <nama-container-yang-pakai-80-443-3306>
```

Catatan: container yang punya `restart: always` akan hidup lagi tiap Docker start. Hentikan dengan `docker update --restart=no <nama>` atau ubah port mapping container-nya (mis. `3307:3306`, `8081:80`).

## Langkah 2 — Install Laragon

1. Unduh **Laragon Full** dari laragon.org, install ke `C:\laragon` (default).
2. Buka Laragon → klik **Start All**.
3. Pastikan **Apache** dan **MySQL** berstatus hijau.
4. Buka `http://localhost` — halaman Laragon muncul.
5. Menu **PHP → Version**: pilih **PHP 8.1 atau lebih baru**.
6. Menu **PHP → Extensions**: pastikan tercentang
   `pdo_mysql`, `mysqli`, `openssl`, `curl`, `mbstring`, `fileinfo`.
7. Setelah mengubah ekstensi: **Stop All → Start All**.

Jika port 80/3306 tetap bentrok dan tidak bisa dihentikan: **Menu → Preferences → Services & Ports**, ganti Apache ke `8080` dan/atau MySQL ke `3307`, lalu Start All. URL berubah menjadi `http://localhost:8080/...`.

## Langkah 3 — Clone Repo ke Folder Laragon

Gunakan **Git Windows** (terminal Laragon: *Menu → Terminal*, atau PowerShell):

```powershell
cd C:\laragon\www
git clone <URL-REPO-KELOMPOK> sisdis-wa-arch
cd sisdis-wa-arch
git config core.autocrlf input
```

Pastikan ada file `.gitattributes` berisi:

```
* text=auto eol=lf
```

Pilih **satu** git untuk commit (Windows **atau** WSL). Jangan campur di repo yang sama.

## Langkah 4 — Siapkan WSL agar Cocok dengan Laragon

### 4a. Alias PHP Laragon di WSL (agar Codex bisa cek sintaks)

Cari folder versi PHP:

```bash
ls /mnt/c/laragon/bin/php/
```

Tambahkan ke `~/.bashrc` (ganti `<versi>` sesuai hasil di atas, mis. `php-8.3.x-Win32-vs16-x64`):

```bash
alias php='/mnt/c/laragon/bin/php/<versi>/php.exe'
alias mysql-laragon='/mnt/c/laragon/bin/mysql/<versi-mysql>/bin/mysql.exe'
```

```bash
source ~/.bashrc
php -v
php -l /mnt/c/laragon/www/sisdis-wa-arch/app/public/index.php
```

### 4b. Agar `curl localhost` dari WSL menjangkau Laragon

Cara termudah (Windows 11 22H2+): buat/edit file `C:\Users\<NamaKamu>\.wslconfig`:

```
[wsl2]
networkingMode=mirrored
```

Lalu di PowerShell: `wsl --shutdown`, buka WSL lagi, uji:

```bash
curl -I http://localhost/
```

Jika tidak memakai mirrored mode, cari IP host Windows dari WSL:

```bash
ip route show default | awk '{print $3}'
# lalu: curl -I http://<IP-itu>/
```

### 4c. Menjalankan Codex

Dua pilihan:
- **Disarankan:** jalankan Codex dari **PowerShell di `C:\laragon\www\sisdis-wa-arch`** (lebih cepat, path native).
- Atau dari WSL di `/mnt/c/laragon/www/sisdis-wa-arch` (bisa, tapi I/O lebih lambat; gunakan alias `php` di atas).

## Langkah 5 — Database untuk Development (Pilih Salah Satu)

Sambil menunggu cloud DB siap, pakai DB lokal. **Production/demo tetap cloud DB.**

**Opsi A (paling mudah): MySQL bawaan Laragon**
- Host `127.0.0.1`, port `3306` (atau yang kamu ubah), user `root`, password kosong (default Laragon).
- Buat DB: buka HeidiSQL dari Laragon (*Menu → Database → HeidiSQL*) atau:
  ```powershell
  C:\laragon\bin\mysql\<versi-mysql>\bin\mysql.exe -u root -e "CREATE DATABASE sisdis_dev CHARACTER SET utf8mb4;"
  ```

**Opsi B: MySQL di Docker (WSL), port 3307 agar tidak bentrok**
```bash
docker run --name sisdis-mysql-dev -e MYSQL_ROOT_PASSWORD=dev123 \
  -e MYSQL_DATABASE=sisdis_dev -p 3307:3306 -d mysql:8
```
- Dari PHP Laragon (Windows) host `127.0.0.1`, port `3307`.
- Jangan commit password ini; hanya untuk lokal.

Impor schema:
```powershell
C:\laragon\bin\mysql\<versi-mysql>\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root sisdis_dev < database\schema.sql
```

## Langkah 6 — Konfigurasi Aplikasi

```powershell
cd C:\laragon\www\sisdis-wa-arch\app\config
copy config.example.php config.local.php
```

Isi `config.local.php` (contoh bentuk, sesuaikan nama key dengan `config.example.php`):

```php
return [
  'base_url'  => 'http://localhost/sisdis-wa-arch/app/public',
  'node_name' => 'laptop-NAMAKAMU',          // tampil di demo (origin_node)
  'db' => [
    'host' => '127.0.0.1',                    // dev lokal; ganti ke host cloud saat siap
    'port' => 3306,
    'name' => 'sisdis_dev',
    'user' => 'root',
    'pass' => '',
    'ssl_ca' => null,                         // isi path ca.pem untuk cloud DB
  ],
  'backup_service_url' => 'http://IP-PC-BACKUP/sisdis-wa-arch/backup-service/public',
  'backup_token'       => 'ISI-TOKEN-ACAK-PANJANG',
];
```

Buka `http://localhost/sisdis-wa-arch/app/public/status.php` → harus menampilkan "DB connected".

## Langkah 7 — Sambung ke Cloud DB (Bila Pengelola DB Sudah Siap)

1. Pengelola DB mengirim **host, port, nama DB, user, password** lewat chat pribadi (bukan commit Git) + file **CA certificate**.
2. Simpan CA di `app/config/certs/ca.pem`.
3. Uji dulu pakai client MySQL Laragon:
   ```powershell
   C:\laragon\bin\mysql\<versi-mysql>\bin\mysql.exe -h <HOST> -P <PORT> -u <USER> -p --ssl-ca=app\config\certs\ca.pem
   ```
   Jika masuk, ubah `config.local.php`: host/port/user/pass cloud + `'ssl_ca' => __DIR__ . '/certs/ca.pem'`.
4. Jika gagal konek: coba hotspot HP (jaringan kampus sering memblokir port DB), cek IP whitelist di dashboard provider.

## Langkah 8 — Setup PC Backup (hanya anggota pengelola backup)

1. Install Laragon seperti Langkah 2; tempatkan repo yang sama di `C:\laragon\www\sisdis-wa-arch\`.
2. Isi `backup-service\config\config.local.php`:
   ```php
   return [
     'mysqldump_path' => 'C:\\laragon\\bin\\mysql\\<versi-mysql>\\bin\\mysqldump.exe',
     'mysql_cli_path' => 'C:\\laragon\\bin\\mysql\\<versi-mysql>\\bin\\mysql.exe',
     'backup_dir'     => 'C:\\laragon\\backup-sisdis',   // di luar www agar tidak bisa diunduh via URL
     'shared_secret'  => 'SAMA-DENGAN-backup_token-DI-APP',
     'db' => [ /* kredensial cloud DB (hak backup/restore) + ssl_ca */ ],
   ];
   ```
3. Buat folder backup: `mkdir C:\laragon\backup-sisdis`.
4. Agar laptop lain bisa menjangkau PC ini — pilih salah satu:
   - **Tailscale (disarankan):** install di **Windows** (bukan di WSL) pada PC backup dan semua laptop, login dengan akun yang sama/ditambah ke tailnet; pakai IP `100.x.x.x` PC backup di `backup_service_url`.
   - **Satu hotspot/LAN:** pakai IP LAN (`ipconfig`) dan buka firewall (PowerShell **Admin**):
     ```powershell
     New-NetFirewallRule -DisplayName "Laragon Apache" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Private
     ```
5. Uji dari laptop lain: `http://<IP-PC-BACKUP>/sisdis-wa-arch/backup-service/public/list.php`.

## Langkah 9 — Verifikasi Setup (Semua Harus Ceklis Sebelum Mulai Fase 2)

- [ ] `http://localhost/sisdis-wa-arch/app/public/` terbuka
- [ ] `phpinfo()` menunjukkan `pdo_mysql` dan `openssl` aktif
- [ ] `status.php` menampilkan DB connected + waktu server DB
- [ ] Dari WSL: `php -v` jalan dan `curl -I http://localhost/` membalas 200/302
- [ ] Docker tetap bisa dipakai tanpa mematikan Laragon (tidak ada bentrok port)
- [ ] `git status` bersih dari `config.local.php` (ter-ignore)
- [ ] (Pengelola backup) `mysqldump.exe --version` jalan dan PC backup terjangkau dari laptop lain

## Troubleshooting Cepat

| Gejala | Penyebab umum | Solusi |
|--------|---------------|--------|
| Apache/MySQL Laragon merah | Port dipakai Docker | Langkah 1; stop container atau ganti port |
| `could not find driver` | `pdo_mysql` belum aktif | PHP → Extensions → centang, restart Laragon |
| `SQLSTATE[HY000] [2002]` | Host/port salah atau DB mati | Cek port (3306 vs 3307), pastikan MySQL hijau/ container hidup |
| `SSL connection error` ke cloud DB | CA path salah/tidak ada | Cek `ssl_ca` dan file `ca.pem`; uji dengan `mysql.exe --ssl-ca` |
| Halaman 404 di subfolder | `base_url` salah | Samakan dengan URL browser; semua link pakai `BASE_URL` |
| `php: command not found` di WSL | Alias belum dibuat | Langkah 4a |
| `curl localhost` WSL gagal | NAT mode WSL | Langkah 4b (mirrored) atau IP host |
| Diff git besar semua file | CRLF vs LF | `.gitattributes` + `autocrlf input`; satu git saja |
| Backup gagal "mysqldump not found" | Path versi MySQL salah | Cek folder di `C:\laragon\bin\mysql\` dan perbarui config |
| PC backup tidak terjangkau | Firewall / beda jaringan | Rule firewall Private atau Tailscale |
| Lupa Laragon sedang jalan saat demo | — | Selalu **Start All** dan cek `status.php` 10 menit sebelum presentasi |

## Prompt Awal untuk Codex (salin-tempel)

```
Baca PRD_Sisdis_WhatsApp_Architecture.md dan SETUP_Laragon_Docker_WSL.md di folder ini.
Konteks: repo ada di C:\laragon\www\sisdis-wa-arch (di WSL: /mnt/c/laragon/www/sisdis-wa-arch).
Runtime target = Laragon (Apache + PHP 8). JANGAN buat Docker/docker-compose untuk sistem ini.
Mulai dari Fase 1 saja: struktur folder, .gitignore, .gitattributes, config.example.php,
database/schema.sql + seed.sql, app/src/db.php (PDO, TLS opsional, SET time_zone='+00:00'),
dan app/api/status.php + app/public/status.php.
Gunakan BASE_URL dari config untuk semua URL. Semua SQL prepared statement.
Setelah selesai, tulis cara menjalankan dan hasil verifikasi Fase 1, lalu berhenti dan tunggu perintah untuk Fase 2.
```
