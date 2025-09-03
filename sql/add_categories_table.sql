-- Add categories table for dynamic category management
USE job_order_system;

-- Create categories table
CREATE TABLE IF NOT EXISTS part_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    category_description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert existing categories from the enum
INSERT INTO part_categories (category_name, category_description) VALUES 
('compressor', 'Compressor parts and components'),
('condenser', 'Condenser coils and related parts'),
('evaporator', 'Evaporator coils and components'),
('filter', 'Air filters and filtration systems'),
('capacitor', 'Electrical capacitors'),
('thermostat', 'Temperature control devices'),
('fan_motor', 'Fan motors and related components'),
('refrigerant', 'Refrigerant gases and chemicals'),
('electrical', 'Electrical components and wiring'),
('other', 'Miscellaneous parts and accessories');

-- Add category_id column to ac_parts table
ALTER TABLE ac_parts ADD COLUMN IF NOT EXISTS category_id INT;

-- Update existing parts to use category_id
UPDATE ac_parts SET category_id = (
    SELECT id FROM part_categories WHERE category_name = ac_parts.part_category
) WHERE category_id IS NULL;

-- Add foreign key constraint
ALTER TABLE ac_parts ADD CONSTRAINT fk_ac_parts_category_id 
FOREIGN KEY (category_id) REFERENCES part_categories(id) ON DELETE SET NULL;

-- Add index for better performance
CREATE INDEX IF NOT EXISTS idx_ac_parts_category_id ON ac_parts(category_id);

SELECT 'Categories table created and data migrated successfully!' as message;