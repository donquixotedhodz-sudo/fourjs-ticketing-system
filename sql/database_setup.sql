-- Create database if not exists
CREATE DATABASE IF NOT EXISTS job_order_system;
USE job_order_system;

-- Create admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create technicians table
CREATE TABLE IF NOT EXISTS technicians (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create aircon_models table
CREATE TABLE IF NOT EXISTS aircon_models (
    id INT PRIMARY KEY AUTO_INCREMENT,
    brand VARCHAR(50) NOT NULL,
    model_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create job_orders table
CREATE TABLE IF NOT EXISTS job_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_order_number VARCHAR(20) UNIQUE NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_address TEXT NOT NULL,
    service_type ENUM('installation', 'repair') NOT NULL,
    aircon_model_id INT,
    assigned_technician_id INT,
    due_date DATE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (aircon_model_id) REFERENCES aircon_models(id),
    FOREIGN KEY (assigned_technician_id) REFERENCES technicians(id)
);

-- Add customers table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add customer_id to job_orders
ALTER TABLE job_orders ADD COLUMN IF NOT EXISTS customer_id INT;
ALTER TABLE job_orders ADD CONSTRAINT fk_customer_id FOREIGN KEY (customer_id) REFERENCES customers(id);

-- Insert default admin user (username: admin, password: admin123)
INSERT INTO admins (username, password) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert sample technician (username: tech1, password: tech123)
INSERT INTO technicians (username, password, name, phone) VALUES 
('tech1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', '09123456789');

-- Insert sample aircon models
INSERT INTO aircon_models (brand, model_name) VALUES 
('Carrier', 'Window Type 1.0HP'),
('Panasonic', 'Split Type 1.5HP'),
('LG', 'Inverter Split Type 2.0HP'),
('Samsung', 'Window Type 1.5HP'),
('Daikin', 'Split Type 1.0HP'); 