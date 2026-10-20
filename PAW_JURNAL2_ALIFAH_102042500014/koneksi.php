<?php
$host    = 'localhost';
$user    = 'root';
$pass    = 'Sincostan05.';
$nama_db = 'db_lmsmahasiswa';


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $nama_db);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    die('Koneksi database gagal. Pastikan MySQL menyala dan database lms_db sudah diimport.');
}