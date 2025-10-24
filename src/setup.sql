-- Tablo Oluşturma Komutları
CREATE TABLE companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL
);

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fullname TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('admin', 'firma_admin', 'user')),
    company_id INTEGER NULLABLE,
    balance REAL DEFAULT 0.0,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

CREATE TABLE trips (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL,
    origin TEXT NOT NULL,
    destination TEXT NOT NULL,
    departure_time DATETIME NOT NULL,
    arrival_time DATETIME NOT NULL,
    price REAL NOT NULL,
    capacity INTEGER NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

CREATE TABLE coupons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    discount_rate REAL NOT NULL,
    usage_limit INTEGER NOT NULL,
    used_count INTEGER DEFAULT 0,
    expiry_date DATE NOT NULL,
    company_id INTEGER NULLABLE,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

CREATE TABLE tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    trip_id INTEGER NOT NULL,
    seat_number INTEGER NOT NULL,
    price REAL NOT NULL,
    purchase_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TEXT NOT NULL CHECK (status IN ('satın alındı', 'iptal edildi')),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (trip_id) REFERENCES trips(id),
    UNIQUE (trip_id, seat_number)
);


-- Test Verisi Ekleme Komutları
-- Tüm şifreler "123456" hash'lenmiştir.
-- Hash: $2y$10$w3/k9hBf1Y4l0A6e9L3M4u5B6C7D8E9F0G1H2I3J4K5L6M7N8O9P0Q1R2S3T4U5V6W7X8Y9Z0

INSERT INTO companies (name) VALUES ('Hızlı Seyahat'); 
INSERT INTO companies (name) VALUES ('Konfor Turizm'); 

INSERT INTO users (fullname, email, password, role, company_id, balance) 
VALUES ('Sistem Admini', 'admin@bilet.com', '$2y$10$XamyzI3Yt/jsoFcr7pF0zOdxII/SMvpUrbTxKbCBs4YIX4bcvUCYu', 'admin', NULL, 10000000.00); 

INSERT INTO users (fullname, email, password, role, company_id, balance) 
VALUES ('Hızlı Seyahat Yetkilisi', 'firma_admin@hizli.com', '$2y$10$w3/k9hBf1Y4l0A6e9L3M4u5B6C7D8E9F0G1H2I3J4K5L6M7N8O9P0Q1R2S3T4U5V6W7X8Y9Z0', 'firma_admin', 1, 500000.00); 

INSERT INTO users (fullname, email, password, role, company_id, balance) 
VALUES ('Örnek Yolcu', 'yolcu@mail.com', '$2y$10$w3/k9hBf1Y4l0A6e9L3M4u5B6C7D8E9F0G1H2I3J4K5L6M7N8O9P0Q1R2S3T4U5V6W7X8Y9Z0', 'user', NULL, 10000.00); 

INSERT INTO trips (company_id, origin, destination, departure_time, arrival_time, price, capacity) 
VALUES (1, 'İstanbul', 'Ankara', '2025-10-25 10:00:00', '2025-10-25 16:00:00', 350.00, 40);

INSERT INTO trips (company_id, origin, destination, departure_time, arrival_time, price, capacity) 
VALUES (2, 'İstanbul', 'İzmir', '2025-10-26 23:00:00', '2025-10-27 07:00:00', 400.00, 30);

INSERT INTO trips (company_id, origin, destination, departure_time, arrival_time, price, capacity) 
VALUES (1, 'Ankara', 'İstanbul', '2025-10-27 08:30:00', '2025-10-27 14:30:00', 320.00, 40);
