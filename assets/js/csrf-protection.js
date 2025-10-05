/**
 * CSRF Protection untuk AJAX Requests
 * Include file ini di halaman yang menggunakan AJAX
 */

// Global CSRF token variable
var csrfToken = '<?= csrf_token() ?>';

// Setup AJAX dengan CSRF token
$(document).ready(function() {
    // Setup CSRF token untuk semua AJAX POST requests
    $.ajaxSetup({
        beforeSend: function(xhr, settings) {
            if (settings.type === 'POST' && !this.crossDomain) {
                // Add CSRF token to POST data
                if (settings.data) {
                    settings.data += '&csrf_token=' + encodeURIComponent(csrfToken);
                } else {
                    settings.data = 'csrf_token=' + encodeURIComponent(csrfToken);
                }
            }
        }
    });
    
    // Setup CSRF token untuk form submissions via AJAX
    $('form').on('submit', function(e) {
        var form = $(this);
        
        // Check if form already has CSRF token
        if (form.find('input[name="csrf_token"]').length === 0) {
            // Add CSRF token as hidden field
            form.append('<input type="hidden" name="csrf_token" value="' + csrfToken + '">');
        }
    });
});

// Function untuk refresh CSRF token (jika diperlukan)
function refreshCSRFToken() {
    $.get('get_csrf_token.php', function(data) {
        if (data.token) {
            csrfToken = data.token;
            // Update semua hidden CSRF fields
            $('input[name="csrf_token"]').val(csrfToken);
        }
    });
}

// Function untuk secure AJAX POST dengan CSRF
function secureAjaxPost(url, data, successCallback, errorCallback) {
    // Ensure data is object
    if (typeof data === 'string') {
        data = {};
    }
    
    // Add CSRF token
    data.csrf_token = csrfToken;
    
    $.ajax({
        url: url,
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (typeof successCallback === 'function') {
                successCallback(response);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            
            // Handle CSRF token mismatch
            if (xhr.status === 403 && xhr.responseJSON && xhr.responseJSON.message.includes('Token')) {
                alert('Session keamanan telah berakhir. Halaman akan dimuat ulang.');
                location.reload();
                return;
            }
            
            if (typeof errorCallback === 'function') {
                errorCallback(xhr, status, error);
            } else {
                alert('Terjadi kesalahan: ' + error);
            }
        }
    });
}

// Example usage:
/*
secureAjaxPost('api/update_data.php', {
    id: 123,
    name: 'New Name'
}, function(response) {
    if (response.success) {
        alert('Data berhasil diupdate!');
    } else {
        alert('Error: ' + response.message);
    }
});
*/