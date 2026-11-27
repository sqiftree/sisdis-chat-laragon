# Panduan Koneksi Cloud Database — Bagian Yashif

## Ringkasan Cloud Database

- **Provider:** TiDB Cloud Starter (kompatibel MySQL, wajib TLS).
- **Database aktif:** `sisdis_dev`.
- **Schema & Seed:** Sudah diimpor (`database/schema.sql` dan `database/seed.sql`) — **tidak perlu impor ulang**.
  - 3 tabel: `users`, `messages`, `backup_logs`.
  - 8 akun aplikasi: `laura`, `giska`, `wawa`, `yashif` (`admin`), `wantech` (`admin`), `ayu`, `bima`, `citra` *(password login aplikasi default: `sisdis123`)*.
- **Kredensial & Sertifikat:** Detail `host`, `port`, `user`, `pass`, dan file sertifikat `ca.pem` dibagikan terpisah lewat chat privat grup. **Jangan pernah commit `config.local.php` atau `*.pem` ke Git.**

---

## Panduan untuk Laura, Giska, dan Wawa (App Server Laragon)

1. Tarik update terbaru di folder repo proyek Laragon masing-masing (`C:\laragon\www\sisdis-wa-arch` atau sesuai folder masing-masing):
   ```bash
   git pull origin main
   ```
2. Pastikan di **Laragon**:
   - **Apache** dan PHP 8.1+ aktif.
   - Menu **PHP → Extensions** mencentang `pdo_mysql`, `mysqli`, `openssl`, `curl`, `mbstring`, `fileinfo` *(jika baru dicentang, klik **Stop All → Start All**)*.
3. Simpan file sertifikat **`ca.pem`** (yang dikirim di grup) ke folder:
   ```text
   app\config\certs\ca.pem
   ```
4. Salin `app\config\config.example.php` menjadi `app\config\config.local.php`, lalu isi seperti contoh berikut (ganti kredensial sesuai yang dibagikan di chat grup):
   ```php
   <?php
   return [
       'base_url' => '/sisdis-wa-arch/app/public', // sesuaikan jika nama folder di www berbeda
       'node_name' => 'laptop-NAMA',              // contoh: laptop-laura / laptop-giska / laptop-wawa
       'db' => [
           'host' => 'ISI_HOST_DARI_CHAT_GRUP',
           'port' => 4000,
           'name' => 'sisdis_dev',
           'user' => 'ISI_USER_DARI_CHAT_GRUP',
           'pass' => 'ISI_PASSWORD_DARI_CHAT_GRUP',
           'ssl_ca' => __DIR__ . '/certs/ca.pem',
       ],
       'backup_service_url' => '', // nanti diisi setelah PC Backup Wantech siap
       'backup_token' => '',
   ];
   ```
5. Buka halaman status di browser:
   - `http://localhost/sisdis-wa-arch/app/public/status.php` *(tambahkan `:8080` setelah `localhost` jika Apache Laragon memakai port `8080`)*.
   - Pastikan tampil **"Database terhubung"**, nama node laptop masing-masing, dan waktu WIB.
6. **Ambil screenshot** halaman `status.php` yang menampilkan *"Database terhubung"* + nama laptopmu, lalu kirim ke grup untuk bukti integrasi multi-node.

---

## Panduan untuk Wantech (PC Backup)

1. Tarik update terbaru di Laragon PC Backup:
   ```bash
   git pull origin main
   ```
2. Simpan file **`ca.pem`** (yang dikirim di grup) ke:
   ```text
   backup-service\config\certs\ca.pem
   ```
   *(Simpan juga ke `app\config\certs\ca.pem` jika PC Backup ikut menjalankan app server).*
3. Salin `backup-service\config\config.example.php` menjadi `backup-service\config\config.local.php`, lalu isi:
   - `mysqldump_path` & `mysql_cli_path`: arahkan ke folder `bin\mysql\<versi>\bin\` di Laragon PC Backup.
   - `backup_dir`: folder di luar `www`, misalnya `C:/laragon/backup-sisdis`.
   - `shared_secret`: token acak panjang.
   - Blok `db`:
     ```php
     'db' => [
         'host' => 'ISI_HOST_DARI_CHAT_GRUP',
         'port' => 4000,
         'name' => 'sisdis_dev',
         'user' => 'ISI_USER_DARI_CHAT_GRUP',
         'pass' => 'ISI_PASSWORD_DARI_CHAT_GRUP',
         'ssl_ca' => __DIR__ . '/certs/ca.pem',
     ],
     ```
4. Setelah backup service siap dan port Apache dapat diakses via Tailscale/LAN, bagikan `backup_service_url` dan `shared_secret` ke grup agar semua anggota melengkapi `backup_service_url` dan `backup_token` di `app\config\config.local.php`.

---

## Troubleshooting Singkat

- **Gagal konek di Wi-Fi kampus:** Coba pindah ke hotspot HP (port `4000` sering diblokir jaringan kampus).
- **`Server aplikasi tidak dapat dijangkau`:** Periksa nilai `base_url` di `app\config\config.local.php` agar sesuai dengan URL di browser.
- **`Database belum terhubung`:** Pastikan lokasi `app\config\certs\ca.pem` benar, ekstensi `pdo_mysql` & `openssl` aktif, dan `host`/`port`/`user`/`pass` tidak ada spasi berlebih.
