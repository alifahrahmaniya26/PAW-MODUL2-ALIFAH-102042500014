<?php
// index.php - beranda: ringkasan jumlah data dari database
require 'koneksi.php';
require 'helper.php';

// Nama tabel hanya dari daftar tetap ini, bukan dari input pengguna
$tabel = [
    'mata_kuliah' => 'Mata kuliah',
    'materi'      => 'Materi',
    'tugas'       => 'Tugas',
];

$jumlah = [];
foreach ($tabel as $nama => $label) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM $nama");
    $stmt->execute();
    $jumlah[$nama] = (int)$stmt->get_result()->fetch_assoc()['total'];
}

$stmt = $conn->prepare('SELECT kode, nama FROM mata_kuliah ORDER BY nama');
$stmt->execute();
$daftar = $stmt->get_result();

page_header('Beranda');
?>
<h1>Learning Management System</h1>
<p>Pengelolaan mata kuliah, materi pembelajaran, dan tugas.</p>

<div class="ringkas">
    <?php foreach ($tabel as $nama => $label): ?>
        <div><?= e($label) ?><strong><?= $jumlah[$nama] ?></strong></div>
    <?php endforeach; ?>
</div>

<p>
    <a class="btn" href="mata_kuliah.php">Kelola mata kuliah</a>
    <a class="btn" href="materi.php">Kelola materi</a>
    <a class="btn" href="tugas.php">Kelola tugas</a>
</p>

<h3>Mata kuliah tersedia</h3>
<?php if ($daftar->num_rows === 0): ?>
    <div class="kosong">Belum ada mata kuliah.</div>
<?php else: ?>
    <ul>
        <?php while ($r = $daftar->fetch_assoc()): ?>
            <li><?= e($r['kode']) ?> - <?= e($r['nama']) ?></li>
        <?php endwhile; ?>
    </ul>
<?php endif; ?>
<?php page_footer(); ?>