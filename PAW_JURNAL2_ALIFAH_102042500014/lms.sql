-- ============================================================
-- skema_lms.sql
-- Database LMS sederhana: mata_kuliah, materi, tugas
-- ============================================================

CREATE DATABASE IF NOT EXISTS lms_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lms_db;

-- Hapus tabel lama (anak dulu, baru induk) agar skrip bisa diimport ulang
DROP TABLE IF EXISTS tugas;
DROP TABLE IF EXISTS materi;
DROP TABLE IF EXISTS mata_kuliah;

-- ------------------------------------------------------------
-- 1. Tabel mata_kuliah (induk)
-- ------------------------------------------------------------
CREATE TABLE mata_kuliah (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode     VARCHAR(20)  NOT NULL UNIQUE,   -- 3-20 karakter, unik
  nama     VARCHAR(100) NOT NULL,          -- 3-100 karakter
  dosen    VARCHAR(100) NOT NULL,          -- 3-100 karakter
  semester TINYINT UNSIGNED NOT NULL,      -- 1-14
  CONSTRAINT chk_semester CHECK (semester BETWEEN 1 AND 14)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. Tabel materi (anak dari mata_kuliah)
-- ------------------------------------------------------------
CREATE TABLE materi (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mata_kuliah_id INT UNSIGNED NOT NULL,
  judul          VARCHAR(150) NOT NULL,    -- 3-150 karakter
  isi            TEXT         NOT NULL,    -- 3-2000 karakter (dicek di PHP)
  tautan         VARCHAR(255) NOT NULL,    -- maks 255, http:// atau https:// (dicek di PHP)
  CONSTRAINT fk_materi_mata_kuliah
    FOREIGN KEY (mata_kuliah_id)
    REFERENCES mata_kuliah(id)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. Tabel tugas (anak dari mata_kuliah)
-- ------------------------------------------------------------
CREATE TABLE tugas (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mata_kuliah_id INT UNSIGNED NOT NULL,
  judul          VARCHAR(150) NOT NULL,    -- 3-150 karakter
  instruksi      TEXT         NOT NULL,    -- 3-2000 karakter (dicek di PHP)
  tenggat        DATE         NOT NULL,    -- tanggal valid
  status         VARCHAR(20)  NOT NULL,    -- Draf / Aktif / Selesai
  CONSTRAINT chk_status CHECK (status IN ('Draf', 'Aktif', 'Selesai')),
  CONSTRAINT fk_tugas_mata_kuliah
    FOREIGN KEY (mata_kuliah_id)
    REFERENCES mata_kuliah(id)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Data awal (2 baris per tabel)
-- ------------------------------------------------------------
INSERT INTO mata_kuliah (kode, nama, dosen, semester) VALUES
  ('ALP-101', 'Algoritma dan Pemrograman',      'Dosen ALP', 1),
  ('PAW-101', 'Pengembangan Aplikasi Website',  'Dosen PAW', 3);

INSERT INTO materi (mata_kuliah_id, judul, isi, tautan) VALUES
  (1, 'Dasar Algoritma',
      'Materi membahas konsep algoritma, flowchart, dan pseudocode.',
      'https://www.w3schools.com/programming/'),
  (2, 'Dasar PHP',
      'Materi membahas sintaks dasar PHP, variabel, dan struktur kontrol.',
      'https://www.php.net/manual/en/langref.php');

INSERT INTO tugas (mata_kuliah_id, judul, instruksi, tenggat, status) VALUES
  (1, 'Latihan Flowchart',
      'Buat flowchart untuk program penghitung nilai rata-rata.',
      '2026-11-05', 'Aktif'),
  (2, 'Latihan CRUD',
      'Buat aplikasi CRUD sederhana dengan PHP dan MySQL.',
      '2026-11-12', 'Draf');