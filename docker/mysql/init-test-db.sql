-- Create test database alongside primary dev database
CREATE DATABASE IF NOT EXISTS `inventory_dashboard_test`;
GRANT ALL PRIVILEGES ON `inventory_dashboard_test`.* TO 'root'@'%';
FLUSH PRIVILEGES;
