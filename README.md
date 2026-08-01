# Keuangan Kelas RPL 1

## Setup
1. Place folder in `C:\xampp\htdocs\cashflow-kelas`
2. Start XAMPP (Apache + MySQL)
3. Open `http://localhost/phpmyadmin`, create DB `cashflow_kelas`
4. Import `config/schema.sql`
5. Run `php tests/seed_admin.php` to set the bendahara password hash
6. (Optional) Run `php tests/seed_dummy.php` for sample data

## URLs
- Public SPA: `http://localhost/cashflow-kelas/`
- Admin login: `http://localhost/cashflow-kelas/login.php` (admin / admin123)

## Tests
Run from project root:
```bash
php tests/test_*.php
```
