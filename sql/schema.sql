-- Users Table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,  -- Use SERIAL for auto-increment
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
	role VARCHAR(20) DEFAULT 'user', -- Role column to differentiate user types
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

select * from tasks_with_category

CREATE MATERIALIZED VIEW weekly_task_completion AS
SELECT 
    u.id AS user_id,
    u.username,
    COUNT(t.id) AS completed_tasks_last_week
FROM users u
LEFT JOIN tasks t ON u.id = t.user_id
WHERE t.status = TRUE AND t.updated_at >= NOW() - INTERVAL '7 days'
GROUP BY u.id, u.username;

-- Categories Table
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,  -- Use SERIAL for auto-increment
    name VARCHAR(100) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tasks Table
CREATE TABLE tasks (
    id SERIAL PRIMARY KEY,  -- Use SERIAL for auto-increment
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    user_id INT NOT NULL,
    status BOOLEAN DEFAULT FALSE,  -- Use BOOLEAN for status (TRUE/FALSE)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

select * from tasks

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

CREATE OR REPLACE FUNCTION get_user_overdue_tasks(user_id INTEGER)
RETURNS TABLE (
    task_id INTEGER,
    task_title TEXT,
    category_name TEXT,
    days_overdue INTEGER
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        t.id,
        t.task_title,
        c.name AS category_name,
        CURRENT_DATE - t.task_deadline AS days_overdue
    FROM tasks t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = user_id
      AND t.task_deadline < CURRENT_DATE
      AND t.is_complete = FALSE
    ORDER BY t.task_deadline ASC;
END;
$$ LANGUAGE plpgsql;

CREATE MATERIALIZED VIEW category_task_stats AS
SELECT 
    u.id AS user_id,
    u.username AS user_name,
    c.id AS category_id,
    c.name AS category_name,
    COUNT(CASE WHEN t.status = TRUE THEN 1 END) AS completed_tasks,
    COUNT(CASE WHEN t.status = FALSE THEN 1 END) AS pending_tasks
FROM 
    users u
LEFT JOIN 
    tasks t ON u.id = t.user_id
LEFT JOIN 
    categories c ON t.category_id = c.id
GROUP BY 
    u.id, u.username, c.id, c.name;

select * from public.category_task_stats

REFRESH MATERIALIZED VIEW category_task_stats;