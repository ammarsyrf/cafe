<?php
$password_biasa = 'ammarfte'; // Ganti dengan password yang ingin di-hash
$hash = password_hash($password_biasa, PASSWORD_DEFAULT);
echo $hash;
