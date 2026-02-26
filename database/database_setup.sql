-- Database creation, security authentication

-- Create a database for our systerm
CREATE DATABASE IF NOT EXISTS it490_db;
-- Switch to database
USE it490_db;

-- Create table for users, storing ID, username, hashed password, and session ID for security
CREATE TABLE IF NOT EXISTS users (
	id INT AUTO_INCREMENT PRIMARY KEY,
	username VARCHAR(50) UNIQUE NOT NULL,
	password_hash VARCHAR(255) NOT NULL,
	session_id VARCHAR(255) DEFAULT NULL
);

-- Create localhost user that is the sole access to the db for PHP script
CREATE USER IF NOT EXISTS 'backendVM'@'localhost' IDENTIFIED BY 'password';
-- Grant that user all privileges on it490_db, as root will not be used for security
GRANT ALL PRIVELAGES ON it490_db.* TO 'backendVM'@'localhost';
FLUSH PRIVILEGES
EXIT;
