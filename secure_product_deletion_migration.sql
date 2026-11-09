USE groceria_basket;

ALTER TABLE products
  ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER image_path;

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
