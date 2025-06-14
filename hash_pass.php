<?php
// hash_pass.php (contoh cara menghasilkan hash password)
if (!empty($argv[1])) {
    $pw = $argv[1];
    echo "Password asli: $pw\n";
    echo "Password hash : " . password_hash($pw, PASSWORD_DEFAULT) . "\n";
} else {
    echo "Gunakan: php hash_pass.php passwordAnda\n";
}
