-- Create Estimation Database for Air Conditioning Repair Problems
-- This script adds tables for common repair problems, cleaning prices, and parts prices

USE job_order_system;

-- Create table for common air conditioning problems
CREATE TABLE IF NOT EXISTS ac_problems (
    id INT AUTO_INCREMENT PRIMARY KEY,
    problem_name VARCHAR(255) NOT NULL,
    problem_description TEXT,
    category ENUM('electrical', 'mechanical', 'refrigerant', 'cleaning', 'installation') NOT NULL,
    severity ENUM('minor', 'moderate', 'major') DEFAULT 'moderate',
    estimated_time_hours DECIMAL(4,2) DEFAULT 1.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create table for cleaning services and prices
CREATE TABLE IF NOT EXISTS cleaning_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(255) NOT NULL,
    service_description TEXT,
    service_type ENUM('basic_cleaning', 'deep_cleaning', 'chemical_wash', 'coil_cleaning', 'filter_cleaning') NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    unit_type ENUM('per_unit', 'per_hour', 'per_service') DEFAULT 'per_unit',
    aircon_type ENUM('window', 'split', 'cassette', 'floor_standing', 'all') DEFAULT 'all',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create table for air conditioning parts and prices
CREATE TABLE IF NOT EXISTS ac_parts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    part_name VARCHAR(255) NOT NULL,
    part_code VARCHAR(100),
    part_category ENUM('compressor', 'condenser', 'evaporator', 'filter', 'capacitor', 'thermostat', 'fan_motor', 'refrigerant', 'electrical', 'other') NOT NULL,
    compatible_brands TEXT, -- JSON array of compatible brands
    unit_price DECIMAL(10,2) NOT NULL,
    labor_cost DECIMAL(10,2) DEFAULT 0.00,
    warranty_months INT DEFAULT 12,
    stock_quantity INT DEFAULT 0,
    supplier VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create table for problem-solution mapping
CREATE TABLE IF NOT EXISTS problem_solutions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    problem_id INT NOT NULL,
    solution_type ENUM('cleaning', 'part_replacement', 'repair', 'adjustment') NOT NULL,
    cleaning_service_id INT NULL,
    part_id INT NULL,
    additional_labor_cost DECIMAL(10,2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (problem_id) REFERENCES ac_problems(id) ON DELETE CASCADE,
    FOREIGN KEY (cleaning_service_id) REFERENCES cleaning_services(id) ON DELETE SET NULL,
    FOREIGN KEY (part_id) REFERENCES ac_parts(id) ON DELETE SET NULL
);

-- Create table for estimation templates
CREATE TABLE IF NOT EXISTS estimation_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(255) NOT NULL,
    aircon_type ENUM('window', 'split', 'cassette', 'floor_standing') NOT NULL,
    service_type ENUM('installation', 'repair', 'maintenance', 'cleaning') NOT NULL,
    base_cost DECIMAL(10,2) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample air conditioning problems
INSERT INTO ac_problems (problem_name, problem_description, category, severity, estimated_time_hours) VALUES
('Not Cooling', 'Air conditioner is running but not producing cold air', 'refrigerant', 'major', 2.00),
('Not Turning On', 'Unit does not respond when turned on', 'electrical', 'major', 1.50),
('Water Leaking', 'Water dripping from indoor unit', 'mechanical', 'moderate', 1.00),
('Strange Noise', 'Unusual sounds coming from the unit', 'mechanical', 'moderate', 1.50),
('Bad Odor', 'Unpleasant smell when AC is running', 'cleaning', 'minor', 0.50),
('Weak Airflow', 'Reduced air circulation from vents', 'cleaning', 'minor', 1.00),
('Frequent Cycling', 'Unit turns on and off repeatedly', 'electrical', 'moderate', 2.00),
('Ice Formation', 'Ice buildup on evaporator coils', 'refrigerant', 'moderate', 1.50),
('High Electric Bill', 'Increased energy consumption', 'mechanical', 'moderate', 2.00),
('Remote Not Working', 'Remote control not responding', 'electrical', 'minor', 0.25);

-- Insert sample cleaning services
INSERT INTO cleaning_services (service_name, service_description, service_type, base_price, unit_type, aircon_type) VALUES
('Basic Cleaning', 'Standard cleaning of filters and external parts', 'basic_cleaning', 500.00, 'per_unit', 'all'),
('Deep Cleaning', 'Thorough cleaning including coils and internal components', 'deep_cleaning', 1200.00, 'per_unit', 'all'),
('Chemical Wash', 'Chemical cleaning for heavily soiled units', 'chemical_wash', 2000.00, 'per_unit', 'all'),
('Coil Cleaning', 'Specialized cleaning of evaporator and condenser coils', 'coil_cleaning', 800.00, 'per_unit', 'all'),
('Filter Replacement', 'Replace air filters with new ones', 'filter_cleaning', 300.00, 'per_unit', 'all'),
('Window AC Basic Clean', 'Basic cleaning for window type units', 'basic_cleaning', 400.00, 'per_unit', 'window'),
('Split AC Deep Clean', 'Comprehensive cleaning for split type units', 'deep_cleaning', 1500.00, 'per_unit', 'split'),
('Cassette AC Chemical Wash', 'Chemical wash for cassette type units', 'chemical_wash', 2500.00, 'per_unit', 'cassette');

-- Insert sample air conditioning parts
INSERT INTO ac_parts (part_name, part_code, part_category, compatible_brands, unit_price, labor_cost, warranty_months, stock_quantity, supplier) VALUES
('Compressor 1HP', 'COMP-1HP-001', 'compressor', '["Carrier", "LG", "Samsung"]', 8500.00, 2000.00, 24, 5, 'AC Parts Supplier Inc.'),
('Condenser Coil', 'COND-001', 'condenser', '["All Brands"]', 1500.00, 500.00, 12, 10, 'AC Parts Supplier Inc.'),
('Evaporator Coil', 'EVAP-001', 'evaporator', '["All Brands"]', 1200.00, 500.00, 12, 8, 'AC Parts Supplier Inc.'),
('Air Filter', 'FILT-001', 'filter', '["All Brands"]', 150.00, 50.00, 3, 50, 'Filter Supply Co.'),
('Capacitor 35uF', 'CAP-35UF', 'capacitor', '["All Brands"]', 250.00, 200.00, 6, 20, 'Electrical Parts Co.'),
('Digital Thermostat', 'THERM-DIG-001', 'thermostat', '["All Brands"]', 800.00, 300.00, 12, 15, 'Control Systems Inc.'),
('Fan Motor 1/4HP', 'FAN-025HP', 'fan_motor', '["All Brands"]', 1800.00, 400.00, 18, 12, 'Motor Supply Co.'),
('R410A Refrigerant 1kg', 'REF-R410A-1KG', 'refrigerant', '["All Brands"]', 1200.00, 800.00, 0, 25, 'Refrigerant Supply Co.'),
('Control Board', 'PCB-001', 'electrical', '["Carrier", "Daikin", "Panasonic"]', 2500.00, 600.00, 12, 8, 'Electronics Supply Co.'),
('Drain Pump', 'PUMP-001', 'other', '["All Brands"]', 600.00, 300.00, 12, 10, 'AC Parts Supplier Inc.');

-- Insert sample problem solutions
INSERT INTO problem_solutions (problem_id, solution_type, cleaning_service_id, part_id, additional_labor_cost, notes) VALUES
(1, 'part_replacement', NULL, 8, 500.00, 'Check refrigerant levels and refill if needed'),
(1, 'cleaning', 3, NULL, 0.00, 'Chemical wash may resolve cooling issues'),
(2, 'part_replacement', NULL, 5, 0.00, 'Replace faulty capacitor'),
(2, 'part_replacement', NULL, 9, 0.00, 'Replace control board if capacitor is fine'),
(3, 'cleaning', 4, NULL, 200.00, 'Clean drain line and coils'),
(3, 'part_replacement', NULL, 10, 0.00, 'Install drain pump if gravity drain insufficient'),
(4, 'part_replacement', NULL, 7, 0.00, 'Replace noisy fan motor'),
(4, 'part_replacement', NULL, 1, 0.00, 'Replace compressor if noise is from compressor'),
(5, 'cleaning', 3, NULL, 0.00, 'Chemical wash to remove odor-causing bacteria'),
(6, 'cleaning', 1, NULL, 0.00, 'Basic cleaning to improve airflow'),
(6, 'part_replacement', NULL, 4, 0.00, 'Replace clogged air filter');

-- Insert sample estimation templates
INSERT INTO estimation_templates (template_name, aircon_type, service_type, base_cost, description, is_active) VALUES
('Window AC Installation', 'window', 'installation', 1500.00, 'Standard installation for window type AC unit', TRUE),
('Split AC Installation', 'split', 'installation', 3000.00, 'Standard installation for split type AC unit', TRUE),
('Window AC Repair', 'window', 'repair', 800.00, 'Basic repair service for window type AC', TRUE),
('Split AC Repair', 'split', 'repair', 1200.00, 'Basic repair service for split type AC', TRUE),
('Preventive Maintenance', 'all', 'maintenance', 600.00, 'Regular maintenance service', TRUE),
('Deep Cleaning Service', 'all', 'cleaning', 1200.00, 'Comprehensive cleaning service', TRUE);

-- Create indexes for better performance
CREATE INDEX idx_ac_problems_category ON ac_problems(category);
CREATE INDEX idx_cleaning_services_type ON cleaning_services(service_type);
CREATE INDEX idx_ac_parts_category ON ac_parts(part_category);
CREATE INDEX idx_problem_solutions_problem ON problem_solutions(problem_id);
CREATE INDEX idx_estimation_templates_type ON estimation_templates(service_type);

-- Create view for complete estimation details
CREATE OR REPLACE VIEW estimation_details AS
SELECT 
    p.id as problem_id,
    p.problem_name,
    p.category as problem_category,
    p.severity,
    ps.solution_type,
    cs.service_name as cleaning_service,
    cs.base_price as cleaning_price,
    ap.part_name,
    ap.unit_price as part_price,
    ap.labor_cost as part_labor_cost,
    ps.additional_labor_cost,
    (COALESCE(cs.base_price, 0) + COALESCE(ap.unit_price, 0) + COALESCE(ap.labor_cost, 0) + COALESCE(ps.additional_labor_cost, 0)) as total_cost
FROM ac_problems p
LEFT JOIN problem_solutions ps ON p.id = ps.problem_id
LEFT JOIN cleaning_services cs ON ps.cleaning_service_id = cs.id
LEFT JOIN ac_parts ap ON ps.part_id = ap.id;

SELECT 'Estimation database created successfully!' as message;
SELECT COUNT(*) as total_problems FROM ac_problems;
SELECT COUNT(*) as total_cleaning_services FROM cleaning_services;
SELECT COUNT(*) as total_parts FROM ac_parts;
SELECT COUNT(*) as total_solutions FROM problem_solutions;