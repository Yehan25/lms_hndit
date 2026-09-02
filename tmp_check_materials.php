<?php
$conn = new mysqli('localhost','root','root','lms_hndit');
if ($conn->connect_error) {
    echo 'DB CONNECT ERROR: ' . $conn->connect_error;
    exit(1);
}
$res = $conn->query('SELECT id, course_id, title, material_type, file_path, uploaded_at FROM materials ORDER BY id DESC LIMIT 10');
if (!$res) {
    echo 'DB QUERY ERROR: ' . $conn->error;
    exit(1);
}
while ($row = $res->fetch_assoc()) {
    echo implode(' | ', $row) . PHP_EOL;
}
