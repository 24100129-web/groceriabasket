USE groceria_basket;

ALTER TABLE stores
  ADD COLUMN contact_number VARCHAR(60) NULL AFTER address,
  ADD COLUMN email VARCHAR(160) NULL AFTER contact_number,
  ADD COLUMN opening_hours VARCHAR(120) NULL AFTER email,
  ADD COLUMN currency_code CHAR(3) NOT NULL DEFAULT 'PHP' AFTER opening_hours,
  ADD COLUMN timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Manila' AFTER currency_code,
  ADD COLUMN low_stock_alerts TINYINT(1) NOT NULL DEFAULT 1 AFTER timezone;
