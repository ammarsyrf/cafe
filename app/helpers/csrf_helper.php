<?php
// File: csrf_helper.php
// Helper functions untuk CSRF protection

/**
 * Generate CSRF token field untuk form HTML
 */
function csrf_token_field()
{
    $security = Security::getInstance();
    $token = $security->generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Generate CSRF token untuk AJAX requests
 */
function csrf_token()
{
    $security = Security::getInstance();
    return $security->generateCSRFToken();
}

/**
 * Validate CSRF token dari form submission
 */
function csrf_verify()
{
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    $security = Security::getInstance();
    return $security->validateCSRFToken($token);
}
