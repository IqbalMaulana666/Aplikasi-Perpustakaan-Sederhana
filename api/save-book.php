<?php
/**
 * JSON API: add, edit, delete books (including cover upload).
 * 
 * FIXES:
 *   Bug #1 — Status buku kini diperbarui otomatis saat stok diedit ke 0.
 *   Bug #2 — Logika hapus buku diperbaiki: hanya cek active_borrows > 0.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_check_api.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Ukuran data melebihi batas server (post_max_size / upload_max_filesize di php.ini).',
    ]);
    exit;
}

$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';

if ($action === 'add') {
    $title    = sanitize($_POST['title'] ?? '');
    $author   = sanitize($_POST['author'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $stock    = (int) ($_POST['stock'] ?? 0);

    if (empty($title) || empty($author) || empty($category) || $stock < 1) {
        echo json_encode(['success' => false, 'message' => 'Data buku tidak lengkap atau tidak valid']);
        exit;
    }

    $filename = null;

    if (bookCoverUploadPresent($_FILES['cover'] ?? [])) {
        $coverValidation = validateBookCoverUpload($_FILES['cover']);
        if ($coverValidation !== null) {
            echo json_encode(['success' => false, 'message' => $coverValidation]);
            exit;
        }

        $filename = storeBookCoverUpload($_FILES['cover']);
        if (!$filename) {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan gambar cover ke uploads/covers/.']);
            exit;
        }
    } elseif (isset($_FILES['cover']) && (int) $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => bookCoverUploadErrorMessage((int) $_FILES['cover']['error'])]);
        exit;
    }

    $insert_query = "INSERT INTO books (title, author, category, cover, stock, status, created_at) VALUES (?, ?, ?, ?, ?, 'available', NOW())";
    $insert_stmt  = $conn->prepare($insert_query);

    if (!$insert_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $coverForDb = (string) ($filename ?? '');
    $insert_stmt->bind_param('ssssi', $title, $author, $category, $coverForDb, $stock);

    if ($insert_stmt->execute()) {
        logActivity('tambah_buku', "Buku: $title oleh $author");
        echo json_encode(['success' => true, 'message' => 'Buku berhasil ditambahkan']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan buku: ' . $conn->error]);
    }
    $insert_stmt->close();
    exit;
}

if ($action === 'edit') {
    $id       = (int) ($_POST['id'] ?? 0);
    $title    = sanitize($_POST['title'] ?? '');
    $author   = sanitize($_POST['author'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $stock    = (int) ($_POST['stock'] ?? 0);

    if ($id < 1 || empty($title) || empty($author) || empty($category) || $stock < 0) {
        echo json_encode(['success' => false, 'message' => 'Data buku tidak lengkap atau tidak valid']);
        exit;
    }

    $book = getBookByID($id);

    if (!$book) {
        echo json_encode(['success' => false, 'message' => 'Buku tidak ditemukan']);
        exit;
    }

    $filename = (string) ($book['cover'] ?? '');

    if (bookCoverUploadPresent($_FILES['cover'] ?? [])) {
        $coverValidation = validateBookCoverUpload($_FILES['cover']);
        if ($coverValidation !== null) {
            echo json_encode(['success' => false, 'message' => $coverValidation]);
            exit;
        }

        $new_filename = storeBookCoverUpload($_FILES['cover']);

        if (!$new_filename) {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan gambar cover ke uploads/covers/.']);
            exit;
        }

        deleteBookCoverFile($filename);
        $filename = $new_filename;
        deleteBookCoverCache($id);
    } elseif (isset($_FILES['cover']) && (int) $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => bookCoverUploadErrorMessage((int) $_FILES['cover']['error'])]);
        exit;
    }

    // BUG #1 FIX: Status diperbarui otomatis berdasarkan stok.
    // Jika stok = 0 → 'borrowed' (habis), jika stok > 0 → 'available'.
    $newStatus = ($stock > 0) ? 'available' : 'borrowed';

    $update_query = 'UPDATE books SET title=?, author=?, category=?, cover=?, stock=?, status=? WHERE id=?';
    $update_stmt  = $conn->prepare($update_query);

    if (!$update_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $coverForDb = $filename;
    $update_stmt->bind_param('ssssisi', $title, $author, $category, $coverForDb, $stock, $newStatus, $id);

    if ($update_stmt->execute()) {
        logActivity('edit_buku', "Buku ID: $id - $title");
        echo json_encode(['success' => true, 'message' => 'Buku berhasil diperbarui']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui buku: ' . $conn->error]);
    }
    $update_stmt->close();
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id < 1) {
        echo json_encode(['success' => false, 'message' => 'ID buku tidak valid']);
        exit;
    }

    $book = getBookByID($id);

    if (!$book) {
        echo json_encode(['success' => false, 'message' => 'Buku tidak ditemukan']);
        exit;
    }

    // BUG #2 FIX: Hanya cek peminjaman aktif untuk keamanan relasional.
    // Kondisi sebelumnya ($book['stock'] < 1 || $active_borrows > 0) keliru
    // karena stok tidak relevan — yang penting tidak ada yang sedang meminjam.
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM borrowings WHERE book_id = ? AND status = 'active'");
    if (!$check_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $check_stmt->bind_param('i', $id);
    $check_stmt->execute();
    $active_borrows = $check_stmt->get_result()->fetch_row()[0];
    $check_stmt->close();

    if ($active_borrows > 0) {
        echo json_encode(['success' => false, 'message' => 'Tidak bisa menghapus buku yang sedang dipinjam']);
        exit;
    }

    deleteBookCoverFile((string) ($book['cover'] ?? ''));

    $delete_query = 'DELETE FROM books WHERE id = ?';
    $delete_stmt  = $conn->prepare($delete_query);

    if (!$delete_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $delete_stmt->bind_param('i', $id);

    if ($delete_stmt->execute()) {
        deleteBookCoverCache($id);
        logActivity('hapus_buku', "Buku ID: $id");
        echo json_encode(['success' => true, 'message' => 'Buku berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus buku: ' . $conn->error]);
    }
    $delete_stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak valid atau data tidak lengkap.']);
exit;
