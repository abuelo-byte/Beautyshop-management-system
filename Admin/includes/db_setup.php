<?php
// Database connection
$host = 'localhost';
$dbname = 'cafeteria-management-system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS food_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            category VARCHAR(100),
            unit_price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS stock_inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            food_item_id INT,
            quantity INT NOT NULL,
            unit VARCHAR(50) NOT NULL,
            min_stock_level INT NOT NULL,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (food_item_id) REFERENCES food_items(id)
        );

        CREATE TABLE IF NOT EXISTS daily_sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            food_item_id INT,
            quantity_sold INT NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            sale_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (food_item_id) REFERENCES food_items(id)
        );

        CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            contact_person VARCHAR(255),
            phone VARCHAR(20),
            email VARCHAR(255),
            address TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS stock_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            food_item_id INT,
            alert_type ENUM('low_stock', 'out_of_stock') NOT NULL,
            alert_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_resolved BOOLEAN DEFAULT FALSE,
            FOREIGN KEY (food_item_id) REFERENCES food_items(id)
        );

        CREATE TABLE IF NOT EXISTS profit_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_date DATE NOT NULL,
            total_sales DECIMAL(10,2) NOT NULL,
            total_cost DECIMAL(10,2) NOT NULL,
            profit DECIMAL(10,2) NOT NULL,
            report_type ENUM('daily', 'weekly', 'monthly') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 