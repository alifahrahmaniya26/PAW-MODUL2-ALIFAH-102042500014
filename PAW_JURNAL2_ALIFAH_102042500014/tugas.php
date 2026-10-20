<?php
// tugas.php - CRUD tugas (daftar+cari+filter, detail, tambah, edit, hapus)
require 'koneksi.php';
require 'helper.php';

// Status hanya boleh salah satu dari daftar tetap ini
$daftarStatus = ['Draf', 'Aktif', 'Selesai'];

// Validasi semua isian tugas
function validasi($conn, $n, $daftarStatus)
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

    $len = mb_strlen($n['instruksi']);
    if ($len < 3 || $len > 2000) {
        $err['instruksi'] = 'Instruksi harus 3-2000 karakter.';
    }

    // Tenggat: format Y-m-d dan tanggalnya harus ada di kalender (mis. 2026-02-30 ditolak)
    $tgl = DateTime::createFromFormat('Y-m-d', $n['tenggat']);
    if (!$tgl || $tgl->format('Y-m-d') !== $n['tenggat']) {
        $err['tenggat'] = 'Tanggal tenggat tidak valid.';
    }

    // Status hanya Draf / Aktif / Selesai
    if (!in_array($n['status'], $daftarStatus, true)) {
        $err['status'] = 'Status harus Draf, Aktif, atau Selesai.';
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
        redirect('tugas.php');
    }
    $stmt = $conn->prepare(
        'SELECT t.id, t.mata_kuliah_id, t.judul, t.instruksi, t.tenggat, t.status, mk.kode, mk.nama AS nama_mk
         FROM tugas t JOIN mata_kuliah mk ON mk.id = t.mata_kuliah_id
         WHERE t.id = ?'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    if (!$data) {
        set_flash('error', 'Data tugas tidak ditemukan.');
        redirect('tugas.php');
    }
}

$errors = [];
$nilai = ['mata_kuliah_id' => '', 'judul' => '', 'instruksi' => '', 'tenggat' => '', 'status' => 'Draf'];
if ($aksi === 'edit') {
    $nilai = [
        'mata_kuliah_id' => $data['mata_kuliah_id'],
        'judul'          => $data['judul'],
        'instruksi'      => $data['instruksi'],
        'tenggat'        => $data['tenggat'],
        'status'         => $data['status'],
    ];
}
$pesanHapus = '';

// ---------- Penerima form (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($aksi, ['tambah', 'edit', 'hapus'], true)) {
    if (!token_valid()) {
        set_flash('error', 'Token tidak valid. Permintaan ditolak.');
        redirect('tugas.php');
    }

    if ($aksi === 'hapus') {
        if (valid_id($_POST['id'] ?? null) !== (int)$data['id']) {
            set_flash('error', 'ID tidak sesuai. Data tidak dihapus.');
            redirect('tugas.php');
        }
        try {
            $stmt = $conn->prepare('DELETE FROM tugas WHERE id = ?');
            $stmt->bind_param('i', $data['id']);
            $stmt->execute();
            set_flash('success', 'Tugas berhasil dihapus.');
            redirect('tugas.php');
        } catch (mysqli_sql_exception $ex) {
            $pesanHapus = 'Data belum terhapus karena terjadi kesalahan database.';
        }
    } else {
        $nilai = [
            'mata_kuliah_id' => trim($_POST['mata_kuliah_id'] ?? ''),
            'judul'          => trim($_POST['judul'] ?? ''),
            'instruksi'      => trim($_POST['instruksi'] ?? ''),
            'tenggat'        => trim($_POST['tenggat'] ?? ''),
            'status'         => trim($_POST['status'] ?? ''),
        ];
        $errors = validasi($conn, $nilai, $daftarStatus);

        if (!$errors) {
            $mk = (int)$nilai['mata_kuliah_id'];
            try {
                if ($aksi === 'tambah') {
                    $stmt = $conn->prepare('INSERT INTO tugas (mata_kuliah_id, judul, instruksi, tenggat, status) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('issss', $mk, $nilai['judul'], $nilai['instruksi'], $nilai['tenggat'], $nilai['status']);
                    $stmt->execute();
                    set_flash('success', 'Tugas berhasil ditambahkan.');
                    redirect('tugas.php');
                } else {
                    $sama = (int)$data['mata_kuliah_id'] === $mk && $data['judul'] === $nilai['judul']
                        && $data['instruksi'] === $nilai['instruksi'] && $data['tenggat'] === $nilai['tenggat']
                        && $data['status'] === $nilai['status'];
                    if ($sama) {
                        set_flash('info', 'Tidak ada perubahan data.');
                        redirect('tugas.php');
                    }
                    $stmt = $conn->prepare('UPDATE tugas SET mata_kuliah_id = ?, judul = ?, instruksi = ?, tenggat = ?, status = ? WHERE id = ?');
                    $stmt->bind_param('issssi', $mk, $nilai['judul'], $nilai['instruksi'], $nilai['tenggat'], $nilai['status'], $data['id']);
                    $stmt->execute();
                    set_flash('success', 'Tugas berhasil diubah.');
                    redirect('tugas.php');
                }
            } catch (mysqli_sql_exception $ex) {
                $errors['judul'] = 'Data gagal disimpan.';
            }
        }
    }
}

// ---------- Tampilan ----------
page_header('Tugas');

if ($aksi === 'daftar') {
    // Pencarian judul + filter mata kuliah dipakai bersamaan
    $q = trim($_GET['q'] ?? '');
    $filter = valid_id($_GET['mk'] ?? null);

    $sql = 'SELECT t.id, t.judul, t.tenggat, t.status, mk.kode, mk.nama AS nama_mk
            FROM tugas t JOIN mata_kuliah mk ON mk.id = t.mata_kuliah_id';
    $where = [];
    $tipe = '';
    $param = [];
    if ($q !== '') {
        $where[] = 't.judul LIKE ?';
        $tipe .= 's';
        $param[] = '%' . addcslashes($q, '%_\\') . '%';
    }
    if ($filter !== null) {
        $where[] = 't.mata_kuliah_id = ?';
        $tipe .= 'i';
        $param[] = $filter;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY t.tenggat, t.judul';

    $stmt = $conn->prepare($sql);
    if ($param) {
        $stmt->bind_param($tipe, ...$param);
    }
    $stmt->execute();
    $hasil = $stmt->get_result();
    ?>
    <h1>Tugas</h1>
    <div class="baris-atas">
        <span>Menampilkan <?= $hasil->num_rows ?> data.</span>
        <a class="btn" href="tugas.php?aksi=tambah">Tambah tugas</a>
    </div>
    <form class="cari" method="get" action="tugas.php">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari judul tugas">
        <select name="mk"><?= opsi_mata_kuliah($conn, $filter, 'Semua mata kuliah') ?></select>
        <button class="btn">Cari</button>
        <a class="btn btn-light" href="tugas.php">Reset</a>
    </form>
    <?php if ($hasil->num_rows === 0): ?>
        <div class="kosong">Data tidak ditemukan.</div>
    <?php else: ?>
        <table>
            <tr><th>Judul</th><th>Mata kuliah</th><th>Tenggat</th><th>Status</th><th>Aksi</th></tr>
            <?php while ($r = $hasil->fetch_assoc()): ?>
                <tr>
                    <td><?= e($r['judul']) ?></td>
                    <td><?= e($r['kode']) ?> - <?= e($r['nama_mk']) ?></td>
                    <td><?= e($r['tenggat']) ?></td>
                    <td><?= e($r['status']) ?></td>
                    <td class="aksi">
                        <a class="btn btn-light btn-kecil" href="tugas.php?aksi=detail&amp;id=<?= (int)$r['id'] ?>">Detail</a>
                        <a class="btn btn-light btn-kecil" href="tugas.php?aksi=edit&amp;id=<?= (int)$r['id'] ?>">Edit</a>
                        <a class="btn btn-danger btn-kecil" href="tugas.php?aksi=hapus&amp;id=<?= (int)$r['id'] ?>">Hapus</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif;

} elseif ($aksi === 'detail') {
    ?>
    <h1>Detail tugas</h1>
    <dl>
        <dt>Mata kuliah</dt><dd><?= e($data['kode']) ?> - <?= e($data['nama_mk']) ?></dd>
        <dt>Judul tugas</dt><dd><?= e($data['judul']) ?></dd>
        <dt>Instruksi</dt><dd><?= nl2br(e($data['instruksi'])) ?></dd>
        <dt>Tenggat</dt><dd><?= e($data['tenggat']) ?></dd>
        <dt>Status</dt><dd><?= e($data['status']) ?></dd>
    </dl>
    <p>
        <a class="btn" href="tugas.php?aksi=edit&amp;id=<?= (int)$data['id'] ?>">Edit data</a>
        <a class="btn btn-light" href="tugas.php">Kembali</a>
    </p>
    <?php

} elseif ($aksi === 'hapus') {
    ?>
    <h1>Tugas</h1>
    <?php if ($pesanHapus !== ''): ?>
        <div class="alert error"><?= e($pesanHapus) ?></div>
    <?php endif; ?>
    <p>Hapus <strong><?= e($data['judul']) ?></strong>?</p>
    <p>Data baru dihapus setelah tombol konfirmasi dikirim.</p>
    <form method="post" action="tugas.php?aksi=hapus&amp;id=<?= (int)$data['id'] ?>">
        <input type="hidden" name="token" value="<?= e(token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">
        <button class="btn btn-danger">Ya, hapus data</button>
        <a class="btn btn-light" href="tugas.php">Batal</a>
    </form>
    <?php

} else {
    $action = ($aksi === 'edit')
        ? 'tugas.php?aksi=edit&id=' . (int)$data['id']
        : 'tugas.php?aksi=tambah';
    ?>
    <h1><?= $aksi === 'edit' ? 'Edit' : 'Tambah' ?> tugas</h1>
    <form method="post" action="<?= e($action) ?>">
        <input type="hidden" name="token" value="<?= e(token()) ?>">
        <div class="form-grid">
            <div>
                <label for="mata_kuliah_id">Mata kuliah</label>
                <select id="mata_kuliah_id" name="mata_kuliah_id"><?= opsi_mata_kuliah($conn, $nilai['mata_kuliah_id']) ?></select>
                <?= galat($errors, 'mata_kuliah_id') ?>
            </div>
            <div>
                <label for="judul">Judul tugas</label>
                <input type="text" id="judul" name="judul" maxlength="150" value="<?= e($nilai['judul']) ?>">
                <?= galat($errors, 'judul') ?>
            </div>
            <div class="penuh">
                <label for="instruksi">Instruksi tugas</label>
                <textarea id="instruksi" name="instruksi"><?= e($nilai['instruksi']) ?></textarea>
                <?= galat($errors, 'instruksi') ?>
            </div>
            <div>
                <label for="tenggat">Tenggat</label>
                <input type="date" id="tenggat" name="tenggat" value="<?= e($nilai['tenggat']) ?>">
                <?= galat($errors, 'tenggat') ?>
            </div>
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach ($daftarStatus as $s): ?>
                        <option value="<?= e($s) ?>"<?= $nilai['status'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= galat($errors, 'status') ?>
            </div>
        </div>
        <p>
            <button class="btn">Simpan data</button>
            <a class="btn btn-light" href="tugas.php">Batal</a>
        </p>
    </form>
    <?php
}

page_footer();