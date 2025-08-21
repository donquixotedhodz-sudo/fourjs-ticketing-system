-- Update Database Schema for Job Order System
-- This script ensures proper customer-job order relationships

USE job_order_system;

-- 1. Ensure customers table exists with proper structure
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Add customer_id column to job_orders if it doesn't exist
ALTER TABLE job_orders ADD COLUMN IF NOT EXISTS customer_id INT;

-- 3. Add foreign key constraint for customer_id (if it doesn't exist)
-- First, drop the constraint if it exists to avoid errors
SET @constraint_name = (
    SELECT CONSTRAINT_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'job_order_system' 
    AND TABLE_NAME = 'job_orders' 
    AND COLUMN_NAME = 'customer_id' 
    AND REFERENCED_TABLE_NAME = 'customers'
);

SET @sql = IF(@constraint_name IS NOT NULL, 
    CONCAT('ALTER TABLE job_orders DROP FOREIGN KEY ', @constraint_name), 
    'SELECT "No constraint to drop" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Now add the foreign key constraint
ALTER TABLE job_orders ADD CONSTRAINT fk_job_orders_customer_id 
FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;

-- 4. Update existing job orders to link with customers
-- This will create customer records for existing job orders and link them
UPDATE job_orders jo 
LEFT JOIN customers c ON c.name = jo.customer_name AND c.phone = jo.customer_phone
SET jo.customer_id = CASE 
    WHEN c.id IS NOT NULL THEN c.id
    ELSE (
        SELECT id FROM customers 
        WHERE name = jo.customer_name AND phone = jo.customer_phone
    )
END
WHERE jo.customer_id IS NULL;

-- 5. Create customer records for job orders that don't have linked customers
INSERT IGNORE INTO customers (name, phone, address, created_at)
SELECT DISTINCT 
    jo.customer_name,
    jo.customer_phone,
    jo.customer_address,
    jo.created_at
FROM job_orders jo
WHERE jo.customer_id IS NULL
AND NOT EXISTS (
    SELECT 1 FROM customers c 
    WHERE c.name = jo.customer_name AND c.phone = jo.customer_phone
);

-- 6. Update job orders to link with newly created customers
UPDATE job_orders jo
SET jo.customer_id = (
    SELECT c.id FROM customers c 
    WHERE c.name = jo.customer_name AND c.phone = jo.customer_phone
)
WHERE jo.customer_id IS NULL;

-- 7. Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_job_orders_customer_id ON job_orders(customer_id);
CREATE INDEX IF NOT EXISTS idx_job_orders_status ON job_orders(status);
CREATE INDEX IF NOT EXISTS idx_customers_name_phone ON customers(name, phone);

-- 8. Ensure aircon_models table has price column
ALTER TABLE aircon_models ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) DEFAULT 0.00;

-- 9. Update aircon_models with sample prices if they don't have prices
UPDATE aircon_models SET price = 15000.00 WHERE price = 0.00 OR price IS NULL;

-- 10. Add additional_fee and discount columns to job_orders if they don't exist
ALTER TABLE job_orders ADD COLUMN IF NOT EXISTS additional_fee DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE job_orders ADD COLUMN IF NOT EXISTS discount DECIMAL(10,2) DEFAULT 0.00;

-- 11. Add created_by column to job_orders if it doesn't exist
ALTER TABLE job_orders ADD COLUMN IF NOT EXISTS created_by INT;

-- 12. Add foreign key for created_by if it doesn't exist
SET @constraint_name = (
    SELECT CONSTRAINT_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'job_order_system' 
    AND TABLE_NAME = 'job_orders' 
    AND COLUMN_NAME = 'created_by' 
    AND REFERENCED_TABLE_NAME = 'admins'
);

SET @sql = IF(@constraint_name IS NOT NULL, 
    CONCAT('ALTER TABLE job_orders DROP FOREIGN KEY ', @constraint_name), 
    'SELECT "No constraint to drop" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE job_orders ADD CONSTRAINT fk_job_orders_created_by 
FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL;

-- 13. Show summary of the update
SELECT 
    'Database schema updated successfully' as message,
    COUNT(*) as total_job_orders,
    COUNT(CASE WHEN customer_id IS NOT NULL THEN 1 END) as linked_job_orders,
    COUNT(CASE WHEN customer_id IS NULL THEN 1 END) as unlinked_job_orders
FROM job_orders;
