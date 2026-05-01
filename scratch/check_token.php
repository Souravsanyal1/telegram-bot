<?php
$token = '8682225634:AAG8b4e5wAqT_20XobD8uVUYEqCxg-ae2wk';
$url = "https://api.telegram.org/bot$token/getMe";
$response = file_get_contents($url);
echo "Response: $response\n";
?>
