<?php
/**
 * Profile Database Tables Setup
 * Creates necessary tables if they don't exist
 */

function ensureProfileTablesExist($conn) {
    // Education table
    $create_education = "CREATE TABLE IF NOT EXISTS user_education (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        degree VARCHAR(255) NOT NULL,
        field_of_study VARCHAR(255) NOT NULL,
        institution VARCHAR(255) NOT NULL,
        start_year INT NOT NULL,
        end_year INT NOT NULL,
        gpa VARCHAR(10),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_education);

    // Experience table
    $create_experience = "CREATE TABLE IF NOT EXISTS user_experience (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        job_title VARCHAR(255) NOT NULL,
        company VARCHAR(255) NOT NULL,
        location VARCHAR(255),
        start_date DATE NOT NULL,
        end_date DATE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_experience);

    // Skills table
    $create_skills = "CREATE TABLE IF NOT EXISTS user_skills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        skill_name VARCHAR(255) NOT NULL,
        skill_category VARCHAR(100),
        skill_level INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_skills);

    // Check and add missing columns
    $check_category = "SHOW COLUMNS FROM user_skills LIKE 'skill_category'";
    if ($conn->query($check_category)->num_rows == 0) {
        $conn->query("ALTER TABLE user_skills ADD COLUMN skill_category VARCHAR(100) AFTER skill_name");
    }

    $check_level = "SHOW COLUMNS FROM user_skills LIKE 'skill_level'";
    if ($conn->query($check_level)->num_rows == 0) {
        $conn->query("ALTER TABLE user_skills ADD COLUMN skill_level INT NOT NULL DEFAULT 1 AFTER skill_category");
    }

    // Add profile columns to applicants table
    $check_picture = "SHOW COLUMNS FROM applicants LIKE 'profile_picture'";
    if ($conn->query($check_picture)->num_rows == 0) {
        $conn->query("ALTER TABLE applicants ADD COLUMN profile_picture VARCHAR(255) NULL");
    }

    $check_phone = "SHOW COLUMNS FROM applicants LIKE 'contact_number'";
    if ($conn->query($check_phone)->num_rows == 0) {
        $conn->query("ALTER TABLE applicants ADD COLUMN contact_number VARCHAR(20) NULL");
    }

    $check_address = "SHOW COLUMNS FROM applicants LIKE 'address'";
    if ($conn->query($check_address)->num_rows == 0) {
        $conn->query("ALTER TABLE applicants ADD COLUMN address TEXT NULL");
    }
}
?>
