# Sistem Login Admin & Superadmin

## 📋 File yang Dibuat

### 1. `admin_login.php`

- Halaman login khusus untuk admin dan superadmin
- Validasi role: hanya `admin` dan `superadmin` yang bisa login
- UI modern dengan Tailwind CSS
- Form validasi dan pesan error/success

### 2. `admin_logout.php`

- Menghapus session admin
- Redirect ke halaman login dengan pesan sukses

## 🔧 Perubahan File

### `superadmin/includes/header.php`

- ✅ Tambah authentication check di awal file
- ✅ Update logout link ke `admin_logout.php`
- ✅ Tampilkan nama dan role admin yang login

## 🔐 Session yang Digunakan

```php
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = $user['id'];
$_SESSION['admin_username'] = $user['username'];
$_SESSION['admin_name'] = $user['name'];
$_SESSION['admin_email'] = $user['email'];
$_SESSION['admin_role'] = $user['role']; // 'admin' atau 'superadmin'
```

## 🚀 Cara Menggunakan

1. **Login**: Akses `http://localhost/cafe/admin_login.php`
2. **Masuk** dengan username/password admin atau superadmin
3. **Otomatis redirect** ke `superadmin/index.php`
4. **Logout**: Klik tombol Logout di sidebar

## 🛡️ Keamanan

- Semua halaman admin dilindungi authentication check
- Hanya user dengan role `admin` atau `superadmin` yang bisa login
- Session admin terpisah dari session member
- Password ter-hash dengan password_verify()

## ✅ Status

Sistem login admin telah berhasil dibuat dan terintegrasi dengan sistem yang ada!
