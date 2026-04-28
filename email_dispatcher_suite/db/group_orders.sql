-- Create table for group orders
CREATE TABLE IF NOT EXISTS `group_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_group_orders_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for group order items
CREATE TABLE IF NOT EXISTS `group_order_items` (
  `group_order_id` INT NOT NULL,
  `group_id` INT NOT NULL,
  `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_order_id`, `group_id`),
  CONSTRAINT `fk_goi_group_order` FOREIGN KEY (`group_order_id`) REFERENCES `group_orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_goi_group` FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
