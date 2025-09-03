-- Add AC Parts Support to Job Orders Table
-- This script adds part_id column to job_orders table for better access to AC parts information

USE job_order_system;

-- Add part_id column to job_orders table if it doesn't exist
ALTER TABLE job_orders ADD COLUMN IF NOT EXISTS part_id INT DEFAULT NULL;

-- Add foreign key constraint for part_id to reference ac_parts table
-- First, check if the constraint already exists and drop it if necessary
SET @constraint_name = (
    SELECT CONSTRAINT_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'job_order_system' 
    AND TABLE_NAME = 'job_orders' 
    AND COLUMN_NAME = 'part_id' 
    AND REFERENCED_TABLE_NAME = 'ac_parts'
);

SET @sql = IF(@constraint_name IS NOT NULL, 
    CONCAT('ALTER TABLE job_orders DROP FOREIGN KEY ', @constraint_name), 
    'SELECT "No constraint to drop" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add the foreign key constraint
ALTER TABLE job_orders ADD CONSTRAINT fk_job_orders_part_id 
FOREIGN KEY (part_id) REFERENCES ac_parts(id) ON DELETE SET NULL;

-- Add index for better performance
CREATE INDEX IF NOT EXISTS idx_job_orders_part_id ON job_orders(part_id);

-- Show the updated table structure
DESCRIBE job_orders;

-- Show summary
SELECT 
    'Job Orders table updated successfully' as message,
    'part_id column added with foreign key to ac_parts table' as details;