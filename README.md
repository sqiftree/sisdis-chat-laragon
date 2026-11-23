# Mini Chat Sistem Terdistribusi

Fase 1 menyediakan schema MySQL, tiga kontak demo, koneksi PDO dan halaman Status Sistem yang mengambil waktu UTC dari database dan menampilkannya dalam WIB.

## Jalankan di Laragon

1. Tempatkan proyek di `C:\laragon\www\sisdis-wa-arch` dan aktifkan Apache, MySQL serta ekstensi PHP `pdo_mysql` dan `openssl`.
2. Buat database `sisdis_dev` dengan charset `utf8mb4`. Pilih database tersebut di HeidiSQL, lalu jalankan `database/schema.sql` dan `database/seed.sql` secara berurutan.
3. Salin `app/config/config.example.php` menjadi `app/config/config.local.php` pada tiap laptop. Isi konfigurasi database, nama node dan base URL. Untuk cloud, isi host provider dan `ssl_ca` dengan path sertifikat CA. Setiap laptop harus menunjuk ke database yang sama.
4. Buka [Status Sistem](http://localhost/sisdis-wa-arch/app/public/). Saat koneksi berhasil, halaman menampilkan nama node dan waktu database. Tombol **Periksa lagi** mengulang pemeriksaan.

Panduan instalasi dan jaringan tersedia di [Setup Laragon](SETUP_Laragon_Docker_WSL.md). Urutan pengembangan dan kriteria demo ada di [PRD](PRD_Sisdis_WhatsApp_Architecture.md).

## Sinkronisasi pada laptop pengembangan

Edit kode di `~/SISTER/TUBES`. Selama WSL aktif, timer pengguna memeriksa perubahan sekitar setiap lima detik dan menyalin kode aplikasi, schema, dan dokumen proyek ke `C:\laragon-sisdis\www\sisdis-wa-arch`. File aplikasi baru ikut tersalin sebelum commit. Penghapusan file yang sebelumnya disalin juga diterapkan. Konfigurasi lokal, sertifikat dan backup dikelola terpisah di tiap lingkungan.

Untuk memasang ulang timer dari root repo pada laptop ini:

```bash
mkdir -p ~/.config/systemd/user
cp scripts/systemd/sisdis-laragon-sync.* ~/.config/systemd/user/
systemctl --user daemon-reload
systemctl --user enable --now sisdis-laragon-sync.timer
```

Jalankan `scripts/sync-laragon.sh` untuk sinkronisasi langsung. Cek hasil terakhir dengan `systemctl --user status sisdis-laragon-sync.service`. Klik `C:\laragon-sisdis\Mulai-Sisdis.cmd` untuk menjalankan aplikasi, lalu buka [Status Sistem lokal](http://127.0.0.1:8080/). Commit dan push ke GitHub dilakukan terpisah.
