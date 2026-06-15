<?php
require_once __DIR__ . '/../config/database.php';

/** Absolute path to book cover uploads (project root/uploads/covers/). */
define('BOOK_COVERS_UPLOAD_DIR', __DIR__ . '/../uploads/covers/');

/** Web-relative path to covers from pages/ (for fallback/direct access if needed). */
define('BOOK_COVERS_WEB_PATH', 'uploads/covers/');

define('BOOK_COVER_ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'webp']);
define('BOOK_COVER_MAX_BYTES', 2 * 1024 * 1024);

/**
 * True when the request includes a real uploaded cover file (not an empty file input).
 */
function bookCoverUploadPresent(array $file): bool {
    return isset($file['error'])
        && (int) $file['error'] === UPLOAD_ERR_OK
        && !empty($file['tmp_name'])
        && is_uploaded_file($file['tmp_name'])
        && isset($file['size'])
        && (int) $file['size'] > 0;
}

/**
 * Validate cover upload; returns null if OK, otherwise an error message.
 */
function validateBookCoverUpload(array $file): ?string {
    if (!bookCoverUploadPresent($file)) {
        return null;
    }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, BOOK_COVER_ALLOWED_EXT, true)) {
        return 'Format gambar tidak valid (gunakan JPG, JPEG, PNG, atau WebP)';
    }

    if ((int) $file['size'] > BOOK_COVER_MAX_BYTES) {
        return 'File terlalu besar (maksimal 2MB)';
    }

    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }
    if ($mime === null && function_exists('mime_content_type')) {
        $mime = @mime_content_type($file['tmp_name']);
    }

    $allowedMimes = [
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png', 'image/x-png'],
        'webp' => ['image/webp'],
    ];
    if ($mime !== null && $mime !== false && $mime !== 'application/octet-stream') {
        $ok = in_array($mime, $allowedMimes[$ext] ?? [], true);
        if (!$ok) {
            return 'Tipe file tidak sesuai ekstensi. Gunakan JPG, JPEG, PNG, atau WebP';
        }
    }

    return null;
}

/** Human-readable message for PHP upload error codes. */
function bookCoverUploadErrorMessage(int $uploadError): string {
    switch ($uploadError) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File terlalu besar (max 2MB)';
        case UPLOAD_ERR_PARTIAL:
            return 'Upload terpotong, coba lagi';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            return 'Folder upload tidak dapat ditulis. Periksa izin folder covers.';
        default:
            return 'Gagal upload file';
    }
}

/** Safe absolute path to a cover file on disk. */
function bookCoverDiskPath(string $filename): string {
    return BOOK_COVERS_UPLOAD_DIR . basename($filename);
}

/** Web URL to a cover file (filename only stored in DB). */
function bookCoverWebUrl(string $filename, string $prefix = '../'): string {
    return rtrim($prefix, '/') . '/' . BOOK_COVERS_WEB_PATH . rawurlencode(basename($filename));
}

/**
 * Store uploaded cover via move_uploaded_file into uploads/covers/.
 * Returns unique filename for DB, or null on failure.
 */
function storeBookCoverUpload(array $file): ?string {
    if (!bookCoverUploadPresent($file)) {
        return null;
    }

    $validationError = validateBookCoverUpload($file);
    if ($validationError !== null) {
        error_log('Cover validation failed: ' . $validationError);
        return null;
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($extension, BOOK_COVER_ALLOWED_EXT, true)) {
        return null;
    }

    $uploadDir = BOOK_COVERS_UPLOAD_DIR;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        error_log('Failed to create cover upload directory: ' . $uploadDir);
        return null;
    }

    if (!is_writable($uploadDir)) {
        error_log('Cover upload directory is not writable: ' . $uploadDir);
        return null;
    }

    $newFileName = time() . '_' . uniqid('', true) . '.' . $extension;
    $uploadPath = $uploadDir . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        error_log('move_uploaded_file failed: ' . ($file['tmp_name'] ?? '') . ' -> ' . $uploadPath);
        return null;
    }

    if (!is_file($uploadPath)) {
        error_log('Uploaded cover file does not exist after move_uploaded_file: ' . $uploadPath);
        return null;
    }

    return $newFileName;
}

/** Delete a cover file from uploads/covers/ if it exists. */
function deleteBookCoverFile(?string $filename): bool {
    if ($filename === null || $filename === '') {
        return false;
    }
    $path = bookCoverDiskPath($filename);
    if (is_file($path)) {
        return @unlink($path);
    }
    return false;
}

/** Remove cached WebP derivatives for a book cover. */
function deleteBookCoverCache(int $bookId): void {
    $cacheDir = BOOK_COVERS_UPLOAD_DIR . 'cache';
    if (!is_dir($cacheDir)) {
        return;
    }
    foreach (glob($cacheDir . '/b' . $bookId . '_*') ?: [] as $cf) {
        if (is_file($cf)) {
            @unlink($cf);
        }
    }
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(stripslashes(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Log activity
function logActivity($action, $details = '') {
    global $conn;

    // Get admin_id from session, default to 1 (first admin) if not available
    $admin_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

    // Verify admin exists
    $verify_stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $verify_stmt->bind_param("i", $admin_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    // If admin doesn't exist, get the first admin
    if ($verify_result->num_rows === 0) {
        $default_stmt = $conn->prepare("SELECT id FROM users LIMIT 1");
        $default_stmt->execute();
        $default_result = $default_stmt->get_result();
        if ($default_result->num_rows > 0) {
            $admin_row = $default_result->fetch_assoc();
            $admin_id = $admin_row['id'];
        } else {
            // No users in database, skip logging
            return;
        }
    }

    // Insert activity log
    $query = "INSERT INTO activity_logs (admin_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("iss", $admin_id, $action, $details);
        $stmt->execute();
        $stmt->close();
    }
}

// Get student by NIS
function getStudentByNIS($nis) {
    global $conn;
    $query = "SELECT * FROM students WHERE student_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $nis);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get book by ID
function getBookByID($id) {
    global $conn;
    $query = "SELECT * FROM books WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Slug kategori untuk styling cover placeholder (Fiksi, Nonfiksi, Akademik, Sejarah).
 */
function getBookCoverCategorySlug($category) {
    $c = trim((string)$category);
    $map = [
        'Fiksi' => 'fiksi',
        'Nonfiksi' => 'nonfiksi',
        'Akademik' => 'akademik',
        'Sejarah' => 'sejarah',
    ];
    return $map[$c] ?? 'default';
}

function bookCoverTruncate($text, $max = 48) {
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($text) > $max) {
        return mb_substr($text, 0, $max) . '…';
    }
    if (strlen($text) > $max) {
        return substr($text, 0, $max) . '…';
    }
    return $text;
}

/**
 * Cek file cover unggahan benar-benar ada di disk (hindari broken img).
 */
function bookHasLocalCoverFile(array $book) {
    if (empty($book['cover'])) {
        return false;
    }
    $safe = basename($book['cover']);
    return is_file(bookCoverDiskPath($safe));
}

/**
 * URL cover Open Library berdasarkan judul (opsional, ringan — tanpa API key).
 * -M = medium (cukup untuk thumbnail tabel, lebih ringan dari -L).
 */
function bookOpenLibraryCoverUrl($title) {
    return 'https://covers.openlibrary.org/b/title/' . rawurlencode(trim((string)$title)) . '-M.jpg';
}

/** URL api thumbnail WebP untuk cover lokal (daftar buku — ringan). */
function bookCoverThumbApiUrl($bookId, $assetsPrefix = '../') {
    return rtrim($assetsPrefix, '/') . '/api/cover-image.php?id=' . (int) $bookId . '&t=thumb';
}

/** URL api gambar lebih besar untuk modal/detail (bukan HD penuh). */
function bookCoverFullApiUrl($bookId, $assetsPrefix = '../') {
    return rtrim($assetsPrefix, '/') . '/api/cover-image.php?id=' . (int) $bookId . '&t=full';
}

/**
 * Markup cover untuk daftar buku: lokal → Open Library → placeholder kategori.
 *
 * @param string $assetsPrefix Path relatif ke root app dari halaman pemanggil, mis. '../'
 */
function renderBookCoverThumbnail(array $book, $assetsPrefix = '../') {
    $title = $book['title'] ?? '';
    $author = $book['author'] ?? '';
    $category = $book['category'] ?? '';
    $bookId = (int) ($book['id'] ?? 0);
    $alt = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    if (bookHasLocalCoverFile($book)) {
        $thumbSrc = htmlspecialchars(bookCoverThumbApiUrl($bookId, $assetsPrefix), ENT_QUOTES, 'UTF-8');
        $fullSrc = htmlspecialchars(bookCoverFullApiUrl($bookId, $assetsPrefix), ENT_QUOTES, 'UTF-8');
        return '<div class="book-cover-cell book-cover-cell--local">'
            . '<div class="book-cover-wrap">'
            . '<div class="book-cover-skeleton" aria-hidden="true"></div>'
            . '<img src="' . $thumbSrc . '" alt="' . $alt . '" class="cover-thumb-img" width="120" height="180" loading="lazy" decoding="async" fetchpriority="low" data-full-src="' . $fullSrc . '" onload="this.classList.add(\'is-loaded\');var w=this.closest(\'.book-cover-wrap\');if(w){var s=w.querySelector(\'.book-cover-skeleton\');if(s)s.setAttribute(\'hidden\',\'\');}">'
            . '</div></div>';
    }

    $slug = getBookCoverCategorySlug($category);
    $tShort = htmlspecialchars(bookCoverTruncate($title, 52), ENT_QUOTES, 'UTF-8');
    $aShort = htmlspecialchars(bookCoverTruncate($author, 36), ENT_QUOTES, 'UTF-8');
    $cShort = htmlspecialchars(bookCoverTruncate($category, 18), ENT_QUOTES, 'UTF-8');

    return '<div class="book-cover-cell">'
        . '<div class="book-cover-placeholder book-cover-placeholder--' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '" style="display:flex;" role="img" aria-label="' . $tShort . '">'
        . '<span class="book-cover-placeholder__icon"><i class="fas fa-book" aria-hidden="true"></i></span>'
        . '<span class="book-cover-placeholder__title">' . $tShort . '</span>'
        . '<span class="book-cover-placeholder__author">' . $aShort . '</span>'
        . '<span class="book-cover-placeholder__cat">' . $cShort . '</span>'
        . '</div>'
        . '</div>';
}

// Count borrowed books this week
function countBorrowedThisWeek($student_id) {
    global $conn;
    $seven_days_ago = date('Y-m-d', strtotime('-7 days'));
    $query = "SELECT COUNT(*) as count FROM borrowings WHERE student_id = ? AND borrow_date >= ? AND status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $student_id, $seven_days_ago);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'];
}

// Calculate total fine for student (sums all recorded fines in returns table)
function calculateTotalFine($student_id) {
    global $conn;
    $query = "SELECT COALESCE(SUM(fine), 0) as total_fine FROM returns WHERE student_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total_fine'];
}

// Get active borrowings for student
function getActiveBorrowings($student_id) {
    global $conn;
    $query = "SELECT b.*, bk.title, bk.author FROM borrowings b
              JOIN books bk ON b.book_id = bk.id
              WHERE b.student_id = ? AND b.status = 'active'
              ORDER BY b.due_date ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Calculate fine for late return
function calculateFine($due_date, $return_date) {
    $due = new DateTime($due_date);
    $return = new DateTime($return_date);

    if ($return <= $due) return 0;

    $interval = $due->diff($return);
    $days_late = $interval->days;

    $fine = $days_late * 5000; // Rp5000 per hari
    return min($fine, 100000); // Max Rp100000
}

// Hanya kelas SMP (7A, 7B, 8A, 8B, 9A, 9B)
function isAllowedStudentClass($class) {
    $allowed_classes = ['7A', '7B', '8A', '8B', '9A', '9B'];
    return is_string($class) && in_array($class, $allowed_classes, true);
}

// Fragment SQL untuk membatasi siswa ke kelas 10 & 11 (hindari data 12.X di UI/statistik)
function sqlStudentsOnlySMP($alias = 's') {
    return "({$alias}.class LIKE '7%' OR {$alias}.class LIKE '8%' OR {$alias}.class LIKE '9%')";
}

// Daftar kelas yang valid untuk dropdown (SMP: 7A, 7B, 8A, 8B, 9A, 9B)
function getAllClasses() {
    return ['7A', '7B', '8A', '8B', '9A', '9B'];
}

/** @deprecated Use storeBookCoverUpload() for book covers. */
function uploadAndResizeImage($file, $target_dir, $max_width = 200, $max_height = 200) {
    unset($target_dir, $max_width, $max_height);
    return storeBookCoverUpload($file);
}

// Get dashboard statistics
function getDashboardStats() {
    global $conn;

    $stats = [];

    // Available books (total stock semua buku)
    $result = $conn->query("SELECT COALESCE(SUM(stock), 0) as total FROM books");
    $stats['available_books'] = (int) $result->fetch_assoc()['total'];

    // Borrowed books (jumlah peminjaman aktif)
    $result = $conn->query("SELECT COUNT(*) as count FROM borrowings WHERE status = 'active'");
    $stats['borrowed_books'] = $result->fetch_assoc()['count'];

    // Total books (stock tersedia + buku yang sedang dipinjam)
    $stats['total_books'] = $stats['available_books'] + $stats['borrowed_books'];

    // Late books (peminjaman yang sudah terlambat)
    $result = $conn->query("SELECT COUNT(*) as count FROM borrowings WHERE status = 'active' AND due_date < CURDATE()");
    $stats['late_books'] = $result->fetch_assoc()['count'];

    return $stats;
}

// Get borrowings for the current week (Senin - Sabtu, resets weekly on Sunday)
function getBorrowingsCurrentWeek() {
    global $conn;
    $data = [];

    // Get the date of the most recent Sunday (Day 0)
    $today_w = date('w');
    $sunday_date = date('Y-m-d', strtotime("-$today_w days"));

    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    // Show only Monday (1) to Saturday (6)
    for ($i = 1; $i <= 6; $i++) {
        $date = date('Y-m-d', strtotime("$sunday_date +$i days"));
        $query = "SELECT COUNT(*) as count FROM borrowings b
                  JOIN students s ON b.student_id = s.id
                  WHERE DATE(b.borrow_date) = ?
                  AND " . sqlStudentsOnlySMP('s');
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        $data[] = [
            'date' => $days[$i],
            'full_date' => $date,
            'count' => $result['count']
        ];
    }

    return $data;
}

// Get borrowing stats by major (per kelas SMP)
function getBorrowingsByMajor() {
    global $conn;
    $classes = ['7A', '7B', '8A', '8B', '9A', '9B'];
    $data = [];

    $today_w = date('w');
    $sunday_date = date('Y-m-d', strtotime("-$today_w days"));

    foreach ($classes as $class) {
        $query = "SELECT COUNT(b.id) as count FROM borrowings b
                  JOIN students s ON b.student_id = s.id
                  WHERE s.class = ?
                  AND b.status = 'active'
                  AND b.is_deleted = 0
                  AND b.borrow_date >= ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $class, $sunday_date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $data[$class] = $result['count'];
    }

    return $data;
}

// Get recent transactions
function getRecentTransactions($limit = 5) {
    global $conn;
    $query = "SELECT b.id, s.student_id, s.name, bk.title, b.borrow_date, b.status
              FROM borrowings b
              JOIN students s ON b.student_id = s.id
              JOIN books bk ON b.book_id = bk.id
              WHERE " . sqlStudentsOnlySMP('s') . " AND b.is_deleted = 0
              ORDER BY b.borrow_date DESC
              LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get overdue books
function getOverdueBooks() {
    global $conn;
    $query = "SELECT b.id, s.student_id, s.name, bk.title, b.due_date FROM borrowings b
              JOIN students s ON b.student_id = s.id
              JOIN books bk ON b.book_id = bk.id
              WHERE " . sqlStudentsOnlySMP('s') . " AND b.status = 'active' AND b.is_deleted = 0 AND b.due_date < DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND b.due_date >= CURDATE()";
    return $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

// Get logo
function getSchoolLogo() {
    global $conn;
    $query = "SELECT setting_value FROM settings WHERE setting_key = 'school_logo'";
    $result = $conn->query($query)->fetch_assoc();
    return $result['setting_value'] ?? null;
}

// Set logo
function setSchoolLogo($filename) {
    global $conn;
    $query = "INSERT INTO settings (setting_key, setting_value) VALUES ('school_logo', ?)
              ON DUPLICATE KEY UPDATE setting_value = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $filename, $filename);
    return $stmt->execute();
}

// Get active borrowing details for a student (fine must be calculated separately)
function getStudentBorrowingDetails($student_id) {
    global $conn;
    $query = "SELECT b.id, b.book_id, b.borrow_date, b.due_date, b.status, bk.title, bk.author
              FROM borrowings b
              JOIN books bk ON b.book_id = bk.id
              WHERE b.student_id = ? AND b.status = 'active' AND b.is_deleted = 0
              ORDER BY b.due_date ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
