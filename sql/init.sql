-- Create database tables for the mini ORM example

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    age INT DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Profiles table (one-to-one with users)
CREATE TABLE IF NOT EXISTS profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    bio TEXT,
    avatar VARCHAR(255),
    website VARCHAR(255),
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Posts table (one-to-many with users)
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    status ENUM('draft', 'published') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Comments table (one-to-many with posts and users)
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    status ENUM('approved', 'pending', 'spam') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Tags table
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- User roles pivot table (many-to-many)
CREATE TABLE IF NOT EXISTS user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_role (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Post tags pivot table (many-to-many)
CREATE TABLE IF NOT EXISTS post_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_post_tag (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Insert sample data
INSERT INTO users (name, email, password, age, status) VALUES
('Ali Veli', 'ali@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 25, 'active'),
('Ayşe Demir', 'ayse@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 30, 'active'),
('Mehmet Kaya', 'mehmet@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 22, 'inactive');

INSERT INTO profiles (user_id, bio, avatar, website, location) VALUES
(1, 'Software developer passionate about PHP and databases', 'avatar1.jpg', 'https://ali.dev', 'Istanbul'),
(2, 'UI/UX designer with 5 years of experience', 'avatar2.jpg', 'https://ayse.design', 'Ankara'),
(3, 'Full-stack developer and tech enthusiast', 'avatar3.jpg', 'https://mehmet.tech', 'Izmir');

INSERT INTO posts (user_id, title, content, status, published_at) VALUES
(1, 'Getting Started with PHP ORM', 'This is a comprehensive guide to building your own ORM...', 'published', NOW()),
(1, 'Advanced Database Relationships', 'Understanding complex relationships in databases...', 'draft', NULL),
(2, 'Design Patterns in Modern Web Development', 'Exploring various design patterns...', 'published', NOW()),
(3, 'Building Scalable Applications', 'Tips and tricks for scalable app development...', 'published', NOW());

INSERT INTO comments (post_id, user_id, content, status) VALUES
(1, 2, 'Great article! Very helpful for beginners.', 'approved'),
(1, 3, 'Thanks for sharing this. Looking forward to more.', 'approved'),
(3, 1, 'Excellent insights on design patterns.', 'approved'),
(4, 2, 'Scalability is crucial. Nice points covered.', 'approved');

INSERT INTO roles (name, description) VALUES
('admin', 'Administrator with full access'),
('editor', 'Can create and edit content'),
('user', 'Regular user with basic access');

INSERT INTO tags (name, slug) VALUES
('PHP', 'php'),
('Database', 'database'),
('ORM', 'orm'),
('Design', 'design'),
('Development', 'development'),
('Tutorial', 'tutorial');

INSERT INTO user_roles (user_id, role_id) VALUES
(1, 1), -- Ali is admin
(1, 2), -- Ali is also editor
(2, 2), -- Ayşe is editor
(3, 3); -- Mehmet is user

INSERT INTO post_tags (post_id, tag_id) VALUES
(1, 1), -- PHP
(1, 2), -- Database
(1, 3), -- ORM
(1, 6), -- Tutorial
(2, 2), -- Database
(3, 4), -- Design
(3, 5), -- Development
(4, 5), -- Development
(4, 6); -- Tutorial
