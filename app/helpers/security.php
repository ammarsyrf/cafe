<?php
// File: security.php
// Kelas untuk menangani keamanan aplikasi

class Security
{
    private static $instance = null;
    private $csrfToken = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->initSession();
        $this->setSecurityHeaders();
    }

    /**
     * Inisialisasi session yang aman
     */
    private function initSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Konfigurasi session yang aman
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.gc_maxlifetime', 3600); // 1 jam

            session_start();

            // Regenerate session ID untuk mencegah session fixation
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
                $_SESSION['created_at'] = time();
            }

            // Timeout session setelah 30 menit inaktivitas
            if (
                isset($_SESSION['last_activity']) &&
                (time() - $_SESSION['last_activity'] > 1800)
            ) {
                session_unset();
                session_destroy();
                session_start();
            }
            $_SESSION['last_activity'] = time();
        }
    }

    /**
     * Set security headers
     */
    private function setSecurityHeaders()
    {
        // Prevent XSS attacks
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');

        // Strict Transport Security (HTTPS only)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self'");

        // Prevent information disclosure
        header('Server: ');
        header('X-Powered-By: ');
    }

    /**
     * Generate CSRF token
     */
    public function generateCSRFToken()
    {
        if ($this->csrfToken === null) {
            $this->csrfToken = bin2hex(random_bytes(32));
            $_SESSION['csrf_token'] = $this->csrfToken;
        }
        return $this->csrfToken;
    }

    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token)
    {
        return isset($_SESSION['csrf_token']) &&
            hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Sanitize input untuk mencegah XSS
     */
    public static function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate email
     */
    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone number
     */
    public static function validatePhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }

    /**
     * Generate secure password hash
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
    }

    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Rate limiting untuk login attempts
     */
    public static function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 900)
    {
        $key = 'login_attempts_' . md5($identifier);

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'time' => time()];
        }

        $attempts = $_SESSION[$key];

        // Reset counter jika window sudah lewat
        if (time() - $attempts['time'] > $timeWindow) {
            $_SESSION[$key] = ['count' => 0, 'time' => time()];
            return true;
        }

        // Check jika sudah mencapai limit
        if ($attempts['count'] >= $maxAttempts) {
            return false;
        }

        return true;
    }

    /**
     * Increment rate limit counter
     */
    public static function incrementRateLimit($identifier)
    {
        $key = 'login_attempts_' . md5($identifier);

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'time' => time()];
        }

        $_SESSION[$key]['count']++;
    }

    /**
     * Reset rate limit counter
     */
    public static function resetRateLimit($identifier)
    {
        $key = 'login_attempts_' . md5($identifier);
        unset($_SESSION[$key]);
    }

    /**
     * Validate and sanitize file upload
     */
    public static function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'], $maxSize = 5242880)
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid parameters.');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('No file sent.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('Exceeded filesize limit.');
            default:
                throw new RuntimeException('Unknown errors.');
        }

        // Check file size
        if ($file['size'] > $maxSize) {
            throw new RuntimeException('Exceeded filesize limit.');
        }

        // Check MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        $allowedMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        ];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (
            !in_array($ext, $allowedTypes) ||
            !isset($allowedMimes[$ext]) ||
            $allowedMimes[$ext] !== $mimeType
        ) {
            throw new RuntimeException('Invalid file format.');
        }

        return true;
    }

    /**
     * Generate secure filename
     */
    public static function generateSecureFilename($originalName)
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    }

    /**
     * Safe redirect - prevent open redirect attacks
     */
    public static function safeRedirect($url, $fallback = '/')
    {
        // Parse URL
        $parsed = parse_url($url);

        // Only allow relative URLs or same-origin URLs
        if (isset($parsed['host'])) {
            $currentHost = $_SERVER['HTTP_HOST'] ?? '';
            if ($parsed['host'] !== $currentHost) {
                $url = $fallback;
            }
        }

        // Remove any dangerous protocols
        if (
            isset($parsed['scheme']) &&
            !in_array(strtolower($parsed['scheme']), ['http', 'https'])
        ) {
            $url = $fallback;
        }

        header('Location: ' . $url);
        exit();
    }

    /**
     * Log security events
     */
    public static function logSecurityEvent($event, $details = [])
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'event' => $event,
            'details' => $details
        ];

        error_log('SECURITY: ' . json_encode($logEntry));
    }

    /**
     * Check if user has valid session dan role
     */
    public static function checkAuth($requiredRole = null)
    {
        if (!isset($_SESSION['initiated'])) {
            return false;
        }

        // Check session timeout
        if (
            isset($_SESSION['last_activity']) &&
            (time() - $_SESSION['last_activity'] > 1800)
        ) {
            return false;
        }

        // Check role if specified
        if ($requiredRole !== null) {
            $userRole = null;

            if (isset($_SESSION['admin_role'])) {
                $userRole = $_SESSION['admin_role'];
            } elseif (isset($_SESSION['kasir']['role'])) {
                $userRole = $_SESSION['kasir']['role'];
            } elseif (isset($_SESSION['member']['role'])) {
                $userRole = $_SESSION['member']['role'];
            }

            if (
                $userRole !== $requiredRole &&
                !($requiredRole === 'admin' && $userRole === 'superadmin')
            ) {
                return false;
            }
        }

        return true;
    }
}

// Initialize security
$security = Security::getInstance();
