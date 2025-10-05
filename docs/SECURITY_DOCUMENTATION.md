# DOKUMENTASI KEAMANAN SISTEM CAFE

## Perbaikan Keamanan yang Telah Diimplementasi

### 1. **Sistem Autentikasi yang Aman**

- **Password Hashing**: Menggunakan Argon2ID algorithm yang kuat
- **Session Management**:
  - Secure session configuration
  - Session regeneration untuk mencegah session fixation
  - Session timeout setelah 30 menit inaktivitas
  - HttpOnly dan Secure cookies
- **Rate Limiting**: Membatasi percobaan login (5 attempts per 15 menit)
- **Multi-level Authentication**: Member, Kasir, Admin, Superadmin

### 2. **Proteksi terhadap SQL Injection**

- **Prepared Statements**: Semua query menggunakan prepared statements
- **Input Validation**: Validasi dan sanitasi semua input user
- **Database Configuration**: SQL mode ketat dan disable multiple statements

### 3. **Proteksi terhadap XSS (Cross-Site Scripting)**

- **Input Sanitization**: Sanitasi output dengan htmlspecialchars
- **Content Security Policy**: CSP headers untuk membatasi resource loading
- **XSS Detection**: Pattern matching untuk mendeteksi script injection

### 4. **CSRF Protection**

- **CSRF Tokens**: Token unik untuk setiap form submission
- **Referer Validation**: Validasi origin request untuk POST requests
- **Helper Functions**: csrf_token_field(), csrf_verify()

### 5. **Kontrol Akses & Otorisasi**

- **Role-based Access Control**: Admin, Kasir, Member dengan permission berbeda
- **Middleware**: Validasi akses di setiap endpoint sensitif
- **Session Validation**: Cek role dan status aktif user

### 6. **File Upload Security**

- **File Type Validation**: Whitelist extension dan MIME type validation
- **File Size Limits**: Batas ukuran file (2MB profile, 5MB menu)
- **Secure Filename**: Generate nama file acak untuk mencegah directory traversal
- **Upload Directory Protection**: .htaccess mencegah eksekusi PHP di folder upload

### 7. **Security Headers**

- **X-Content-Type-Options**: nosniff
- **X-Frame-Options**: DENY (clickjacking protection)
- **X-XSS-Protection**: Aktifkan XSS filter browser
- **Content-Security-Policy**: Batasi resource loading
- **Referrer-Policy**: Kontrol informasi referrer

### 8. **Logging & Monitoring**

- **Security Event Logging**: Log semua aktivitas mencurigakan
- **Failed Login Tracking**: Track percobaan login yang gagal
- **Error Handling**: Error messages yang tidak mengekspos informasi sensitif

## File-file Keamanan Baru

### `/security.php` - Security Class

Class utama untuk handling keamanan aplikasi:

- Session management
- Password hashing/verification
- Rate limiting
- Input validation
- File upload validation
- Security logging

### `/middleware.php` - Security Middleware

Middleware untuk:

- Authentication checks
- Authorization validation
- Input sanitization
- Attack detection (SQL injection, XSS)
- Referer validation

### `/csrf_helper.php` - CSRF Helper Functions

Helper functions untuk CSRF protection:

- `csrf_token_field()` - Generate hidden input field
- `csrf_token()` - Get current token
- `csrf_verify()` - Validate token

### `/.htaccess` - Web Server Security

- Block suspicious requests
- Prevent access to sensitive files
- Security headers
- Bot protection

### `/uploads/.htaccess` - Upload Directory Protection

- Prevent PHP execution in uploads folder
- Only allow image files
- Prevent directory listing

## Rekomendasi Tambahan

### 1. **Database Security**

```sql
-- Buat user database khusus dengan privilege terbatas
CREATE USER 'cafe_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE ON db_cafe.* TO 'cafe_user'@'localhost';
FLUSH PRIVILEGES;

-- Tambahkan field is_active ke tabel yang belum ada
ALTER TABLE members ADD COLUMN is_active TINYINT(1) DEFAULT 1;
ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1;
```

### 2. **Environment Configuration**

Buat file `.env` untuk konfigurasi sensitif:

```
DB_HOST=localhost
DB_NAME=db_cafe
DB_USER=cafe_user
DB_PASS=your_secure_password
SECRET_KEY=your_32_character_secret_key
```

### 3. **HTTPS Configuration**

- Install SSL certificate
- Redirect semua HTTP ke HTTPS
- Enable HSTS headers
- Update BASE_URL ke https://

### 4. **Backup & Recovery**

```bash
# Automated backup script
#!/bin/bash
mysqldump -u backup_user -p db_cafe > /backup/cafe_$(date +%Y%m%d_%H%M%S).sql
find /backup -name "cafe_*.sql" -mtime +7 -delete
```

### 5. **Monitoring & Alerting**

- Setup log monitoring untuk security events
- Alert untuk multiple failed login attempts
- Monitor file changes di directory penting

## Penggunaan Sistem Keamanan

### 1. **Dalam Form HTML**

```html
<form method="POST">
  <?= csrf_token_field() ?>
  <!-- form fields -->
</form>
```

### 2. **Dalam AJAX Requests**

```javascript
$.ajaxSetup({
  beforeSend: function (xhr, settings) {
    if (settings.type === "POST") {
      settings.data += "&csrf_token=" + "<?= csrf_token() ?>";
    }
  },
});
```

### 3. **Authentication Check**

```php
// Di awal setiap protected page
require_once 'middleware.php';
Middleware::requireAdminAuth(); // atau requireKasirAuth(), requireMemberAuth()
```

### 4. **File Upload**

```php
try {
    Security::validateFileUpload($_FILES['image'], ['jpg', 'png'], 5242880);
    $filename = Security::generateSecureFilename($_FILES['image']['name']);
    // proceed with upload
} catch (Exception $e) {
    // handle error
}
```

## Status Keamanan

✅ **SELESAI**:

- SQL Injection Protection
- XSS Protection
- CSRF Protection
- Authentication & Authorization
- Session Security
- File Upload Security
- Security Headers
- Input Validation
- Rate Limiting
- Security Logging

🔍 **PERLU DIPANTAU**:

- Log security events secara berkala
- Update dependency jika ada
- Review access logs
- Monitor untuk pattern serangan baru

## Catatan Penting

1. **Ganti Password Default**: Pastikan ganti semua password default
2. **Update Berkala**: Update PHP dan dependencies secara berkala
3. **Backup Regular**: Lakukan backup database dan files secara rutin
4. **Monitor Logs**: Review security logs secara berkala
5. **SSL Certificate**: Install SSL certificate untuk production
6. **Firewall**: Setup firewall di server level
7. **Server Hardening**: Lakukan server hardening sesuai best practices

## Testing Keamanan

### 1. **SQL Injection Test**

```
# Test input: ' OR 1=1 --
# Harus ditolak oleh sistem
```

### 2. **XSS Test**

```html
# Test input:
<script>
  alert("XSS");
</script>
# Harus di-sanitize atau ditolak
```

### 3. **CSRF Test**

```
# Submit form tanpa CSRF token
# Harus ditolak dengan error message
```

### 4. **File Upload Test**

```
# Upload file .php atau .exe
# Harus ditolak
```

### 5. **Authentication Test**

```
# Akses halaman admin tanpa login
# Harus redirect ke login page
```

Sistem keamanan ini sudah mengimplementasi best practices untuk aplikasi web PHP dan memberikan perlindungan berlapis terhadap serangan umum.
