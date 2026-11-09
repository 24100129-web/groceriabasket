USE groceria_basket;

ALTER TABLE products
  ADD COLUMN supplier VARCHAR(120) NULL AFTER category,
  ADD COLUMN minimum_stock INT NOT NULL DEFAULT 0 AFTER stock_quantity,
  ADD COLUMN expiry_date DATE NULL AFTER minimum_stock,
  ADD COLUMN is_damaged TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN image_path VARCHAR(255) NULL AFTER is_damaged,
  ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER image_path;

ALTER TABLE sales
  ADD COLUMN order_number VARCHAR(32) NULL UNIQUE AFTER id,
  ADD COLUMN checkout_token VARCHAR(64) NULL UNIQUE AFTER order_number,
  ADD COLUMN buyer_name VARCHAR(120) NULL AFTER user_id,
  ADD COLUMN buyer_contact VARCHAR(60) NULL AFTER user_id,
  ADD COLUMN delivery_recipient VARCHAR(120) NULL AFTER buyer_contact,
  ADD COLUMN delivery_contact VARCHAR(60) NULL AFTER delivery_recipient,
  ADD COLUMN delivery_address VARCHAR(700) NULL AFTER delivery_contact,
  ADD COLUMN delivery_instructions VARCHAR(500) NULL AFTER delivery_address,
  ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'Completed' AFTER total_amount,
  ADD COLUMN order_type VARCHAR(24) NOT NULL DEFAULT 'walk-in' AFTER status;

ALTER TABLE users
  ADD COLUMN contact_number VARCHAR(30) NULL AFTER role,
  ADD COLUMN house_street VARCHAR(180) NULL AFTER contact_number,
  ADD COLUMN barangay VARCHAR(100) NULL AFTER house_street,
  ADD COLUMN city VARCHAR(100) NULL AFTER barangay,
  ADD COLUMN province VARCHAR(100) NULL AFTER city,
  ADD COLUMN zip_code VARCHAR(16) NULL AFTER province,
  ADD COLUMN address_details VARCHAR(255) NULL AFTER zip_code;

CREATE TABLE cart_items (
  user_id INT UNSIGNED NOT NULL,
  bundle_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, bundle_id),
  CONSTRAINT fk_cart_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  bundle_id INT UNSIGNED NOT NULL,
  item_name VARCHAR(180) NOT NULL,
  image_path VARCHAR(255) NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sale_items_sale (sale_id),
  CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE deleted_products_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  product_name VARCHAR(160) NOT NULL,
  product_snapshot JSON NOT NULL,
  deletion_reason VARCHAR(1000) NOT NULL,
  deleted_by_user_id INT UNSIGNED NULL,
  deleted_by_name VARCHAR(120) NOT NULL,
  deleted_at DATETIME NOT NULL,
  INDEX idx_deleted_products_time (deleted_at),
  INDEX idx_deleted_products_id (product_id),
  CONSTRAINT fk_deleted_products_user FOREIGN KEY (deleted_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
