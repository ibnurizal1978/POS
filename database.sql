-- Tabel untuk menyimpan daftar fitur premium
CREATE TABLE premium_features (
    id INT PRIMARY KEY AUTO_INCREMENT,
    feature_code VARCHAR(50) NOT NULL UNIQUE,
    feature_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel untuk paket berlangganan
CREATE TABLE subscription_packages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    feature_code VARCHAR(50) NOT NULL,
    package_name VARCHAR(100) NOT NULL,
    duration_days INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (feature_code) REFERENCES premium_features(feature_code)
);

-- Tabel untuk menyimpan langganan premium store
CREATE TABLE store_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    store_id INT NOT NULL,
    feature_code VARCHAR(50) NOT NULL,
    package_id INT NOT NULL,
    start_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP NOT NULL,
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (feature_code) REFERENCES premium_features(feature_code),
    FOREIGN KEY (package_id) REFERENCES subscription_packages(id)
);

-- Insert data fitur premium
INSERT INTO premium_features (feature_code, feature_name, description) VALUES
('SALES_REPORT_PRO', 'Laporan Penjualan Pro', 'Akses penuh ke fitur laporan penjualan termasuk filter dan export Excel');

-- Insert paket berlangganan
INSERT INTO subscription_packages (feature_code, package_name, duration_days, price) VALUES
('SALES_REPORT_PRO', 'Langganan 30 Hari', 30, 10000),
('SALES_REPORT_PRO', 'Langganan 1 Tahun', 365, 100000); 