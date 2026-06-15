-- ==========================================
-- Hapus seluruh data siswa kelas 12 + relasi terkait
-- BUKUKITA — aman untuk foreign key (urutan: returns → borrowings → students)
-- Format kelas di project: 12.JURUSAN.n (contoh: 12.TO.1)
-- ==========================================
-- Gunakan JOIN (kompatibel MySQL) agar tidak bermasalah dengan subquery DELETE.

START TRANSACTION;

-- 1. Pengembalian yang terkait peminjaman siswa kelas 12
DELETE r FROM returns r
INNER JOIN students s ON r.student_id = s.id
WHERE s.class LIKE '12.%';

-- 2. Peminjaman siswa kelas 12
DELETE b FROM borrowings b
INNER JOIN students s ON b.student_id = s.id
WHERE s.class LIKE '12.%';

-- 3. Siswa kelas 12 (semua jurusan)
DELETE FROM students
WHERE class LIKE '12.%';

COMMIT;

-- Verifikasi (jalankan terpisah):
-- SELECT DISTINCT LEFT(class, 2) AS tingkat, COUNT(*) FROM students GROUP BY tingkat;
-- SELECT COUNT(*) FROM students WHERE class LIKE '12.%';  -- harus 0
