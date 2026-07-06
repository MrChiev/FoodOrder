-- =========================================================
-- Food Ordering System - Database Schema
-- Web and Cloud Technology Final Project
-- =========================================================

CREATE DATABASE IF NOT EXISTS food_ordering_system;
USE food_ordering_system;

-- ---------------------------------------------------------
-- USERS (customer / staff / admin)
-- ---------------------------------------------------------
CREATE TABLE users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    full_name   VARCHAR(100) NOT NULL,
    phone       VARCHAR(20)  DEFAULT NULL,
    address     VARCHAR(255) DEFAULT NULL,
    role        ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
    is_active   TINYINT(1)  NOT NULL DEFAULT 1,
    created_at  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- CATEGORIES
-- ---------------------------------------------------------
CREATE TABLE categories (
    category_id     INT AUTO_INCREMENT PRIMARY KEY,
    category_name   VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- MENU ITEMS
-- ---------------------------------------------------------
CREATE TABLE menu_items (
    item_id       INT AUTO_INCREMENT PRIMARY KEY,
    category_id   INT NOT NULL,
    item_name     VARCHAR(100)  NOT NULL,
    description   TEXT,
    price         DECIMAL(10,2) NOT NULL,
    image         VARCHAR(255)  DEFAULT NULL,
    is_available  TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- CART (persisted per customer so it survives across sessions)
-- ---------------------------------------------------------
CREATE TABLE cart (
    cart_id      INT AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT NOT NULL,
    item_id      INT NOT NULL,
    quantity     INT NOT NULL DEFAULT 1,
    added_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_customer_item (customer_id, item_id),
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES menu_items(item_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- ORDERS
-- ---------------------------------------------------------
CREATE TABLE orders (
    order_id          INT AUTO_INCREMENT PRIMARY KEY,
    customer_id       INT NOT NULL,
    status            ENUM('pending','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending',
    order_type        ENUM('delivery','pickup') NOT NULL DEFAULT 'delivery',
    delivery_address  VARCHAR(255) DEFAULT NULL,
    payment_method    ENUM('cod','card') NOT NULL DEFAULT 'cod',
    total_amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- ORDER ITEMS (snapshot of item name/price at order time)
-- ---------------------------------------------------------
CREATE TABLE order_items (
    order_item_id  INT AUTO_INCREMENT PRIMARY KEY,
    order_id       INT NOT NULL,
    item_id        INT DEFAULT NULL,
    item_name      VARCHAR(100)  NOT NULL,
    unit_price     DECIMAL(10,2) NOT NULL,
    quantity       INT NOT NULL,
    subtotal       DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES menu_items(item_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- ORDER STATUS LOG (audit trail, also useful for the staff queue UI)
-- ---------------------------------------------------------
CREATE TABLE order_status_log (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT NOT NULL,
    old_status  VARCHAR(20) DEFAULT NULL,
    new_status  VARCHAR(20) NOT NULL,
    changed_by  INT DEFAULT NULL,
    changed_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- LOGIN ATTEMPTS (brute-force protection, keyed by username
-- rather than IP so a single account can't be hammered from
-- anywhere). Also self-created by login.php on first run, so
-- existing installs don't need a manual migration.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    username      VARCHAR(50) PRIMARY KEY,
    failed_count  INT NOT NULL DEFAULT 0,
    locked_until  DATETIME DEFAULT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- SEED DATA
-- =========================================================

-- Default accounts (password for all = "password123", stored in plain text)
INSERT INTO users (username, email, password, full_name, role) VALUES
('admin1',  'admin@foodorder.com',  'password123', 'System Admin', 'admin'),
('staff1',  'staff@foodorder.com',  'password123', 'Kitchen Staff', 'staff'),
('customer1','customer@foodorder.com','password123', 'Test Customer', 'customer');

INSERT INTO categories (category_name) VALUES
('Rice & Noodles'), ('Soups'), ('Grilled & BBQ'), ('Drinks'), ('Desserts');

INSERT INTO menu_items (category_id, item_name, description, price, is_available) VALUES
(1, 'Fried Rice with Chicken', 'Classic Cambodian-style fried rice topped with chicken', 3.50, 1),
(1, 'Khmer Noodle Soup (Kuy Teav)', 'Rice noodle soup with pork and herbs', 3.00, 1),
(2, 'Sour Soup (Samlor Machu)', 'Traditional sour fish soup', 4.00, 1),
(3, 'Grilled Pork Skewers', 'Marinated pork skewers grilled over charcoal', 4.50, 1),
(4, 'Iced Lemon Tea', 'Refreshing iced tea with lemon', 1.00, 1),
(5, 'Sticky Rice with Mango', 'Sweet sticky rice served with fresh mango', 2.50, 1);
