-- Users Table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,  -- Use SERIAL for auto-increment
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- select * from tasks
-- INSERT INTO categories (name, user_id) VALUES ('Work', 1);
-- INSERT INTO categories (name, user_id) VALUES ('Personal', 1);


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
