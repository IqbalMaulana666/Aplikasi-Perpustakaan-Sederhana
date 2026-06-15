<?php
require_once '../includes/session_check.php';
require_once '../includes/functions.php';

// Statistik buku
$stats = getDashboardStats();

// Transaksi terbaru (10)
$recent_transactions = getRecentTransactions(10);

// Activity log (20 terbaru) — gabungkan dengan hapus_transaksi juga
$logs_query = "SELECT al.*, u.username FROM activity_logs al
               LEFT JOIN users u ON al.admin_id = u.id
               ORDER BY al.created_at DESC LIMIT 20";
$activity_logs = $conn->query($logs_query)->fetch_all(MYSQLI_ASSOC);

// Info buku lengkap
$books_query = "SELECT id, title, author, category, stock, status FROM books ORDER BY created_at DESC";
$books_all = $conn->query($books_query)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - BukuKita | SMP TAQ SADAMIYYAH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    <style>
        .report-tabs {
            display: flex;
            gap: 4px;
            padding: 4px;
            background: var(--bg-main, #f4f4f4);
            border-radius: 10px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color, #e0e0e0);
            width: fit-content;
        }
        .report-tab {
            padding: 8px 20px;
            border: none;
            border-radius: 7px;
            background: transparent;
            color: var(--text-muted, #888);
            font-size: 14px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.18s, color 0.18s;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .report-tab.active {
            background: var(--primary, #4f6ef7);
            color: #fff;
            box-shadow: 0 2px 8px rgba(79,110,247,.18);
        }
        .report-tab:not(.active):hover {
            background: var(--border-color, #e8e8e8);
            color: var(--text-main, #333);
        }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        .sysinfo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            padding: 20px;
        }
        .sysinfo-item strong { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted, #888); margin-bottom: 4px; }
        .sysinfo-item span { font-size: 15px; font-weight: 500; color: var(--text-main, #222); }
    </style>
</head>
<body class="dashboard-layout">
    <div class="main-layout">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="book-logo"><i class="fas fa-book-open"></i></div>
                <span class="brand-title">BukuKita</span>
            </div>
            <nav>
                <a href="dashboard.php" class="nav-link"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a>
                <a href="books.php" class="nav-link"><i class="fas fa-book"></i> <span>Manajemen Buku</span></a>
                <a href="students.php" class="nav-link"><i class="fas fa-users"></i> <span>Manajemen Siswa</span></a>
                <a href="borrowing.php" class="nav-link"><i class="fas fa-hand-holding"></i> <span>Peminjaman</span></a>
                <a href="returning.php" class="nav-link"><i class="fas fa-undo"></i> <span>Pengembalian</span></a>
                <a href="reports.php" class="nav-link active"><i class="fas fa-chart-pie"></i> <span>Laporan</span></a>
            </nav>
        </aside>

        <main class="content-wrapper">
            <nav class="navbar">
                <div class="navbar-content">
                    <div class="navbar-title">
                        <i class="fas fa-bars" style="cursor:pointer;display:none;" onclick="toggleSidebar()" id="sidebarToggle"></i>
                        <img src="../assets/uploads/logo/logo-transparent.png" alt="Logo SMP TAQ SADAMIYYAH" class="school-logo">
                        BukuKita | SMP TAQ SADAMIYYAH
                    </div>
                    <div class="navbar-user">
                        <button class="theme-toggle-btn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                        <span class="user-name">Admin: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <a href="../auth/logout.php" class="btn-logout">Logout</a>
                    </div>
                </div>
            </nav>

            <section class="dashboard-content">
                <div class="page-header">
                    <h2>Laporan Perpustakaan</h2>
                    <p>Statistik, transaksi, koleksi buku, dan riwayat aktivitas sistem</p>
                </div>

                <!-- Kartu Statistik -->
                <div class="stats-grid mb-4">
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-value"><?php echo $stats['total_books']; ?></div>
                            <div class="stat-label">Total Buku</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-value"><?php echo $stats['available_books']; ?></div>
                            <div class="stat-label">Buku Tersedia</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-value"><?php echo $stats['borrowed_books']; ?></div>
                            <div class="stat-label">Buku Dipinjam</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-value"><?php echo $stats['late_books']; ?></div>
                            <div class="stat-label">Terlambat</div>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="report-tabs">
                    <button class="report-tab active" onclick="switchTab('tab-transaksi', this)">
                        <i class="fas fa-exchange-alt"></i> Transaksi
                    </button>
                    <button class="report-tab" onclick="switchTab('tab-buku', this)">
                        <i class="fas fa-book"></i> Data Buku
                    </button>
                    <button class="report-tab" onclick="switchTab('tab-aktivitas', this)">
                        <i class="fas fa-history"></i> Activity Log
                    </button>
                    <button class="report-tab" onclick="switchTab('tab-sistem', this)">
                        <i class="fas fa-info-circle"></i> Info Sistem
                    </button>
                </div>

                <!-- Tab: Transaksi Terbaru -->
                <div id="tab-transaksi" class="tab-panel active">
                    <div class="card table-card">
                        <div class="card-header">
                            <h5>Transaksi Peminjaman Terbaru</h5>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">NIS</th>
                                        <th style="text-align: left;">Nama Siswa</th>
                                        <th style="text-align: left;">Judul Buku</th>
                                        <th style="text-align: center;">Tanggal Pinjam</th>
                                        <th style="text-align: center;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_transactions)): ?>
                                        <tr><td colspan="5" style="padding: 40px;">
                                            <!-- <div style="width: 100%; display: flex; justify-content: center; align-items: center;">Belum ada data transaksi</div>
                                        </td></tr> -->
                                    <?php else: ?>
                                        <?php foreach ($recent_transactions as $t): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($t['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($t['name']); ?></td>
                                            <td><?php echo htmlspecialchars($t['title']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($t['borrow_date'])); ?></td>
                                            <td>
                                                <?php if ($t['status'] === 'active'): ?>
                                                    <span class="badge-soft badge-soft-warning">Dipinjam</span>
                                                <?php else: ?>
                                                    <span class="badge-soft badge-soft-success">Selesai</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab: Data Buku -->
                <div id="tab-buku" class="tab-panel">
                    <div class="card table-card">
                        <div class="card-header">
                            <h5>Koleksi Buku Perpustakaan</h5>
                            <span style="font-size:13px; color:var(--text-muted);"><?php echo count($books_all); ?> judul</span>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Judul Buku</th>
                                        <th>Pengarang</th>
                                        <th>Kategori</th>
                                        <th style="text-align:center;">Stok</th>
                                        <th style="text-align:center;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($books_all)): ?>
                                        <!-- <tr><td colspan="6" class="text-center" style="padding:40px;">Belum ada data buku</td></tr> -->
                                    <?php else: ?>
                                        <?php foreach ($books_all as $i => $b): ?>
                                        <tr>
                                            <td style="color:var(--text-muted); font-size:13px;"><?php echo $i + 1; ?></td>
                                            <td><?php echo htmlspecialchars($b['title']); ?></td>
                                            <td><?php echo htmlspecialchars($b['author']); ?></td>
                                            <td><?php echo htmlspecialchars($b['category']); ?></td>
                                            <td style="text-align:center;"><?php echo (int)$b['stock']; ?></td>
                                            <td style="text-align:center;">
                                                <?php if ((int)$b['stock'] <= 0): ?>
                                                    <span class="badge-soft badge-soft-danger">Habis</span>
                                                <?php elseif ($b['status'] === 'available'): ?>
                                                    <span class="badge-soft badge-soft-success">Tersedia</span>
                                                <?php else: ?>
                                                    <span class="badge-soft badge-soft-warning">Dipinjam</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab: Activity Log -->
                <div id="tab-aktivitas" class="tab-panel">
                    <div class="card table-card">
                        <div class="card-header">
                            <h5>Activity Log (20 Terbaru)</h5>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Aksi</th>
                                        <th>Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($activity_logs)): ?>
                                        <!-- <tr><td colspan="3" class="text-center" style="padding:40px;">Belum ada aktivitas</td></tr> -->
                                    <?php else: ?>
                                        <?php
                                        $action_badges = [
                                            'login'               => ['class' => 'badge-soft-primary',  'icon' => 'sign-in-alt'],
                                            'logout'              => ['class' => 'badge-soft-warning',  'icon' => 'sign-out-alt'],
                                            'pinjam_buku'         => ['class' => 'badge-soft-primary',  'icon' => 'hand-holding'],
                                            'kembalikan_buku'     => ['class' => 'badge-soft-success',  'icon' => 'undo'],
                                            'tambah_buku'         => ['class' => 'badge-soft-primary',  'icon' => 'plus'],
                                            'edit_buku'           => ['class' => 'badge-soft-warning',  'icon' => 'edit'],
                                            'hapus_buku'          => ['class' => 'badge-soft-danger',   'icon' => 'trash'],
                                            'tambah_siswa'        => ['class' => 'badge-soft-primary',  'icon' => 'user-plus'],
                                            'edit_siswa'          => ['class' => 'badge-soft-warning',  'icon' => 'user-edit'],
                                            'hapus_siswa'         => ['class' => 'badge-soft-danger',   'icon' => 'user-minus'],
                                            'hapus_transaksi'     => ['class' => 'badge-soft-danger',   'icon' => 'times-circle'],
                                            'ganti_logo_sekolah'  => ['class' => 'badge-soft-primary',  'icon' => 'image'],
                                        ];
                                        foreach ($activity_logs as $log):
                                            $badge = $action_badges[$log['action']] ?? ['class' => 'badge-soft-primary', 'icon' => 'cog'];
                                        ?>
                                        <tr>
                                            <td style="white-space:nowrap;"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                            <td>
                                                <span class="badge-soft <?php echo $badge['class']; ?>" style="gap:6px;">
                                                    <i class="fas fa-<?php echo $badge['icon']; ?>"></i>
                                                    <?php echo htmlspecialchars($log['action']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab: Info Sistem -->
                <div id="tab-sistem" class="tab-panel">
                    <div class="card">
                        <div class="card-header">
                            <h5>Informasi Sistem</h5>
                        </div>
                        <div class="sysinfo-grid">
                            <div class="sysinfo-item">
                                <strong>Aplikasi</strong>
                                <span>BukuKita v1.0</span>
                            </div>
                            <div class="sysinfo-item">
                                <strong>Sekolah</strong>
                                <span>SMP TAQ SADAMIYYAH</span>
                            </div>
                            <div class="sysinfo-item">
                                <strong>Database</strong>
                                <span>MySQL</span>
                            </div>
                            <div class="sysinfo-item">
                                <strong>Framework</strong>
                                <span>PHP Native</span>
                            </div>
                            <div class="sysinfo-item">
                                <strong>Bootstrap</strong>
                                <span>v5.3.0</span>
                            </div>
                            <div class="sysinfo-item">
                                <strong>Tahun Dibuat</strong>
                                <span>2026</span>
                            </div>
                        </div>
                    </div>
                </div>

            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/theme-toggle.js"></script>
    <script>
        setActiveNav('a[href="reports.php"]');

        function switchTab(panelId, btn) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.report-tab').forEach(b => b.classList.remove('active'));
            document.getElementById(panelId).classList.add('active');
            btn.classList.add('active');
        }
    </script>
</body>
</html>
