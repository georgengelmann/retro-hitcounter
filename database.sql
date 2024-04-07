CREATE TABLE visitor_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE KEY,
    visit_timestamp DATETIME NOT NULL,
    user_agent VARCHAR(255),
    browser_language VARCHAR(255),
    headers TEXT
);
CREATE TABLE visitor_count (
    count INT NOT NULL
);
INSERT INTO visitor_count (count) VALUES (0);
