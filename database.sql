-- Create Database
CREATE DATABASE IF NOT EXISTS crime_reporting_system;
USE crime_reporting_system;

-- Create Tables with careful column checking
-- First, create the users table if it doesn't exist
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'agent', 'user') NOT NULL DEFAULT 'user',
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Check if profile_image column exists, add it if it doesn't
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'crime_reporting_system' 
AND TABLE_NAME = 'users' 
AND COLUMN_NAME = 'profile_image';

SET @add_column_sql = IF(@column_exists = 0, 
                         'ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT "default.jpg" AFTER address', 
                         'SELECT "Column already exists"');
PREPARE stmt FROM @add_column_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create agents table if it doesn't exist
CREATE TABLE IF NOT EXISTS agents (
    agent_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    badge_number VARCHAR(20) NOT NULL UNIQUE,
    department VARCHAR(100) NOT NULL,
    `rank` VARCHAR(50),
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create crime_types table if it doesn't exist
CREATE TABLE IF NOT EXISTS crime_types (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL
);

-- Create status_codes table if it doesn't exist
CREATE TABLE IF NOT EXISTS status_codes (
    status_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
);

-- Create incidents table if it doesn't exist
CREATE TABLE IF NOT EXISTS incidents (
    incident_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    crime_type_id INT NOT NULL,
    status_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255) NOT NULL,
    incident_date DATETIME NOT NULL,
    reported_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_anonymous BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (crime_type_id) REFERENCES crime_types(type_id),
    FOREIGN KEY (status_id) REFERENCES status_codes(status_id),
    INDEX (incident_date),
    INDEX (status_id)
);

-- Create incident_agent table if it doesn't exist
CREATE TABLE IF NOT EXISTS incident_agent (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    agent_id INT NOT NULL,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (incident_id) REFERENCES incidents(incident_id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(agent_id),
    UNIQUE KEY (incident_id, agent_id)
);

-- Create incident_updates table if it doesn't exist
CREATE TABLE IF NOT EXISTS incident_updates (
    update_id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    user_id INT NOT NULL,
    status_id INT NOT NULL,
    update_text TEXT NOT NULL,
    update_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(incident_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (status_id) REFERENCES status_codes(status_id)
);

-- Insert sample data carefully
-- Status codes - Using INSERT IGNORE to prevent duplicate key errors
INSERT IGNORE INTO status_codes (name, description) VALUES 
('New', 'Newly reported incident, not yet reviewed'),
('Under Investigation', 'Incident is being actively investigated'),
('Pending Evidence', 'Waiting for additional evidence or information'),
('Assigned', 'Assigned to an agent but investigation not yet started'),
('Closed', 'Investigation completed and case closed');

-- Crime types - Using INSERT IGNORE to prevent duplicate key errors
INSERT IGNORE INTO crime_types (name, description, severity) VALUES 
('Theft', 'Theft of property', 'medium'),
('Assault', 'Physical assault or attack', 'high'),
('Vandalism', 'Damage to property', 'low'),
('Fraud', 'Deception for financial gain', 'medium'),
('Breaking and Entering', 'Illegal entry into a property', 'high'),
('Cybercrime', 'Computer-related crimes', 'medium'),
('Harassment', 'Repeated unwanted contact or behavior', 'medium');

-- Insert admin user if it doesn't exist
INSERT IGNORE INTO users (username, password, email, full_name, role, phone, address, profile_image) VALUES 
('admin', '$2y$10$8KGQFEABPKh6WGHm4JUwVeqyEkqNXM7ykE0fFzQGQwUkz4XiG2YLG', 'admin@example.com', 'System Administrator', 'admin', '123-456-7890', '123 Admin St, Admin City', 'admin_profile.jpg');

-- Insert agent users if they don't exist
INSERT IGNORE INTO users (username, password, email, full_name, role, phone, address, profile_image) VALUES 
('agent1', '$2y$10$8KGQFEABPKh6WGHm4JUwVeqyEkqNXM7ykE0fFzQGQwUkz4XiG2YLG', 'agent1@example.com', 'John Smith', 'agent', '123-456-7891', '456 Agent Ave, Agent City', 'agent1_profile.jpg');

INSERT IGNORE INTO users (username, password, email, full_name, role, phone, address, profile_image) VALUES 
('agent2', '$2y$10$8KGQFEABPKh6WGHm4JUwVeqyEkqNXM7ykE0fFzQGQwUkz4XiG2YLG', 'agent2@example.com', 'Sarah Johnson', 'agent', '123-456-7893', '789 Agent Blvd, Agent Town', 'agent2_profile.jpg');

-- Insert regular users if they don't exist
INSERT IGNORE INTO users (username, password, email, full_name, role, phone, address, profile_image) VALUES 
('user1', '$2y$10$8KGQFEABPKh6WGHm4JUwVeqyEkqNXM7ykE0fFzQGQwUkz4XiG2YLG', 'user1@example.com', 'Jane Doe', 'user', '123-456-7892', '789 User Blvd, User City', 'user1_profile.jpg');

INSERT IGNORE INTO users (username, password, email, full_name, role, phone, address, profile_image) VALUES 
('user2', '$2y$10$8KGQFEABPKh6WGHm4JUwVeqyEkqNXM7ykE0fFzQGQwUkz4XiG2YLG', 'user2@example.com', 'Robert Brown', 'user', '123-456-7894', '101 User St, User Town', 'user2_profile.jpg');

-- Insert agent records if they don't exist
-- First, get the user_id for agent1
SET @agent1_user_id = NULL;
SELECT user_id INTO @agent1_user_id FROM users WHERE username = 'agent1' LIMIT 1;

-- Only insert if we found the user_id and the badge number doesn't exist
INSERT IGNORE INTO agents (user_id, badge_number, department, `rank`)
SELECT @agent1_user_id, 'A12345', 'Criminal Investigation', 'Detective'
FROM dual
WHERE @agent1_user_id IS NOT NULL 
AND NOT EXISTS (SELECT 1 FROM agents WHERE badge_number = 'A12345');

-- Get the user_id for agent2
SET @agent2_user_id = NULL;
SELECT user_id INTO @agent2_user_id FROM users WHERE username = 'agent2' LIMIT 1;

-- Only insert if we found the user_id and the badge number doesn't exist
INSERT IGNORE INTO agents (user_id, badge_number, department, `rank`)
SELECT @agent2_user_id, 'B67890', 'Cyber Crimes Unit', 'Senior Agent'
FROM dual
WHERE @agent2_user_id IS NOT NULL 
AND NOT EXISTS (SELECT 1 FROM agents WHERE badge_number = 'B67890');


