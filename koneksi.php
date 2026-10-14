
<?php
$conn = mysqli_connect('127.0.0.1', 'root', 'Sincostan05.', 'db_mobil', 3306);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

echo "Koneksi ke database berhasil!";
?>
