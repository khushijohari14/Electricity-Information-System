CREATE DATABASE IF NOT EXISTS electricity_db;
USE electricity_db;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bills;
DROP TABLE IF EXISTS meter_readings;
DROP TABLE IF EXISTS meters;
DROP TABLE IF EXISTS consumers;
DROP TABLE IF EXISTS tariffs;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS activity_log;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO admins (name, username, password) VALUES ('Administrator', 'admin', MD5('admin123'));

CREATE TABLE tariffs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    min_units INT NOT NULL,
    max_units INT NOT NULL,
    price_per_unit DECIMAL(8,2) NOT NULL
);
INSERT INTO tariffs (category, min_units, max_units, price_per_unit) VALUES
('Residential', 0, 100, 3.50),
('Residential', 101, 300, 5.00),
('Residential', 301, 999999, 7.50),
('Commercial', 0, 999999, 9.00),
('Industrial', 0, 999999, 6.50);

CREATE TABLE consumers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consumer_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    aadhaar VARCHAR(20),
    address VARCHAR(255),
    city VARCHAR(50),
    state VARCHAR(50),
    pincode VARCHAR(10),
    connection_type ENUM('Residential','Commercial','Industrial') DEFAULT 'Residential',
    password VARCHAR(255),
    status ENUM('Active','Inactive') DEFAULT 'Active',
    registration_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE meters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consumer_id INT NOT NULL,
    meter_number VARCHAR(50) UNIQUE NOT NULL,
    meter_type ENUM('Single Phase','Three Phase','Smart Meter') DEFAULT 'Single Phase',
    connection_date DATE,
    initial_reading DECIMAL(10,2) DEFAULT 0,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (consumer_id) REFERENCES consumers(id) ON DELETE CASCADE
);

CREATE TABLE meter_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meter_id INT NOT NULL,
    previous_reading DECIMAL(10,2) DEFAULT 0,
    current_reading DECIMAL(10,2) NOT NULL,
    units_consumed DECIMAL(10,2) DEFAULT 0,
    reading_date DATE NOT NULL,
    reading_month VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE
);

CREATE TABLE bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consumer_id INT NOT NULL,
    meter_id INT NOT NULL,
    reading_id INT,
    bill_number VARCHAR(50) UNIQUE,
    billing_month VARCHAR(20),
    units_consumed DECIMAL(10,2),
    amount DECIMAL(10,2),
    due_date DATE,
    status ENUM('Pending','Paid','Overdue') DEFAULT 'Pending',
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (consumer_id) REFERENCES consumers(id) ON DELETE CASCADE,
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE
);

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    consumer_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_mode ENUM('Cash','UPI','Bank Transfer','Cheque','Online Portal') DEFAULT 'Cash',
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
    FOREIGN KEY (consumer_id) REFERENCES consumers(id) ON DELETE CASCADE
);

CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(255) NOT NULL,
    type ENUM('consumer','payment','bill','meter','reading','system') DEFAULT 'system',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);