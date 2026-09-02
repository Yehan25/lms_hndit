<?php
header('Content-Type: text/plain');
$dir = __DIR__ . '/uploads/materials';
$writable = is_writable($dir) ? 'yes' : 'no';
echo "uploads/materials exists=" . (is_dir($dir) ? 'yes' : 'no') . "\n";
echo "writable=$writable\n";
$testFile = $dir . '/test_upload_' . time() . '.txt';
$ok = @file_put_contents($testFile, 'test');
echo "file_put_contents ok=" . ($ok !== false ? 'yes' : 'no') . "\n";
if ($ok !== false) {
    echo "created=" . basename($testFile) . "\n";
    @unlink($testFile);
}
$whoami = null;
if (function_exists('exec')) {
    @exec('whoami', $output, $ret);
    if ($ret === 0) {
        echo "whoami=" . implode(' ', $output) . "\n";
    }
}
