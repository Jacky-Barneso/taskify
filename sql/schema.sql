-- Users Table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,  -- Use SERIAL for auto-increment
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
	role VARCHAR(20) DEFAULT 'user', -- Role column to differentiate user types
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- select * from tasks
-- INSERT INTO categories (name, user_id) VALUES ('Work', 1);
-- INSERT INTO categories (name, user_id) VALUES ('Personal', 1);

ALTER TABLE users
ADD COLUMN role VARCHAR(50) DEFAULT 'user';

-- Categories Table
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,  -- Use SERIAL for auto-increment
    name VARCHAR(100) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE tasks (
    id SERIAL PRIMARY KEY,  -- Auto-increment ID
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    user_id INT NOT NULL,
    status BOOLEAN DEFAULT FALSE,  -- Status: TRUE/FALSE
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deadline TIMESTAMP,  -- New deadline column
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE tasks
ADD COLUMN deadline TIMESTAMP;


-- Activity Logs Table
CREATE TABLE activity_logs (
    id SERIAL PRIMARY KEY,  -- Use SERIAL for auto-increment
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT,
    action_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE VIEW task_summary_by_user AS
SELECT 
    u.id AS user_id,
    u.username,
    COUNT(CASE WHEN t.status = TRUE THEN 1 END) AS completed_tasks,
    COUNT(CASE WHEN t.status = FALSE THEN 1 END) AS pending_tasks
FROM users u
LEFT JOIN tasks t ON u.id = t.user_id
GROUP BY u.id, u.username;

CREATE VIEW tasks_with_category AS
SELECT 
    t.id AS task_id,
    t.title,
    t.description,
    t.status,
    c.name AS category_name,
    t.user_id
FROM tasks t
LEFT JOIN categories c ON t.category_id = c.id;