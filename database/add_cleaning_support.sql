-- Add cleaning service support to job_orders table
-- Run this SQL in phpMyAdmin or your MySQL client

USE job_order_system;

-- Add cleaning_service_id column
ALTER TABLE job_orders 
ADD COLUMN cleaning_service_id INT NULL AFTER part_id,
ADD FOREIGN KEY (cleaning_service_id) REFERENCES cleaning_services(id) ON DELETE SET NULL;

-- Update service_type enum to include 'cleaning'
ALTER TABLE job_orders 
MODIFY service_type ENUM('installation','repair','survey','cleaning') NOT NULL;

-- Verify the changes
DESCRIBE job_orders;