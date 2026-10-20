<?php
// materi.php - CRUD materi (daftar+cari+filter, detail, tambah, edit, hapus)
require 'koneksi.php';
require 'helper.php';

// Validasi semua isian materi
function validasi($conn, $n)
{
    $err = [];

    // Mata kuliah harus dipilih dan benar-benar ada di database
    $mk = valid_id($n['mata_kuliah_id']);
    if ($mk === null) {
        $err['mata_kuliah_id'] = 'Pilih mata kuliah.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM mata_kuliah WHERE id = ?');
        $stmt->bind_param('i', $mk);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $err['mata_kuliah_id'] = 'Mata kuliah tidak ditemukan.';
        }
    }

    $len = mb_strlen($n['judul']);
    if ($len < 3 || $len > 150) {
        $err['judul'] = 'Judul harus 3-150 karakter.';
    }

    $len = mb_strlen($n['isi']);
    if ($len < 3 || $len > 2000) {
        $err['isi'] = 'Isi harus 3-2000 karakter.';
    }

    // Tautan: maks 255 karakter, harus diawali http:// atau https://
    // (menolak javascript: dan skema berbahaya lainnya)
    if (mb_strlen($n['tautan']) > 255
        || !preg_match('#^https?://#i', $n['tautan'])
        || filter_var($n['tautan'], FILTER_VALIDATE_URL) === false) {
        $err['tautan'] = 'Gunakan tautan http/https yang valid (maks 255 karakter).';
    }

    return $err;
}

$aksi = $_GET['aksi'] ?? 'daftar';
if (!in_array($aksi, ['daftar', 'detail', 'tambah', 'edit', 'hapus'], true)) {
    $aksi = 'daftar';
}

// Ambil data lama (dengan nama mata kuliah) untuk detail/edit/hapus
$data = null;
if (in_array($aksi, ['detail', 'edit', 'hapus'], true)) {
    $id = valid_id($_GET['id'] ?? null);
    if ($id === null) {
        set_flash('error', 'ID tidak valid.');
        redirect('materi.php');
    }
    $stmt = $conn->prepare(
        'SELECT m.id, m.mata_kuliah_id, m.judul, m.isi, m.tautan, mk.kode, mk.nama AS nama_mk
         FROM materi m JOIN mata_kuliah mk ON mk.id = m.mata_kuliah_id
         WHERE m.id = ?'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    if (!$data) {
        set_flash('error', 'Data materi tidak ditemukan.');
        redirect('materi.php');
    }
}

$errors = [];
$nilai = ['mata_kuliah_id' => '', 'judul' => '', 'isi' => '', 'tautan' => ''];
if ($aksi === 'edit') {
    $nilai = [
        'mata_kuliah_id' => $data['mata_kuliah_id'],
        'judul'          => $data['judul'],
        'isi'            => $data['isi'],
        'tautan'         => $data['tautan'],
    ];
}
$pesanHapus = '';

// ---------- Penerima form (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($aksi, ['tambah', 'edit', 'hapus'], true)) {
    if (!token_valid()) {
        set_flash('error', 'Token tidak valid. Permintaan ditolak.');
        redirect('materi.php');
    }

    if ($aksi === 'hapus') {
        if (valid_id($_POST['id'] ?? null) !== (int)$data['id']) {
            set_flash('error', 'ID tidak sesuai. Data tidak dihapus.');
            redirect('materi.php');
        }
        try {
            $stmt = $conn->prepare('DELETE FROM materi WHERE id = ?');
            $stmt->bind_param('i', $data['id']);
            $stmt->execute();
            set_flash('success', 'Materi berhasil dihapus.');
            redirect('materi.php');
        } catch (mysqli_sql_exception $ex) {
            $pesanHapus = 'Data belum terhapus karena terjadi kesalahan database.';
        }
    } else {
        $nilai = [
            'mata_kuliah_id' => trim($_POST['mata_kuliah_id'] ?? ''),
            'judul'          => trim($_POST['judul'] ?? ''),
            'isi'            => trim($_POST['isi'] ?? ''),
            'tautan'         => trim($_POST['tautan'] ?? ''),
        ];
        $errors = validasi($conn, $nilai);

        if (!$errors) {
            $mk = (int)$nilai['mata_kuliah_id'];
            try {
                if ($aksi === 'tambah') {
                    $stmt = $conn->prepare('INSERT INTO materi (mata_kuliah_id, judul, isi, tautan) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('isss', $mk, $nilai['judul'], $nilai['isi'], $nilai['tautan']);
                    $stmt->execute();
                    set_flash('success', 'Materi berhasil ditambahkan.');
                    redirect('materi.php');
                } else {
                    $sama = (int)$data['mata_kuliah_id'] === $mk && $data['judul'] === $nilai['judul']
                        && $data['isi'] === $nilai['isi'] && $data['tautan'] === $nilai['tautan'];
                    if ($sama) {
                        set_flash('info', 'Tidak ada perubahan data.');
                        redirect('materi.php');
                    }
                    $stmt = $conn->prepare('UPDATE materi SET mata_kuliah_id = ?, judul = ?, isi = ?, tautan = ? WHERE id = ?');
                    $stmt->bind_param('isssi', $mk, $nilai['judul'], $nilai['isi'], $nilai['tautan'], $data['id']);
                    $stmt->execute();
                    set_flash('success', 'Materi berhasil diubah.');
                    redirect('materi.php');
                }
            } catch (mysqli_sql_exception $ex) {
                $errors['judul'] = 'Data gagal disimpan.';
            }
        }
    }
}

// ---------- Tampilan ----------
page_header('Materi');

if ($aksi === 'daftar') {
    // Pencarian judul + filter mata kuliah dipakai bersamaan
    $q = trim($_GET['q'] ?? '');
    $filter = valid_id($_GET['mk'] ?? null);

    $sql = 'SELECT m.id, m.judul, mk.kode, mk.nama AS nama_mk
            FROM materi m JOIN mata_kuliah mk ON mk.id = m.mata_kuliah_id';
    $where = [];
    $tipe = '';
    $param = [];
    if ($q !== '') {
        $where[] = 'm.judul LIKE ?';
        $tipe .= 's';
        $param[] = '%' . addcslashes($q, '%_\\') . '%';
    }
    if ($filter !== null) {
        $where[] = 'm.mata_kuliah_id = ?';
        $tipe .= 'i';
        $param[] = $filter;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY m.judul';

    $stmt = $conn->prepare($sql);
    if ($param) {
        $stmt->bind_param($tipe, ...$param);
    }
    $stmt->execute();
    $hasil = $stmt->get_result();
    ?>
    <h1>Materi</h1>
    <div class="baris-atas">
        <span>Menampilkan <?= $hasil->num_rows ?> data.</span>
        <a class="btn" href="materi.php?aksi=tambah">Tambah materi</a>
    </div>
    <form class="cari" method="get" action="materi.php">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari judul materi">
        <select name="mk"><?= opsi_mata_kuliah($conn, $filter, 'Semua mata kuliah') ?></select>
        <button class="btn">Cari</button>
        <a class="btn btn-light" href="materi.php">Reset</a>
    </form>
    <?php if ($hasil->num_rows === 0): ?>
        <div class="kosong">Data tidak ditemukan.</div>
    <?php else: ?>
        <table>
            <tr><th>Judul</th><th>Mata kuliah</th><th>Aksi</th></tr>
            <?php while ($r = $hasil->fetch_assoc()): ?>
                <tr>
                    <td><?= e($r['judul']) ?></td>
                    <td><?= e($r['kode']) ?> - <?= e($r['nama_mk']) ?></td>
                    <td class="aksi">
                        <a class="btn btn-light btn-kecil" href="materi.php?aksi=detail&amp;id=<?= (int)$r['id'] ?>">Detail</a>
                        <a class="btn btn-light btn-kecil" href="materi.php?aksi=edit&amp;id=<?= (int)$r['id'] ?>">Edit</a>
                        <a class="btn btn-danger btn-kecil" href="materi.php?aksi=hapus&amp;id=<?= (int)$r['id'] ?>">Hapus</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif;

} elseif ($aksi === 'detail') {
    ?>
    <h1>Detail materi</h1>
    <dl>
        <dt>Mata kuliah</dt><dd><?= e($data['kode']) ?> - <?= e($data['nama_mk']) ?></dd>
        <dt>Judul materi</dt><dd><?= e($data['judul']) ?></dd>
        <dt>Isi materi</dt><dd><?= nl2br(e($data['isi'])) ?></dd>
        <dt>Tautan referensi</dt>
        <dd><a href="<?= e($data['tautan']) ?>" target="_blank" rel="noopener noreferrer"><?= e($data['tautan']) ?></a></dd>
    </dl>
    <p>
        <a class="btn" href="materi.php?aksi=edit&amp;id=<?= (int)$data['id'] ?>">Edit data</a>
        <a class="btn btn-light" href="materi.php">Kembali</a>
    </p>
    <?php

} elseif ($aksi === 'hapus') {
    ?>
    <h1>Materi</h1>
    <?php if ($pesanHapus !== ''): ?>
        <div class="alert error"><?= e($pesanHapus) ?></div>
    <?php endif; ?>
    <p>Hapus <strong><?= e($data['judul']) ?></strong>?</p>
    <p>Data baru dihapus setelah tombol konfirmasi dikirim.</p>
    <form method="post" action="materi.php?aksi=hapus&amp;id=<?= (int)$data['id'] ?>">
        <input type="hidden" name="token" value="<?= e(token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">
        <button class="btn btn-danger">Ya, hapus data</button>
        <a class="btn btn-light" href="materi.php">Batal</a>
    </form>
    <?php

} else {
    $action = ($aksi === 'edit')
        ? 'materi.php?aksi=edit&id=' . (int)$data['id']
        : 'materi.php?aksi=tambah';
    ?>
    <h1><?= $aksi === 'edit' ? 'Edit' : 'Tambah' ?> materi</h1>
    <form method="post" action="<?= e($action) ?>">
        <input type="hidden" name="token" value="<?= e(token()) ?>">
        <div class="form-grid">
            <div>
                <label for="mata_kuliah_id">Mata kuliah</label>
                <select id="mata_kuliah_id" name="mata_kuliah_id"><?= opsi_mata_kuliah($conn, $nilai['mata_kuliah_id']) ?></select>
                <?= galat($errors, 'mata_kuliah_id') ?>
            </div>
            <div>
                <label for="judul">Judul materi</label>
                <input type="text" id="judul" name="judul" maxlength="150" value="<?= e($nilai['judul']) ?>">
                <?= galat($errors, 'judul') ?>
            </div>
            <div class="penuh">
                <label for="isi">Isi materi</label>
                <textarea id="isi" name="isi"><?= e($nilai['isi']) ?></textarea>
                <?= galat($errors, 'isi') ?>
            </div>
            <div class="penuh">
                <label for="tautan">Tautan referensi</label>
                <input type="text" id="tautan" name="tautan" maxlength="255" value="<?= e($nilai['tautan']) ?>">
                <?= galat($errors, 'tautan') ?>
            </div>
        </div>
        <p>
            <button class="btn">Simpan data</button>
            <a class="btn btn-light" href="materi.php">Batal</a>
        </p>
    </form>
    <?php
}

page_footer();