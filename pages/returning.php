<?php
require_once '../includes/session_check.php';
require_once '../includes/functions.php';

$student_data = null;
$borrowings = [];
$total_fine = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (isset($_POST['search_nis'])) {
        $nis = sanitize($_POST['nis']);
        $query = "SELECT * FROM students WHERE (student_id = ? OR id = ?) AND (class LIKE '7%' OR class LIKE '8%' OR class LIKE '9%')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $nis, $nis);
        $stmt->execute();
        $student_data = $stmt->get_result()->fetch_assoc();

        if ($student_data) {
            $borrowings = getActiveBorrowings($student_data['id']);
            $total_fine = calculateTotalFine($student_data['id']);
            echo json_encode([
                'success' => true,
                'student' => $student_data,
                'borrowings' => $borrowings,
                'total_fine' => $total_fine
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Siswa tidak ditemukan']);
        }
        exit;
    }

    if (isset($_POST['return_books'])) {
        $student_id = (int)$_POST['student_id'];
        $borrowing_ids = $_POST['borrowing_ids'] ?? [];

        if (empty($borrowing_ids)) {
            echo json_encode(['success' => false, 'message' => 'Pilih minimal satu buku']);
            exit;
        }

        $class_check = $conn->prepare("SELECT class FROM students WHERE id = ?");
        $class_check->bind_param("i", $student_id);
        $class_check->execute();
        $cls_row = $class_check->get_result()->fetch_assoc();
        if (!$cls_row || !isAllowedStudentClass($cls_row['class'])) {
            echo json_encode(['success' => false, 'message' => 'Siswa tidak valid untuk pengembalian (hanya kelas 7, 8, dan 9)']);
            exit;
        }

        $total_return_fine = 0;
        $return_date = date('Y-m-d');
        $returned_details = [];

        // Get student name
        $student_stmt = $conn->prepare("SELECT name FROM students WHERE id = ?");
        $student_stmt->bind_param("i", $student_id);
        $student_stmt->execute();
        $student_name = $student_stmt->get_result()->fetch_assoc()['name'] ?? 'Siswa';

        foreach ($borrowing_ids as $borrowing_id) {
            $borrowing_id = (int)$borrowing_id;

            $borrowing_query = "SELECT b.*, bk.title FROM borrowings b JOIN books bk ON b.book_id = bk.id WHERE b.id = ? AND b.student_id = ? AND b.status = 'active'";
            $borrowing_stmt = $conn->prepare($borrowing_query);
            $borrowing_stmt->bind_param("ii", $borrowing_id, $student_id);
            $borrowing_stmt->execute();
            $borrowing = $borrowing_stmt->get_result()->fetch_assoc();

            if (!$borrowing) continue;

            $returned_details[] = [
                'title' => $borrowing['title'],
                'borrow_time' => $borrowing['created_at'] // Timestamp
            ];

            // Calculate fine
            $fine = calculateFine($borrowing['due_date'], $return_date);
            $total_return_fine += $fine;

            // Insert return record
            $return_query = "INSERT INTO returns (borrowing_id, student_id, return_date, fine) VALUES (?, ?, ?, ?)";
            $return_stmt = $conn->prepare($return_query);
            $return_stmt->bind_param("iisi", $borrowing_id, $student_id, $return_date, $fine);
            $return_stmt->execute();

            // Update borrowing status
            $update_query = "UPDATE borrowings SET status = 'returned' WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("i", $borrowing_id);
            $update_stmt->execute();

            // Update book stock
            $book_query = "SELECT stock FROM books WHERE id = ?";
            $book_stmt = $conn->prepare($book_query);
            $book_stmt->bind_param("i", $borrowing['book_id']);
            $book_stmt->execute();
            $book = $book_stmt->get_result()->fetch_assoc();

            $new_stock = $book['stock'] + 1;
            $status = 'available';
            $book_update_query = "UPDATE books SET stock = ?, status = ? WHERE id = ?";
            $book_update_stmt = $conn->prepare($book_update_query);
            $book_update_stmt->bind_param("isi", $new_stock, $status, $borrowing['book_id']);
            $book_update_stmt->execute();
        }

        logActivity('kembalikan_buku', "Siswa ID: $student_id - Denda: Rp" . number_format($total_return_fine, 0, ',', '.'));
        echo json_encode([
            'success' => true,
            'message' => 'Buku berhasil dikembalikan',
            'total_fine' => $total_return_fine,
            'student_name' => $student_name,
            'returned_details' => $returned_details
        ]);
        exit;
    }
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
    <title>Pengembalian - BukuKita | SMP TAQ SADAMIYYAH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body class="dashboard-layout">
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
                <a href="students.php" class="nav-link">
                    <i class="fas fa-users"></i> <span>Manajemen Siswa</span>
                </a>
                <a href="borrowing.php" class="nav-link">
                    <i class="fas fa-hand-holding"></i> <span>Peminjaman</span>
                </a>
                <a href="returning.php" class="nav-link active">
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
            <h2>Pengembalian Buku</h2>
            <p>Proses pengembalian buku dari siswa</p>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Search Student -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Cari Siswa</h5>
                    </div>
                    <form id="searchForm">
                        <div style="padding: 20px;">
                            <div class="form-group">
                                <label for="nisInput" class="form-label">NIS atau ID Siswa</label>
                                <input type="text" class="form-control" id="nisInput" name="nis" placeholder="Masukkan NIS" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Cari Siswa
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Student Borrowings -->
                <div id="borrowingsSection" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h5>Buku yang Dipinjam</h5>
                        </div>
                        <div style="padding: 20px;">
                            <div style="margin-bottom: 20px;">
                                <strong>Nama Siswa:</strong> <span id="studentName"></span><br>
                                <strong>Kelas:</strong> <span id="studentClass"></span><br>
                                <strong>Total Denda Saat Ini:</strong> <span id="currentFine"></span>
                            </div>

                            <form id="returningForm">
                                <input type="hidden" id="studentIdInput" name="student_id">
                                <div id="borrowingsList"></div>
                                <button type="submit" class="btn btn-primary w-100" style="margin-top: 20px;">
                                    <i class="fas fa-undo"></i> Proses Pengembalian
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="noBorrowingsMessage" style="display: none;">
                    <div class="card">
                        <div style="padding: 40px; text-align: center; color: #999;">
                            <div style="font-size: 48px; margin-bottom: 20px;">📚</div>
                            <p>Siswa tidak memiliki buku yang sedang dipinjam</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Informasi Pengembalian</h5>
                    </div>
                    <div style="padding: 20px; font-size: 14px;">
                        <div style="margin-bottom: 15px;">
                            <strong>Perhitungan Denda:</strong>
                            <ul style="margin-top: 10px; margin-bottom: 0;">
                                <li>Denda per hari: Rp5.000</li>
                                <li>Maksimal denda: Rp100.000</li>
                                <li>Sistem hitung otomatis</li>
                            </ul>
                        </div>
                        <div id="finePreview" class="fine-preview-card" style="display: none; background-color: var(--danger-bg); padding: 15px; border-radius: 8px; margin-top: 20px; border: 1px solid var(--danger-text);">
                            <strong style="color: var(--danger-text)">Estimasi Denda:</strong>
                            <div style="font-size: 20px; color: var(--danger-text); font-weight: 700; margin-top: 10px;" id="estimatedFine"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/theme-toggle.js"></script>
    <script>
        setActiveNav('a[href="returning.php"]');

        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('search_nis', true);
            formData.append('nis', document.getElementById('nisInput').value);

            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('studentName').textContent = data.student.name;
                    document.getElementById('studentClass').textContent = data.student.class;
                    document.getElementById('currentFine').textContent = formatCurrency(data.total_fine);
                    document.getElementById('studentIdInput').value = data.student.id;

                    if (data.borrowings.length > 0) {
                        let html = '';
                        data.borrowings.forEach((borrowing, index) => {
                            const fine = calculateFine(borrowing.due_date);
                            html += `
                                <div class="borrowed-book-item" style="margin-bottom: 15px; padding: 15px; background-color: var(--bg-main); border-radius: 8px;">
                                    <label style="display: flex; align-items: center; cursor: pointer; margin-bottom: 0;">
                                        <input type="checkbox" name="borrowing_ids[]" value="${borrowing.id}" data-due-date="${borrowing.due_date}" onchange="updateFinePreview()">
                                        <span style="margin-left: 10px;">
                                            <strong class="borrowed-book-title">${borrowing.title}</strong><br>
                                            <small class="borrowed-book-info" style="color: var(--text-muted);">Pengarang: ${borrowing.author}</small><br>
                                            <small class="borrowed-book-info" style="color: var(--text-muted);">Jatuh Tempo: ${formatDate(borrowing.due_date)}</small>
                                            ${fine > 0 ? `<br><small style="color: #ff6b6b;"><strong>Denda: ${formatCurrency(fine)}</strong></small>` : ''}
                                        </span>
                                    </label>
                                </div>
                            `;
                        });
                        document.getElementById('borrowingsList').innerHTML = html;
                        document.getElementById('borrowingsSection').style.display = 'block';
                        document.getElementById('noBorrowingsMessage').style.display = 'none';
                    } else {
                        document.getElementById('borrowingsSection').style.display = 'none';
                        document.getElementById('noBorrowingsMessage').style.display = 'block';
                    }
                } else {
                    showToast(data.message, 'error');
                }
            });
        });

        document.getElementById('returningForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            submitBtn.disabled = true;

            const formData = new FormData(this);
            formData.append('return_books', true);

            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.innerHTML = originalBtnHtml;
                submitBtn.disabled = false;

                if (data.success) {
                    let detailsHtml = '<div class="return-details-card" style="text-align: left; font-size: 14px; margin-top: 15px; background: var(--bg-main); border: 1px solid var(--border-color); padding: 15px; border-radius: 8px;">';
                    detailsHtml += `<p class="return-peminjam" style="margin-bottom: 10px; color: var(--text-main);"><strong>Peminjam:</strong> ${data.student_name}</p>`;
                    detailsHtml += '<ul style="padding-left: 20px; margin-bottom: 0; color: var(--text-muted);">';
                    data.returned_details.forEach(item => {
                        const borrowDate = new Date(item.borrow_time);
                        const timeString = borrowDate.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                        const dateString = borrowDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
                        detailsHtml += `<li style="margin-bottom: 8px;"><strong>${item.title}</strong><br><small style="color: var(--primary);"><i class="fas fa-clock"></i> Dipinjam pada jam ${timeString}, tanggal ${dateString}</small></li>`;
                    });
                    detailsHtml += '</ul></div>';

                    Swal.fire({
                        title: 'Pengembalian Berhasil!',
                        html: `
                            <p>${data.message}</p>
                            ${detailsHtml}
                            <div style="background-color: var(--primary-color); padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <strong>Total Denda:</strong><br>
                                <div style="font-size: 28px; color: var(--accent-color); font-weight: 700; margin-top: 10px;">
                                    ${formatCurrency(data.total_fine)}
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#6dc7cf',
                        confirmButtonText: 'OK'
                    });
                    
                    document.getElementById('searchForm').dispatchEvent(new Event('submit'));
                    document.getElementById('finePreview').style.display = 'none';
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(() => {
                submitBtn.innerHTML = originalBtnHtml;
                submitBtn.disabled = false;
                showToast('Terjadi kesalahan koneksi', 'error');
            });
        });

        function calculateFine(dueDate) {
            const due = new Date(dueDate);
            const today = new Date();
            const diffTime = today - due;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays <= 0) return 0;

            let fine = diffDays * 5000;
            return Math.min(fine, 100000);
        }

        function updateFinePreview() {
            const checkboxes = document.querySelectorAll('input[name="borrowing_ids[]"]:checked');
            let totalFine = 0;

            checkboxes.forEach(checkbox => {
                const dueDate = checkbox.getAttribute('data-due-date');
                totalFine += calculateFine(dueDate);
            });

            if (checkboxes.length > 0) {
                document.getElementById('finePreview').style.display = 'block';
                document.getElementById('estimatedFine').textContent = formatCurrency(totalFine);
            } else {
                document.getElementById('finePreview').style.display = 'none';
            }
        }

    </script>
</body>
</html>
