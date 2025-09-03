-- Add missing columns to admins table for profile functionality
USE job_order_system;

-- Add name column
ALTER TABLE admins ADD COLUMN IF NOT EXISTS name VARCHAR(100) DEFAULT 'Administrator';

-- Add email column
ALTER TABLE admins ADD COLUMN IF NOT EXISTS email VARCHAR(100) DEFAULT 'admin@example.com';

-- Add phone column
ALTER TABLE admins ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT '';

-- Add profile_picture column
ALTER TABLE admins ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT NULL;

-- Add updated_at column
ALTER TABLE admins ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Update existing admin record with default values
UPDATE admins SET 
    name = 'Administrator - Staff',
    email = 'admin@example.com',
    phone = '09958714112'
WHERE id = 1;
