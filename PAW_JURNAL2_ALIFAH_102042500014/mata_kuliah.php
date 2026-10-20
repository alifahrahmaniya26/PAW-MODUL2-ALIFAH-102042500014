<?php
// mata_kuliah.php - CRUD mata kuliah (daftar, detail, tambah, edit, hapus)
require 'koneksi.php';
require 'helper.php';

// Validasi semua isian; mengembalikan array pesan error per kolom
function validasi($conn, $n, $idSendiri)
{
    $err = [];

    // Kode: hanya huruf kapital, angka, tanda -, panjang 3-20
    if (!preg_match('/^[A-Z0-9-]{3,20}$/D', $n['kode'])) {
        $err['kode'] = 'Kode 3-20 karakter: huruf kapital, angka, atau tanda -.';
    } else {
        // Kode harus unik (kecuali milik data yang sedang diedit)
        $stmt = $conn->prepare('SELECT id FROM mata_kuliah WHERE kode = ? AND id <> ?');
        $stmt->bind_param('si', $n['kode'], $idSendiri);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $err['kode'] = 'Kode mata kuliah sudah digunakan.';
        }
    }

    $len = mb_strlen($n['nama']);
    if ($len < 3 || $len > 100) {
        $err['nama'] = 'Nama harus 3-100 karakter.';
    }

    $len = mb_strlen($n['dosen']);
    if ($len < 3 || $len > 100) {
        $err['dosen'] = 'Dosen harus 3-100 karakter.';
    }

    // Semester: bilangan bulat 1-14
    $sem = filter_var($n['semester'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 14]]);
    if ($sem === false) {
        $err['semester'] = 'Semester harus bilangan bulat 1-14.';
    }

    return $err;
}

$aksi = $_GET['aksi'] ?? 'daftar';
if (!in_array($aksi, ['daftar', 'detail', 'tambah', 'edit', 'hapus'], true)) {
    $aksi = 'daftar';
}

// Ambil data lama untuk detail/edit/hapus (ID diperiksa dulu sebelum query)
$data = null;
if (in_array($aksi, ['detail', 'edit', 'hapus'], true)) {
    $id = valid_id($_GET['id'] ?? null);
    if ($id === null) {
        set_flash('error', 'ID tidak valid.');
        redirect('mata_kuliah.php');
    }
    $stmt = $conn->prepare('SELECT id, kode, nama, dosen, semester FROM mata_kuliah WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    if (!$data) {
        set_flash('error', 'Data mata kuliah tidak ditemukan.');
        redirect('mata_kuliah.php');
    }
}

$errors = [];
$nilai = ['kode' => '', 'nama' => '', 'dosen' => '', 'semester' => ''];
if ($aksi === 'edit') {
    $nilai = $data;
}
$pesanHapus = '';

// ---------- Penerima form (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($aksi, ['tambah', 'edit', 'hapus'], true)) {
    // Token harus cocok, kalau tidak permintaan ditolak
    if (!token_valid()) {
        set_flash('error', 'Token tidak valid. Permintaan ditolak.');
        redirect('mata_kuliah.php');
    }

    if ($aksi === 'hapus') {
        // ID di form harus sama dengan ID di URL
        if (valid_id($_POST['id'] ?? null) !== (int)$data['id']) {
            set_flash('error', 'ID tidak sesuai. Data tidak dihapus.');
            redirect('mata_kuliah.php');
        }
        try {
            $stmt = $conn->prepare('DELETE FROM mata_kuliah WHERE id = ?');
            $stmt->bind_param('i', $data['id']);
            $stmt->execute();
            set_flash('success', 'Mata kuliah berhasil dihapus.');
            redirect('mata_kuliah.php');
        } catch (mysqli_sql_exception $ex) {
            // 1451 = masih dipakai foreign key (ON DELETE RESTRICT)
            if ((int)$ex->getCode() === 1451) {
                $pesanHapus = 'Data belum terhapus. Mata kuliah masih memiliki materi atau tugas. Hapus data terkait terlebih dahulu.';
            } else {
                $pesanHapus = 'Data belum terhapus karena terjadi kesalahan database.';
            }
        }
    } else {
        $nilai = [
            'kode'     => trim($_POST['kode'] ?? ''),
            'nama'     => trim($_POST['nama'] ?? ''),
            'dosen'    => trim($_POST['dosen'] ?? ''),
            'semester' => trim($_POST['semester'] ?? ''),
        ];
        $errors = validasi($conn, $nilai, $aksi === 'edit' ? (int)$data['id'] : 0);

        if (!$errors) {
            $sem = (int)$nilai['semester'];
            if ($aksi === 'tambah') {
                try {
                    $stmt = $conn->prepare('INSERT INTO mata_kuliah (kode, nama, dosen, semester) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('sssi', $nilai['kode'], $nilai['nama'], $nilai['dosen'], $sem);
                    $stmt->execute();
                    set_flash('success', 'Mata kuliah berhasil ditambahkan.');
                    redirect('mata_kuliah.php');
                } catch (mysqli_sql_exception $ex) {
                    // 1062 = kode duplikat (jaga-jaga jika dua permintaan bersamaan)
                    $errors['kode'] = ((int)$ex->getCode() === 1062)
                        ? 'Kode mata kuliah sudah digunakan.'
                        : 'Data gagal disimpan.';
                }
            } else {
                // Edit: cek apakah ada perubahan
                $sama = $data['kode'] === $nilai['kode'] && $data['nama'] === $nilai['nama']
                    && $data['dosen'] === $nilai['dosen'] && (int)$data['semester'] === $sem;
                if ($sama) {
                    set_flash('info', 'Tidak ada perubahan data.');
                    redirect('mata_kuliah.php');
                }
                try {
                    $stmt = $conn->prepare('UPDATE mata_kuliah SET kode = ?, nama = ?, dosen = ?, semester = ? WHERE id = ?');
                    $stmt->bind_param('sssii', $nilai['kode'], $nilai['nama'], $nilai['dosen'], $sem, $data['id']);
                    $stmt->execute();
                    set_flash('success', 'Mata kuliah berhasil diubah.');
                    redirect('mata_kuliah.php');
                } catch (mysqli_sql_exception $ex) {
                    $errors['kode'] = ((int)$ex->getCode() === 1062)
                        ? 'Kode mata kuliah sudah digunakan.'
                        : 'Data gagal disimpan.';
                }
            }
        }
    }
}

// ---------- Tampilan ----------
page_header('Mata kuliah');

if ($aksi === 'daftar') {
    // Pencarian dari kode atau nama
    $q = trim($_GET['q'] ?? '');
    $sql = 'SELECT id, kode, nama, dosen, semester FROM mata_kuliah';
    if ($q !== '') {
        $sql .= ' WHERE kode LIKE ? OR nama LIKE ?';
    }
    $sql .= ' ORDER BY nama';
    $stmt = $conn->prepare($sql);
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $stmt->bind_param('ss', $like, $like);
    }
    $stmt->execute();
    $hasil = $stmt->get_result();
    ?>
    <h1>Mata kuliah</h1>
    <div class="baris-atas">
        <span>Menampilkan <?= $hasil->num_rows ?> data.</span>
        <a class="btn" href="mata_kuliah.php?aksi=tambah">Tambah mata kuliah</a>
    </div>
    <form class="cari" method="get" action="mata_kuliah.php">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari kode atau nama">
        <button class="btn">Cari</button>
        <a class="btn btn-light" href="mata_kuliah.php">Reset</a>
    </form>
    <?php if ($hasil->num_rows === 0): ?>
        <div class="kosong">Data tidak ditemukan.</div>
    <?php else: ?>
        <table>
            <tr><th>Mata kuliah</th><th>Dosen / semester</th><th>Aksi</th></tr>
            <?php while ($r = $hasil->fetch_assoc()): ?>
                <tr>
                    <td><?= e($r['kode']) ?> - <?= e($r['nama']) ?></td>
                    <td><?= e($r['dosen']) ?> / Semester <?= (int)$r['semester'] ?></td>
                    <td class="aksi">
                        <a class="btn btn-light btn-kecil" href="mata_kuliah.php?aksi=detail&amp;id=<?= (int)$r['id'] ?>">Detail</a>
                        <a class="btn btn-light btn-kecil" href="mata_kuliah.php?aksi=edit&amp;id=<?= (int)$r['id'] ?>">Edit</a>
                        <a class="btn btn-danger btn-kecil" href="mata_kuliah.php?aksi=hapus&amp;id=<?= (int)$r['id'] ?>">Hapus</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif;

} elseif ($aksi === 'detail') {
    // Materi dan tugas milik mata kuliah ini
    $stmt = $conn->prepare('SELECT id, judul FROM materi WHERE mata_kuliah_id = ? ORDER BY judul');
    $stmt->bind_param('i', $data['id']);
    $stmt->execute();
    $daftarMateri = $stmt->get_result();

    $stmt = $conn->prepare('SELECT id, judul FROM tugas WHERE mata_kuliah_id = ? ORDER BY judul');
    $stmt->bind_param('i', $data['id']);
    $stmt->execute();
    $daftarTugas = $stmt->get_result();
    ?>
    <h1>Detail mata kuliah</h1>
    <dl>
        <dt>Kode</dt><dd><?= e($data['kode']) ?></dd>
        <dt>Nama</dt><dd><?= e($data['nama']) ?></dd>
        <dt>Dosen</dt><dd><?= e($data['dosen']) ?></dd>
        <dt>Semester</dt><dd><?= (int)$data['semester'] ?></dd>
    </dl>
    <h3>Materi</h3>
    <?php if ($daftarMateri->num_rows === 0): ?>
        <p>Belum ada materi.</p>
    <?php else: ?>
        <ul>
            <?php while ($m = $daftarMateri->fetch_assoc()): ?>
                <li><a href="materi.php?aksi=detail&amp;id=<?= (int)$m['id'] ?>"><?= e($m['judul']) ?></a></li>
            <?php endwhile; ?>
        </ul>
    <?php endif; ?>
    <h3>Tugas</h3>
    <?php if ($daftarTugas->num_rows === 0): ?>
        <p>Belum ada tugas.</p>
    <?php else: ?>
        <ul>
            <?php while ($t = $daftarTugas->fetch_assoc()): ?>
                <li><a href="tugas.php?aksi=detail&amp;id=<?= (int)$t['id'] ?>"><?= e($t['judul']) ?></a></li>
            <?php endwhile; ?>
        </ul>
    <?php endif; ?>
    <p>
        <a class="btn" href="mata_kuliah.php?aksi=edit&amp;id=<?= (int)$data['id'] ?>">Edit data</a>
        <a class="btn btn-light" href="mata_kuliah.php">Kembali</a>
    </p>
    <?php

} elseif ($aksi === 'hapus') {
    ?>
    <h1>Mata kuliah</h1>
    <?php if ($pesanHapus !== ''): ?>
        <div class="alert error"><?= e($pesanHapus) ?></div>
    <?php endif; ?>
    <p>Hapus <strong><?= e($data['nama']) ?></strong>?</p>
    <p>Data baru dihapus setelah tombol konfirmasi dikirim.</p>
    <form method="post" action="mata_kuliah.php?aksi=hapus&amp;id=<?= (int)$data['id'] ?>">
        <input type="hidden" name="token" value="<?= e(token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">
        <button class="btn btn-danger">Ya, hapus data</button>
        <a class="btn btn-light" href="mata_kuliah.php">Batal</a>
    </form>
    <?php

} else {
    // Form tambah / edit
    $action = ($aksi === 'edit')
        ? 'mata_kuliah.php?aksi=edit&id=' . (int)$data['id']
        : 'mata_kuliah.php?aksi=tambah';
    ?>
    <h1><?= $aksi === 'edit' ? 'Edit' : 'Tambah' ?> mata kuliah</h1>
    <form method="post" action="<?= e($action) ?>">
        <input type="hidden" name="token" value="<?= e(token()) ?>">
        <div class="form-grid">
            <div>
                <label for="kode">Kode mata kuliah</label>
                <input type="text" id="kode" name="kode" maxlength="20" value="<?= e($nilai['kode']) ?>">
                <?= galat($errors, 'kode') ?>
            </div>
            <div>
                <label for="nama">Nama mata kuliah</label>
                <input type="text" id="nama" name="nama" maxlength="100" value="<?= e($nilai['nama']) ?>">
                <?= galat($errors, 'nama') ?>
            </div>
            <div>
                <label for="dosen">Dosen pengampu</label>
                <input type="text" id="dosen" name="dosen" maxlength="100" value="<?= e($nilai['dosen']) ?>">
                <?= galat($errors, 'dosen') ?>
            </div>
            <div>
                <label for="semester">Semester</label>
                <input type="text" id="semester" name="semester" value="<?= e($nilai['semester']) ?>">
                <?= galat($errors, 'semester') ?>
            </div>
        </div>
        <p>
            <button class="btn">Simpan data</button>
            <a class="btn btn-light" href="mata_kuliah.php">Batal</a>
        </p>
    </form>
    <?php
}

page_footer();