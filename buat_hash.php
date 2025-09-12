<?php
$password_biasa = 'superadmin123'; // Ganti dengan password yang ingin di-hash
$hash = password_hash($password_biasa, PASSWORD_DEFAULT);
echo $hash;
