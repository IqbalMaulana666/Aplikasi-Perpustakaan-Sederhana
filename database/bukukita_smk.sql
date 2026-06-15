-- Create Database
CREATE DATABASE IF NOT EXISTS bukukita_smk;
USE bukukita_smk;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Admin User (Nozo / 4891)
INSERT INTO users (username, password) VALUES ('Nozo', '$2y$10$Kf3W8bQPXDqb/PfO6qKxbeM8TqNqVyXZLJxwX6wZMqGvLZLcX.r0a');

-- Books Table
CREATE TABLE IF NOT EXISTS books (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    cover VARCHAR(255) NULL,
    stock INT NOT NULL DEFAULT 3,
    status VARCHAR(20) DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Books (8 Fiksi + 7 Nonfiksi)
INSERT INTO books (title, author, category, stock, status) VALUES
('Crime and Punishment', 'Fyodor Dostoevsky', 'Fiksi', 3, 'available'),
('Laskar Pelangi', 'Andrea Hirata', 'Fiksi', 4, 'available'),
('Bumi Manusia', 'Pramoedya Ananta Toer', 'Fiksi', 3, 'available'),
('Harry Potter and the Sorcerer''s Stone', 'J.K. Rowling', 'Fiksi', 5, 'available'),
('The Alchemist', 'Paulo Coelho', 'Fiksi', 3, 'available'),
('Pride and Prejudice', 'Jane Austen', 'Fiksi', 4, 'available'),
('1984', 'George Orwell', 'Fiksi', 3, 'available'),
('To Kill a Mockingbird', 'Harper Lee', 'Fiksi', 4, 'available'),
('Sapiens', 'Yuval Noah Harari', 'Nonfiksi', 3, 'available'),
('Atomic Habits', 'James Clear', 'Nonfiksi', 5, 'available'),
('The Psychology of Money', 'Morgan Housel', 'Nonfiksi', 3, 'available'),
('Clean Code', 'Robert C. Martin', 'Nonfiksi', 4, 'available'),
('Filsafat Ilmu', 'Jujun S. Suriasumantri', 'Nonfiksi', 3, 'available'),
('The Art of War', 'Sun Tzu', 'Nonfiksi', 4, 'available'),
('Deep Work', 'Cal Newport', 'Nonfiksi', 3, 'available');

-- Students Table
CREATE TABLE IF NOT EXISTS students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    class VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


INSERT INTO students (student_id, name, class) VALUES
('24001', 'Elsa Amalia', '7A'),
('24002', 'Putra Permata', '7A'),
('24003', 'Vino Handoko', '7A'),
('24004', 'Ivan Wijaya', '7A'),
('24005', 'Fatimah Ramadhani', '7A'),
('24006', 'Naila Maulana', '7A'),
('24007', 'Hana Setiawan', '7A'),
('24008', 'Gibran Nugroho', '7A'),
('24009', 'Gita Sari', '7A'),
('24010', 'Umar Kusuma', '7A'),
('24011', 'Rizki Putri', '7A'),
('24012', 'Eka Hidayat', '7A'),
('24013', 'Hendra Ningrum', '7A'),
('24014', 'Berlian Azzahra', '7A'),
('24015', 'Naila Amalia', '7A'),
('24016', 'Cantika Sari', '7A'),
('24017', 'Zahra Wibowo', '7A'),
('24018', 'Rizki Saputra', '7A'),
('24019', 'Satria Nuraini', '7A'),
('24020', 'Kevin Susanto', '7A'),
('24021', 'Nur Hidayat', '7A'),
('24022', 'Ivan Pratama', '7A'),
('24023', 'Yusuf Maulana', '7A'),
('24024', 'Nabil Handoko', '7A'),
('24025', 'Joko Firmansyah', '7A'),
('24026', 'Aulia Kurniawan', '7B'),
('24027', 'Nabil Hasanah', '7B'),
('24028', 'Putri Susanto', '7B'),
('24029', 'Anisa Safitri', '7B'),
('24030', 'Siti Safitri', '7B'),
('24031', 'Qori Putri', '7B'),
('24032', 'Yusuf Lestari', '7B'),
('24033', 'Nur Prayoga', '7B'),
('24034', 'Hana Pratama', '7B'),
('24035', 'Naila Nugroho', '7B'),
('24036', 'Zahra Fitriani', '7B'),
('24037', 'Zahra Rahma', '7B'),
('24038', 'Zaki Kusuma', '7B'),
('24039', 'Eka Susanto', '7B'),
('24040', 'Wahyu Amalia', '7B'),
('24041', 'Ahmad Putri', '7B'),
('24042', 'Naila Hidayat', '7B'),
('24043', 'Rafi Ningrum', '7B'),
('24044', 'Eka Prayoga', '7B'),
('24045', 'Maulana Ningrum', '7B'),
('24046', 'Gibran Putri', '7B'),
('24047', 'Zahra Setiawan', '7B'),
('24048', 'Tegar Nugroho', '7B'),
('24049', 'Dimas Fitriani', '7B'),
('24050', 'Omar Saputra', '7B'),
('24051', 'Gita Kusuma', '8A'),
('24052', 'Rafi Maulana', '8A'),
('24053', 'Dimas Nugroho', '8A'),
('24054', 'Ivan Prayoga', '8A'),
('24055', 'Joko Kusuma', '8A'),
('24056', 'Nanda Firmansyah', '8A'),
('24057', 'Qori Susanto', '8A'),
('24058', 'Gita Saputra', '8A'),
('24059', 'Bagas Safitri', '8A'),
('24060', 'Naila Saputra', '8A'),
('24061', 'Fatimah Kurniawan', '8A'),
('24062', 'Dafa Hidayat', '8A'),
('24063', 'Joko Saputra', '8A'),
('24064', 'Ivan Susanto', '8A'),
('24065', 'Eka Putri', '8A'),
('24066', 'Zaki Wardani', '8A'),
('24067', 'Aulia Rahma', '8A'),
('24068', 'Fira Santoso', '8A'),
('24069', 'Tegar Putri', '8A'),
('24070', 'Ivan Rahma', '8A'),
('24071', 'Gibran Saputra', '8A'),
('24072', 'Qori Firmansyah', '8A'),
('24073', 'Reza Prayoga', '8A'),
('24074', 'Ilham Hasanah', '8A'),
('24075', 'Vino Susanto', '8A'),
('24076', 'Kevin Wibowo', '8B'),
('24077', 'Putri Nugroho', '8B'),
('24078', 'Tegar Wibowo', '8B'),
('24079', 'Joko Amalia', '8B'),
('24080', 'Dewi Hidayat', '8B'),
('24081', 'Eka Kurniawan', '8B'),
('24082', 'Nur Putri', '8B'),
('24083', 'Fatimah Fitriani', '8B'),
('24084', 'Dewi Hakim', '8B'),
('24085', 'Rizki Hakim', '8B'),
('24086', 'Reza Santoso', '8B'),
('24087', 'Wahyu Safitri', '8B'),
('24088', 'Kevin Pratama', '8B'),
('24089', 'Fatimah Rahma', '8B'),
('24090', 'Putra Wardani', '8B'),
('24091', 'Gita Santoso', '8B'),
('24092', 'Fatimah Hidayat', '8B'),
('24093', 'Bagas Susanto', '8B'),
('24094', 'Kevin Azzahra', '8B'),
('24095', 'Nanda Hakim', '8B'),
('24096', 'Nanda Wibowo', '8B'),
('24097', 'Eka Firmansyah', '8B'),
('24098', 'Ilham Susanto', '8B'),
('24099', 'Aisyah Nuraini', '8B'),
('24100', 'Berlian Sari', '8B'),
('24101', 'Farhan Wibowo', '9A'),
('24102', 'Zaki Permata', '9A'),
('24103', 'Naila Wardani', '9A'),
('24104', 'Eka Pratama', '9A'),
('24105', 'Putra Ramadhani', '9A'),
('24106', 'Naila Prayoga', '9A'),
('24107', 'Aulia Azzahra', '9A'),
('24108', 'Dewi Setiawan', '9A'),
('24109', 'Tegar Handoko', '9A'),
('24110', 'Berlian Rahma', '9A'),
('24111', 'Fatimah Permata', '9A'),
('24112', 'Lutfi Santoso', '9A'),
('24113', 'Farhan Safitri', '9A'),
('24114', 'Umar Wardani', '9A'),
('24115', 'Ilham Setiawan', '9A'),
('24116', 'Dina Wijaya', '9A'),
('24117', 'Umar Handoko', '9A'),
('24118', 'Gita Ramadhani', '9A'),
('24119', 'Dina Sari', '9A'),
('24120', 'Putra Fitriani', '9A'),
('24121', 'Tegar Azzahra', '9A'),
('24122', 'Putri Wijaya', '9A'),
('24123', 'Eka Nuraini', '9A'),
('24124', 'Siti Handoko', '9A'),
('24125', 'Yusuf Rahma', '9A'),
('24126', 'Satria Safitri', '9B'),
('24127', 'Hendra Lestari', '9B'),
('24128', 'Ivan Sari', '9B'),
('24129', 'Gita Hakim', '9B'),
('24130', 'Vino Lestari', '9B'),
('24131', 'Bagas Sari', '9B'),
('24132', 'Nanda Safitri', '9B'),
('24133', 'Gita Firmansyah', '9B'),
('24134', 'Dina Amalia', '9B'),
('24135', 'Lutfi Hasanah', '9B'),
('24136', 'Tegar Fitriani', '9B'),
('24137', 'Lutfi Saputra', '9B'),
('24138', 'Nanda Setiawan', '9B'),
('24139', 'Umar Amalia', '9B'),
('24140', 'Lutfi Setiawan', '9B'),
('24141', 'Salwa Putri', '9B'),
('24142', 'Hendra Azzahra', '9B'),
('24143', 'Farhan Rahma', '9B'),
('24144', 'Siti Wijaya', '9B'),
('24145', 'Nanda Amalia', '9B'),
('24146', 'Gita Azzahra', '9B'),
('24147', 'Bagas Hidayat', '9B'),
('24148', 'Dewi Prayoga', '9B'),
('24149', 'Vino Santoso', '9B'),
('24150', 'Dewi Lestari', '9B');

-- Borrowings Table
CREATE TABLE IF NOT EXISTS borrowings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    book_id INT NOT NULL,
    student_id INT NOT NULL,
    borrow_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(id),
    FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Returns Table
CREATE TABLE IF NOT EXISTS returns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    borrowing_id INT NOT NULL,
    student_id INT NOT NULL,
    return_date DATE NOT NULL,
    fine INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (borrowing_id) REFERENCES borrowings(id),
    FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity Logs Table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings Table
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Indexes
CREATE INDEX idx_student_class ON students(class);
CREATE INDEX idx_book_category ON books(category);
CREATE INDEX idx_borrowing_student ON borrowings(student_id);
CREATE INDEX idx_borrowing_book ON borrowings(book_id);
CREATE INDEX idx_borrowing_status ON borrowings(status);
CREATE INDEX idx_return_student ON returns(student_id);
CREATE INDEX idx_activity_admin ON activity_logs(admin_id);

-- Setelah seed: kebijakan sistem hanya kelas 10 & 11 — hapus siswa kelas 12 beserta relasinya (jika ada)
DELETE r FROM returns r
INNER JOIN students s ON r.student_id = s.id
WHERE s.class LIKE '12.%';

DELETE b FROM borrowings b
INNER JOIN students s ON b.student_id = s.id
WHERE s.class LIKE '12.%';

DELETE FROM students WHERE class LIKE '12.%';
