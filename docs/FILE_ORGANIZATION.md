# Ringkasan Organisasi File

## Struktur Proyek Setelah Penataan Ulang

- `.htaccess` - Konfigurasi server
- `error.html` - Halaman error

### /app/

- `/app/config/` - File konfigurasi
  - `config.php` - Konfigurasi utama (PDO)
  - `db_connect.php` - Koneksi database
- `/app/helpers/` - Helper dan utilitas

### /admin/

- `kelola_kategori.php` - Manajemen kategori
- `laporan.php` - Laporan
- `/admin/includes/` - Header/footer admin
- `/admin/menu/` - Manajemen menu
- `/admin/penjualan/` - Manajemen penjualan

### /cashier/ (sebelumnya kasir/)

- `index.php` - Dashboard kasir & POS
- `login_kasir.php` - Login kasir

### /auth/

- `admin_login.php` - Login admin/superadmin
- `admin_logout.php` - Logout admin
- `login.php` - Login member
- `logout.php` - Logout member
- `register.php` - Registrasi member

### /assets/

- `/assets/css/` - File CSS (rencana)

### /utils/

- `setup.php` - Setup database
- `debug.php` - Debug
- `buat_hash.php` - Generator hash password

### /docs/

- `SECURITY_DOCUMENTATION.md` - Panduan keamanan
- `FILE_ORGANIZATION.md` - Dokumentasi struktur file

### /libs/

- `/libs/phpqrcode/` - Library QR code

### /uploads/

- `/uploads/profiles/` - Foto profil member
- `.htaccess` - Keamanan upload

## File yang Dipindahkan

- `db_connect.php` & `config.php` dari root ke `/app/config/`
- `middleware.php` dari root ke `/app/helpers/`
- File login/logout/register admin/member ke `/auth/`
- Folder `kasir/` diubah menjadi `cashier/`

## Penjelasan

Penataan ulang ini bertujuan agar:

1. Setiap folder punya fungsi jelas (modul, konfigurasi, helper, dokumentasi, dll)
2. Struktur lebih aman, file sensitif tidak di root
3. Mudah dikembangkan dan dipelihara
4. Semua `require_once` dan `include` sudah disesuaikan dengan struktur baru

## Contoh Perubahan Path

- Sebelumnya: `require_once 'db_connect.php';`
- Sekarang: `require_once 'app/config/db_connect.php';`

- Sebelumnya: `require_once 'config.php';`
- Sekarang: `require_once 'app/config/config.php';`

- Sebelumnya: `require_once '../middleware.php';`
- Sekarang: `require_once '../app/helpers/middleware.php';`

## Manfaat

1. **Lebih Terstruktur**: Mudah cari file sesuai fungsinya
2. **Keamanan**: File penting tidak di root
3. **Scalable**: Mudah tambah fitur baru
4. **Maintenance**: Mudah perbaikan & pengembangan

## Saran Selanjutnya

1. Tambah file CSS di `/assets/css/`
2. Pertimbangkan menambah `/models/` dan `/controllers/` untuk pola MVC
3. Tambah `/tests/` untuk unit test
4. Pertimbangkan `/api/` untuk endpoint API

All require_once and include statements have been updated to reflect the new file structure:

### Database Connections

- Old: `require_once 'db_connect.php';`
- New: `require_once 'app/config/db_connect.php';`

### Configuration Files

- Old: `require_once 'config.php';`
- New: `require_once 'app/config/config.php';`

### Middleware

- Old: `require_once '../middleware.php';`
- New: `require_once '../app/helpers/middleware.php';`

## Benefits of This Organization

1. **Clear Separation of Concerns**: Each folder has a specific purpose
2. **MVC-like Structure**: App logic separated from presentation
3. **Security**: Configuration and helpers are in protected directories
4. **Scalability**: Easy to add new features in appropriate folders
5. **Maintenance**: Easier to locate and maintain files

## Next Steps

1. Add more CSS files to `/assets/css/`
2. Consider adding `/models/` and `/controllers/` for full MVC pattern
3. Add `/tests/` directory for unit tests
4. Consider `/api/` directory for API endpoints
