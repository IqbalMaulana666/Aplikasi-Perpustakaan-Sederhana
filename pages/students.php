<?php
require_once '../includes/session_check.php';
require_once '../includes/functions.php';

$page         = (int) ($_GET['page'] ?? 1);
$search       = $_GET['search'] ?? '';
$filter_class = $_GET['class'] ?? '';

if (!empty($filter_class) && !isAllowedStudentClass($filter_class)) {
    $filter_class = '';
}

$query  = "SELECT s.*, (SELECT COUNT(*) FROM borrowings b WHERE b.student_id = s.id AND b.status = 'active') as active_borrowings FROM students s WHERE 1=1 AND " . sqlStudentsOnlySMP('s');
$params = [];
$types  = '';

if (!empty($search)) {
    // Search keyword anywhere in name or NIS
    // Contoh: "Amalia" akan match "Elsa Amalia", "Amalia Rahman", dll
    $search_pattern = '%' . $search . '%';
    $query      .= " AND (s.name LIKE ? OR s.student_id LIKE ?)";
    $params      = [$search_pattern, $search_pattern];
    $types       = 'ss';
}

if (!empty($filter_class)) {
    $query   .= " AND s.class = ?";
    $params[] = $filter_class;
    $types   .= 's';
}

// Handle POST requests (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'add') {
        $name  = sanitize($_POST['name']);
        $class = sanitize($_POST['class']);

        if (!isAllowedStudentClass($class)) {
            echo json_encode(['success' => false, 'message' => 'Kelas tidak valid.']);
            exit;
        }

        // BUG #4 FIX: Gunakan transaction + SELECT FOR UPDATE untuk mencegah
        // race condition pada pembuatan NIS. Sebelumnya MAX() + 1 tanpa lock
        // bisa menghasilkan NIS duplikat jika dua request masuk bersamaan.
        $conn->begin_transaction();
        try {
            // Lock baris terakhir agar tidak ada proses lain yang baca MAX bersamaan
            $nis_result = $conn->query("SELECT MAX(CAST(student_id AS UNSIGNED)) as max_nis FROM students FOR UPDATE");
            if (!$nis_result) {
                throw new Exception('Database error: ' . $conn->error);
            }
            $nis_row = $nis_result->fetch_assoc();
            $new_nis = (int) ($nis_row['max_nis'] ?? 24000) + 1;

            $insert_stmt = $conn->prepare("INSERT INTO students (student_id, name, class, created_at) VALUES (?, ?, ?, NOW())");
            if (!$insert_stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            $new_nis_str = (string) $new_nis;
            $insert_stmt->bind_param("sss", $new_nis_str, $name, $class);
            $insert_stmt->execute();
            $insert_stmt->close();

            $conn->commit();
            logActivity('tambah_siswa', "Siswa: $name - Kelas: $class (NIS: $new_nis_str)");
            echo json_encode(['success' => true, 'message' => 'Siswa berhasil ditambahkan']);
        } catch (Exception $e) {
            $conn->rollback();
            // Cek apakah error karena duplicate entry (NIS sudah ada)
            if ($conn->errno === 1062) {
                echo json_encode(['success' => false, 'message' => 'NIS sudah digunakan, coba lagi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menambahkan siswa: ' . $e->getMessage()]);
            }
        }
        exit;
    }

    if ($_POST['action'] === 'edit') {
        if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['class'])) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            exit;
        }

        $id    = (int) $_POST['id'];
        $name  = sanitize($_POST['name']);
        $class = sanitize($_POST['class']);

        if (!isAllowedStudentClass($class)) {
            echo json_encode(['success' => false, 'message' => 'Kelas tidak valid.']);
            exit;
        }

        $check_stmt = $conn->prepare("SELECT id FROM students WHERE id = ? LIMIT 1");
        if (!$check_stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_stmt->close();

        if ($check_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Siswa tidak ditemukan']);
            exit;
        }

        $update_stmt = $conn->prepare("UPDATE students SET name=?, class=? WHERE id=?");
        if (!$update_stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        $update_stmt->bind_param("ssi", $name, $class, $id);

        if ($update_stmt->execute()) {
            logActivity('edit_siswa', "Siswa ID: $id - $name");
            echo json_encode(['success' => true, 'message' => 'Siswa berhasil diperbarui']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui siswa: ' . $conn->error]);
        }
        $update_stmt->close();
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        
        if ($id < 1) {
            echo json_encode(['success' => false, 'message' => 'ID siswa tidak valid']);
            exit;
        }

        // Check if student exists first
        $student_check = $conn->prepare("SELECT id, name FROM students WHERE id = ?");
        if (!$student_check) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        $student_check->bind_param("i", $id);
        $student_check->execute();
        $student_result = $student_check->get_result();
        $student_check->close();

        if ($student_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Siswa dengan ID ' . $id . ' tidak ditemukan']);
            exit;
        }
        $student_row = $student_result->fetch_assoc();
        $student_name = $student_row['name'];

        // Check if student has ACTIVE borrowings only
        $check_stmt = $conn->prepare("SELECT COUNT(*) as active_count FROM borrowings WHERE student_id = ? AND status = 'active'");
        if (!$check_stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $active_row = $check_stmt->get_result()->fetch_assoc();
        $active_borrowing_count = (int) ($active_row['active_count'] ?? 0);
        $check_stmt->close();

        if ($active_borrowing_count > 0) {
            echo json_encode(['success' => false, 'message' => 'Tidak bisa menghapus siswa ' . htmlspecialchars($student_name) . ' karena masih meminjam ' . $active_borrowing_count . ' buku']);
            exit;
        }

        // Start transaction for safe cascade delete
        $conn->begin_transaction();
        try {
            // Step 1: Delete from returns table (which has FK to students)
            $delete_returns = $conn->prepare("DELETE FROM returns WHERE student_id = ?");
            if (!$delete_returns) {
                throw new Exception('Database error: ' . $conn->error);
            }
            $delete_returns->bind_param("i", $id);
            $delete_returns->execute();
            $delete_returns->close();

            // Step 2: Delete from borrowings table (which has FK to students)
            $delete_borrowings = $conn->prepare("DELETE FROM borrowings WHERE student_id = ?");
            if (!$delete_borrowings) {
                throw new Exception('Database error: ' . $conn->error);
            }
            $delete_borrowings->bind_param("i", $id);
            $delete_borrowings->execute();
            $delete_borrowings->close();

            // Step 3: Delete student
            $delete_student = $conn->prepare("DELETE FROM students WHERE id = ?");
            if (!$delete_student) {
                throw new Exception('Database error: ' . $conn->error);
            }
            $delete_student->bind_param("i", $id);
            $delete_student->execute();
            $delete_student->close();

            // Commit transaction
            $conn->commit();
            logActivity('hapus_siswa', "Siswa ID: $id - Nama: $student_name (+ cascade delete dari borrowings & returns)");
            echo json_encode(['success' => true, 'message' => 'Siswa ' . htmlspecialchars($student_name) . ' dan riwayat peminjaman berhasil dihapus']);
        } catch (Exception $e) {
            // Rollback on any error
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus siswa: ' . $e->getMessage()]);
        }
        exit;
    }
}

$total_query = "SELECT COUNT(*) FROM students s WHERE 1=1 AND " . sqlStudentsOnlySMP('s');
$total_params = [];
$total_types  = '';

if (!empty($search)) {
    // Search keyword anywhere in name or NIS
    $search_pattern = '%' . $search . '%';
    $total_query .= " AND (s.name LIKE ? OR s.student_id LIKE ?)";
    $total_params = [$search_pattern, $search_pattern];
    $total_types  = 'ss';
}
if (!empty($filter_class)) {
    $total_query .= " AND s.class = ?";
    $total_params[] = $filter_class;
    $total_types   .= 's';
}

$stmt = $conn->prepare($total_query);
if (!empty($total_params)) {
    $stmt->bind_param($total_types, ...$total_params);
}
$stmt->execute();
$total_students = $stmt->get_result()->fetch_row()[0];
$page_size      = 25;
$total_pages    = ceil($total_students / $page_size);
$offset         = ($page - 1) * $page_size;

$query   .= " ORDER BY class ASC, name ASC LIMIT ? OFFSET ?";
$params[] = $page_size;
$params[] = $offset;
$types   .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$all_classes = getAllClasses();

$page_labels = [];
if (empty($search) && empty($filter_class)) {
    $page_labels = $all_classes;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <!-- Theme Detection - MUST BE FIRST to prevent white flash -->
    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Siswa - BukuKita | SMP TAQ SADAMIYYAH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body class="dashboard-layout page-table-only">
    <div class="main-layout">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="book-logo">
                    <i class="fas fa-book-open"></i>
                </div>
                <span class="brand-title">BukuKita</span>
            </div>
            <nav>
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-chart-line"></i> <span>Dashboard</span>
                </a>
                <a href="books.php" class="nav-link">
                    <i class="fas fa-book"></i> <span>Manajemen Buku</span>
                </a>
                <a href="students.php" class="nav-link active">
                    <i class="fas fa-users"></i> <span>Manajemen Siswa</span>
                </a>
                <a href="borrowing.php" class="nav-link">
                    <i class="fas fa-hand-holding"></i> <span>Peminjaman</span>
                </a>
                <a href="returning.php" class="nav-link">
                    <i class="fas fa-undo"></i> <span>Pengembalian</span>
                </a>
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-pie"></i> <span>Laporan</span>
                </a>
            </nav>
        </aside>

        <main class="content-wrapper">
            <!-- Navbar -->
            <nav class="navbar">
                <div class="navbar-content">
                    <div class="navbar-title">
                        <i class="fas fa-bars" style="cursor: pointer; display: none;" onclick="toggleSidebar()" id="sidebarToggle"></i>
                        <img src="../assets/uploads/logo/logo-transparent.png" alt="Logo SMP TAQ SADAMIYYAH" class="school-logo">
                        BukuKita | SMP TAQ SADAMIYYAH
                    </div>
                    <div class="navbar-user">
                        <button class="theme-toggle-btn" title="Switch Theme">
                            <i class="fas fa-sun"></i>
                        </button>
                        <span class="user-name">Admin: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <a href="../auth/logout.php" class="btn-logout">Logout</a>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <section class="dashboard-content">

        <div class="page-header">
            <h2>Manajemen Siswa</h2>
            <p>Kelola data siswa sekolah</p>
        </div>

        <!-- Student Management Card -->
        <div class="card table-card">
            <div class="card-header">
                <h5>Daftar Siswa</h5>
                <button class="btn btn-primary" onclick="addStudent()">
                    <i class="fas fa-plus"></i> Tambah Siswa
                </button>
            </div>
            
            <!-- Search & Filter -->
            <div class="card-body" style="border-bottom: 1px solid var(--border-color);">
                <div class="search-box" style="margin-bottom: 0;">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <!-- BUG #4b FIX: value diisi dari $_GET['search'] agar field
                             tidak kosong saat halaman di-reload setelah pencarian. -->
                        <input type="text" class="search-input" id="searchInput"
                               placeholder="Cari nama atau NIS siswa..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <select class="filter-select" id="classFilter">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($all_classes as $cls): ?>
                        <option value="<?php echo htmlspecialchars($cls); ?>"
                            <?php echo $filter_class === $cls ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cls); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 12%;">NIS</th>
                            <th style="width: 30%;">Nama Siswa</th>
                            <th style="width: 13%;">Kelas</th>
                            <th style="width: 18%; text-align: center;">Status Pinjam</th>
                            <th style="width: 27%; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="studentsTable">
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="5" style="padding: 40px;">
                                    <!-- <div style="text-align: center; width: 100%;">Belum ada siswa</div> -->
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['class']); ?></td>
                                <td style="text-align: center;">
                                    <?php
                                    $active_borrowing = $student['active_borrowings'];
                                    if ($active_borrowing > 0) {
                                        echo '<button class="badge-soft badge-soft-warning" onclick="viewStudentBorrowings(' . $student['id'] . ', \'' . htmlspecialchars($student['name']) . '\')" style="border: none; cursor: pointer; font-family: inherit;">Meminjam (' . $active_borrowing . ')</button>';
                                    } else {
                                        echo '<span class="badge-soft badge-soft-primary">Tidak Ada</span>';
                                    }
                                    ?>
                                </td>
                                <td style="text-align: center;">
                                    <button class="btn btn-sm btn-primary" onclick="editStudent(event, <?php echo $student['id']; ?>)" style="width: 75px; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteStudent(<?php echo $student['id']; ?>)" style="width: 75px; margin-left: 5px; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="card-body" style="padding-top: 0;">
                <?php if ($total_pages > 1): ?>
        <?php
        $cols = min(6, $total_pages);
        ?>
        <div class="pagination" style="display: grid; grid-template-columns: repeat(<?php echo $cols; ?>, 1fr); width: 100%; margin-top: 30px; gap: 8px;">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php $label = (!empty($page_labels) && isset($page_labels[$i - 1])) ? $page_labels[$i - 1] : $i; ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&class=<?php echo urlencode($filter_class); ?>" class="<?php echo $i === (int)$page ? 'active' : ''; ?>" style="text-align: center; padding: 12px 10px; font-size: 16px; font-weight: 600; white-space: nowrap; border-radius: 8px;">
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
            </div>
        </div>
            </section>
        </main>
    </div>

    <!-- Student Modal -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="studentModalTitle">Tambah Siswa</h5>
                <button type="button" class="btn-close" data-close-modal style="border: none; background: none; font-size: 24px; cursor: pointer; color: var(--text-main);"><i class="fas fa-times"></i></button>
            </div>
            <form id="studentForm">
                <div class="form-group">
                    <label for="studentName" class="form-label">Nama Siswa</label>
                    <input type="text" class="form-control" id="studentName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="studentClass" class="form-label">Kelas</label>
                    <select class="form-control" id="studentClass" name="class" required>
                        <option value="">Pilih Kelas</option>
                        <?php foreach ($all_classes as $cls): ?>
                        <option value="<?php echo htmlspecialchars($cls); ?>"><?php echo htmlspecialchars($cls); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" data-close-modal>Batal</button>
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Student Borrowings Detail Modal -->
    <div id="borrowingsDetailModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h5>Detail Buku yang Sedang Dipinjam</h5>
                <button type="button" class="btn-close" data-close-modal style="border: none; background: none; font-size: 24px; cursor: pointer; color: var(--text-main);"><i class="fas fa-times"></i></button>
            </div>
            <div style="padding: 20px;">
                <div style="margin-bottom: 20px; padding: 15px; background-color: var(--bg-main); border-radius: 8px; border: 1px solid var(--border-color);">
                    <div style="margin-bottom: 10px;">
                        <strong>Nama Siswa:</strong> <span id="borrowDetailStudentName"></span>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong>Total Peminjaman Aktif:</strong> <span id="borrowDetailTotal" style="color: var(--primary); font-weight: bold;"></span>
                    </div>
                    <div>
                        <strong>Total Denda Saat Ini:</strong> <span id="borrowDetailTotalFine" style="color: var(--danger); font-weight: bold;"></span>
                    </div>
                </div>

                <div id="borrowingsList" style="max-height: 400px; overflow-y: auto;"></div>

                <div id="noBorrowingsMsg" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <div style="font-size: 48px; margin-bottom: 10px;">📚</div>
                    <p>Tidak ada buku yang sedang dipinjam</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/theme-toggle.js"></script>
    <script>
        setActiveNav('a[href="students.php"]');

        let editingStudentId = null;

        function addStudent() {
            editingStudentId = null;
            document.getElementById('studentModalTitle').textContent = 'Tambah Siswa';
            document.getElementById('studentForm').reset();
            openModal('studentModal');
        }

        document.getElementById('studentForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('action', editingStudentId ? 'edit' : 'add');
            formData.append('name', document.getElementById('studentName').value);
            formData.append('class', document.getElementById('studentClass').value);

            if (editingStudentId) {
                formData.append('id', editingStudentId);
            }

            const submitBtn = document.querySelector('#studentForm button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Menyimpan...';

            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('HTTP error, status = ' + response.status);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('studentModal');
                    document.getElementById('studentForm').reset();
                    editingStudentId = null;
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Gagal menyimpan data', 'error');
                }
            })
            .catch(error => {
                showToast('Terjadi kesalahan: ' + error.message, 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });

        function editStudent(event, id) {
            fetch('../api/get-students.php?id=' + id)
            .then(response => {
                if (!response.ok) throw new Error('HTTP error, status = ' + response.status);
                return response.json();
            })
            .then(student => {
                if (!student || !student.id) throw new Error('Siswa tidak ditemukan');
                document.getElementById('studentModalTitle').textContent = 'Edit Siswa';
                document.getElementById('studentName').value = student.name || '';
                document.getElementById('studentClass').value = student.class || '';
                editingStudentId = student.id;
                openModal('studentModal');
            })
            .catch(error => {
                showToast('Gagal memuat data siswa: ' + error.message, 'error');
            });
        }

        function deleteStudent(id) {
            confirmAction('Hapus Siswa?', 'Tindakan ini tidak bisa dibatalkan.', function() {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) throw new Error('HTTP error, status = ' + response.status);
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message || 'Gagal menghapus siswa', 'error');
                    }
                })
                .catch(error => {
                    showToast('Terjadi kesalahan: ' + error.message, 'error');
                });
            });
        }

        // Hanya cari ketika Enter ditekan
        document.getElementById('searchInput').addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                const search = this.value;
                const cls = document.getElementById('classFilter').value;
                window.location.href = '?search=' + encodeURIComponent(search) + '&class=' + encodeURIComponent(cls);
            }
        });

        // Auto-refresh ketika input dikosongkan
        document.getElementById('searchInput').addEventListener('input', function(event) {
            if (this.value === '') {
                const cls = document.getElementById('classFilter').value;
                window.location.href = '?search=&class=' + encodeURIComponent(cls);
            }
        });

        document.getElementById('classFilter').addEventListener('change', function() {
            const search = document.getElementById('searchInput').value;
            const cls = this.value;
            window.location.href = '?search=' + encodeURIComponent(search) + '&class=' + encodeURIComponent(cls);
        });

        function viewStudentBorrowings(studentId, studentName) {
            document.getElementById('borrowDetailStudentName').textContent = studentName;
            document.getElementById('borrowingsList').innerHTML = '';
            document.getElementById('noBorrowingsMsg').style.display = 'block';
            openModal('borrowingsDetailModal');

            fetch('../api/get-student-borrowings.php?student_id=' + studentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.borrowings.length > 0) {
                        document.getElementById('noBorrowingsMsg').style.display = 'none';
                        document.getElementById('borrowDetailTotal').textContent = data.total_borrowings + ' buku';
                        document.getElementById('borrowDetailTotalFine').textContent = formatCurrency(data.total_fine);

                        let html = '';
                        data.borrowings.forEach((borrowing) => {
                            const dueDateObj = new Date(borrowing.due_date);
                            const today = new Date();
                            const isOverdue = today > dueDateObj;

                            html += `
                                <div style="margin-bottom: 12px; padding: 12px; background-color: var(--bg-main); border-radius: 6px; border-left: 4px solid ${isOverdue ? 'var(--danger)' : 'var(--success)'}">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <strong>${borrowing.title}</strong><br>
                                            <small style="color: var(--text-muted);">Pengarang: ${borrowing.author}</small><br>
                                            <small style="color: var(--text-muted);">Jatuh Tempo: ${formatDate(borrowing.due_date)}</small>
                                        </div>
                                        <div style="text-align: right; margin-left: 10px;">
                                            ${isOverdue
                                                ? '<span class="badge-soft badge-soft-danger" style="margin-bottom: 5px;">TERLAMBAT</span>'
                                                : '<span class="badge-soft badge-soft-success" style="margin-bottom: 5px;">AKTIF</span>'
                                            }
                                            <div style="color: ${borrowing.fine > 0 ? '#ff6b6b' : '#666'}; font-weight: bold;">
                                                ${borrowing.fine > 0 ? 'Denda: ' + formatCurrency(borrowing.fine) : 'Tidak ada denda'}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });

                        document.getElementById('borrowingsList').innerHTML = html;
                    } else {
                        document.getElementById('borrowDetailTotal').textContent = '0 buku';
                        document.getElementById('borrowDetailTotalFine').textContent = formatCurrency(0);
                    }
                })
                .catch(error => {
                    showToast('Gagal memuat data peminjaman', 'error');
                });
        }
    </script>
</body>
</html>
