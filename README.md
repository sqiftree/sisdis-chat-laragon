# Mini Chat Sistem Terdistribusi

Fase 1 menyediakan schema MySQL, tiga kontak demo, koneksi PDO dan halaman Status Sistem yang mengambil waktu UTC dari database dan menampilkannya dalam WIB.

## Jalankan di Laragon

1. Tempatkan proyek di `C:\laragon\www\sisdis-wa-arch` dan aktifkan Apache, MySQL serta ekstensi PHP `pdo_mysql` dan `openssl`.
2. Buat database `sisdis_dev` dengan charset `utf8mb4`. Pilih database tersebut di HeidiSQL, lalu jalankan `database/schema.sql` dan `database/seed.sql` secara berurutan.
3. Salin `app/config/config.example.php` menjadi `app/config/config.local.php` pada tiap laptop. Isi konfigurasi database, nama node dan base URL. Untuk cloud, isi host provider dan `ssl_ca` dengan path sertifikat CA. Setiap laptop harus menunjuk ke database yang sama.
4. Buka [Status Sistem](http://localhost/sisdis-wa-arch/app/public/). Saat koneksi berhasil, halaman menampilkan nama node dan waktu database. Tombol **Periksa lagi** mengulang pemeriksaan.

Panduan instalasi dan jaringan tersedia di [Setup Laragon](SETUP_Laragon_Docker_WSL.md). Urutan pengembangan dan kriteria demo ada di [PRD](PRD_Sisdis_WhatsApp_Architecture.md).
