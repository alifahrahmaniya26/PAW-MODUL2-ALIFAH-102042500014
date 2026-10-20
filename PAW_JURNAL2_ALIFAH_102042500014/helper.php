<?php
// helper.php - fungsi bantu yang dipakai semua halaman
session_start();

// Escape teks sebelum ditampilkan agar HTML/JS dari pengguna tidak dijalankan (XSS)
function e($teks)
{
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

// ID hanya diterima jika berupa angka bulat positif; selain itu null
function valid_id($nilai)
{
    if (is_string($nilai) && ctype_digit($nilai) && (int)$nilai > 0) {
        return (int)$nilai;
    }
    return null;
}

// Token CSRF disimpan di session dan dikirim lewat input hidden
function token()
{
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token'];
}

// Token dari form harus sama dengan token di session
function token_valid()
{
    return isset($_POST['token'], $_SESSION['token'])
        && is_string($_POST['token'])
        && hash_equals($_SESSION['token'], $_POST['token']);
}

// Pesan sekali tampil (setelah redirect)
function set_flash($tipe, $pesan)
{
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

// Pesan error di bawah input
function galat($errors, $kolom)
{
    return isset($errors[$kolom]) ? '<div class="galat">' . e($errors[$kolom]) . '</div>' : '';
}

// <option> mata kuliah untuk dropdown form dan filter
function opsi_mata_kuliah($conn, $dipilih = null, $labelAwal = 'Pilih Mata kuliah')
{
    $html = '<option value="">' . e($labelAwal) . '</option>';
    $hasil = $conn->query('SELECT id, kode, nama FROM mata_kuliah ORDER BY nama');
    while ($r = $hasil->fetch_assoc()) {
        $sel = ((string)$dipilih === (string)$r['id']) ? ' selected' : '';
        $html .= '<option value="' . (int)$r['id'] . '"' . $sel . '>'
              . e($r['kode'] . ' - ' . $r['nama']) . '</option>';
    }
    return $html;
}

function page_header($judul)
{
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> - LMS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wadah">
    <nav>
        <a href="index.php">Beranda</a>
        <a href="mata_kuliah.php">Mata kuliah</a>
        <a href="materi.php">Materi</a>
        <a href="tugas.php">Tugas</a>
    </nav>
    <?php
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert ' . e($f['tipe']) . '">' . e($f['pesan']) . '</div>';
    }
}

function page_footer()
{
    echo '</div></body></html>';
}