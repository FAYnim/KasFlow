# Keuangan Kelas RPL 1

## Pengaturan
1. Letakkan folder di `C:\xampp\htdocs\cashflow-kelas`
2. Jalankan XAMPP (Apache + MySQL)
3. Buka `http://localhost/phpmyadmin`, buat DB `cashflow_kelas`
4. Import `config/schema.sql`
5. Jalankan `php tests/seed_admin.php` untuk mengatur hash password bendahara
6. (Opsional) Jalankan `php tests/seed_dummy.php` untuk data contoh

## URL
- SPA Publik: `http://localhost/cashflow-kelas/`
- Login admin: `http://localhost/cashflow-kelas/login.php` (admin / admin123)

## Pengujian
Jalankan dari root proyek:
```bash
php tests/test_*.php
```
