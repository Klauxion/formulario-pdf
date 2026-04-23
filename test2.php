<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Mock php://input by defining our own stream wrapper or just modifying submit.php to read from a file?
// Actually we can just run submit.php through CLI by piping to it:
// echo '{"form_data":{"Primeiro Nome":"Test","Email":"test@test.com"}}' | php public/submit.php
