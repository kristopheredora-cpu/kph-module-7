-- ============================================================
-- KameraPH (KPH)  |  Module 7: Fulfillment and Logistics
-- Source: KameraPH_ERD_v5  |  MySQL / MariaDB (InnoDB, utf8mb4)
--
-- HOW TO USE: create an empty database for this module on your host,
-- open it in phpMyAdmin, go to the SQL tab, paste this whole file, click Go.
--
-- Tables in this module (5): fulfillment, shipping_quote, fulfillment_item, fulfillment_evidence, fulfillment_status_history
--
-- Columns owned by OTHER modules are plain ID columns (no FOREIGN KEY),
-- because each module has its own database. Get those records from the
-- other module's API instead of joining tables.
--
-- Outside IDs stored in this module:
--   fulfillment.OrderID  ->  Module 6 (orders.OrderID)
--   fulfillment.SellerID  ->  Module 2 (seller_profile.SellerID)
--   fulfillment.PickupAddressID  ->  Module 1 (user_address.AddressID)
--   shipping_quote.CourierID  ->  Module 8 (courier.CourierID)
--   fulfillment_item.OrderItemID  ->  Module 6 (order_item.OrderItemID)
-- ============================================================

-- FULFILLMENT
CREATE TABLE IF NOT EXISTS `fulfillment` (
  FulfillmentID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  OrderID INT UNSIGNED NOT NULL COMMENT 'Module 6: orders.OrderID (plain ID, no FK)',
  SellerID INT UNSIGNED NOT NULL COMMENT 'Module 2: seller_profile.SellerID (plain ID, no FK)',
  PickupAddressID INT UNSIGNED NOT NULL COMMENT 'Module 1: user_address.AddressID (plain ID, no FK)',
  PackageStatus VARCHAR(30) NOT NULL,
  ShipByDate DATETIME NOT NULL,
  PackedAt DATETIME NULL,
  HandoverToCourierAt DATETIME NULL,
  ShippingFee DECIMAL(12,2) NOT NULL,
  FundsStatus VARCHAR(30) NOT NULL,
  BuyerConfirmedAt DATETIME NULL,
  AutoReleaseAt DATETIME NULL,
  ReleasedAt DATETIME NULL,
  Notes TEXT NULL,
  PRIMARY KEY (FulfillmentID),
  KEY idx_fulfillment_OrderID (OrderID),
  KEY idx_fulfillment_SellerID (SellerID),
  KEY idx_fulfillment_PickupAddressID (PickupAddressID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SHIPPING QUOTE
CREATE TABLE IF NOT EXISTS `shipping_quote` (
  QuoteID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  FulfillmentID INT UNSIGNED NOT NULL,
  CourierID INT UNSIGNED NOT NULL COMMENT 'Module 8: courier.CourierID (plain ID, no FK)',
  QuotedFee DECIMAL(12,2) NOT NULL,
  EstimatedDeliveryDays SMALLINT UNSIGNED NOT NULL,
  QuotedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  IsSelected TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (QuoteID),
  KEY idx_shipping_quote_CourierID (CourierID),
  CONSTRAINT fk_shipping_quote_FulfillmentID FOREIGN KEY (FulfillmentID) REFERENCES `fulfillment` (FulfillmentID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FULFILLMENT ITEM
CREATE TABLE IF NOT EXISTS `fulfillment_item` (
  FulfillmentItemID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  FulfillmentID INT UNSIGNED NOT NULL,
  OrderItemID INT UNSIGNED NOT NULL COMMENT 'Module 6: order_item.OrderItemID (plain ID, no FK)',
  Quantity INT UNSIGNED NOT NULL,
  PRIMARY KEY (FulfillmentItemID),
  KEY idx_fulfillment_item_OrderItemID (OrderItemID),
  CONSTRAINT fk_fulfillment_item_FulfillmentID FOREIGN KEY (FulfillmentID) REFERENCES `fulfillment` (FulfillmentID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FULFILLMENT EVIDENCE
CREATE TABLE IF NOT EXISTS `fulfillment_evidence` (
  EvidenceID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  FulfillmentID INT UNSIGNED NOT NULL,
  EvidenceType VARCHAR(100) NOT NULL,
  FileURL VARCHAR(500) NOT NULL,
  UploadedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (EvidenceID),
  CONSTRAINT fk_fulfillment_evidence_FulfillmentID FOREIGN KEY (FulfillmentID) REFERENCES `fulfillment` (FulfillmentID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FULFILLMENT STATUS HISTORY
CREATE TABLE IF NOT EXISTS `fulfillment_status_history` (
  FulfillmentStatusID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  FulfillmentID INT UNSIGNED NOT NULL,
  Status VARCHAR(30) NOT NULL,
  ChangedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ChangedByRole VARCHAR(30) NOT NULL,
  Notes TEXT NULL,
  PRIMARY KEY (FulfillmentStatusID),
  CONSTRAINT fk_fulfillment_status_history_FulfillmentID FOREIGN KEY (FulfillmentID) REFERENCES `fulfillment` (FulfillmentID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
