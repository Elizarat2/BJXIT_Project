
DROP DATABASE IF EXISTS bjxit_db;
CREATE DATABASE bjxit_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bjxit_db;

-- Tabla de usuarios
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'sales') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- Tabla de productos
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_key VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    stock INT NOT NULL DEFAULT 0,
    min_stock INT DEFAULT 5,
    price DECIMAL(10,2),
    category VARCHAR(50),
    supplier VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    
    INDEX idx_product_key (product_key),
    INDEX idx_name (name),
    INDEX idx_category (category),
    INDEX idx_stock (stock),
    CONSTRAINT chk_stock_positive CHECK (stock >= 0)
);

-- Tabla de pedidos/órdenes
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(100) NOT NULL,
    client_email VARCHAR(100),
    client_phone VARCHAR(20),
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2),
    total_price DECIMAL(10,2),
    user_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    
    INDEX idx_client_name (client_name),
    INDEX idx_order_date (order_date),
    INDEX idx_status (status),
    INDEX idx_user_id (user_id),
    INDEX idx_product_id (product_id),
    CONSTRAINT chk_quantity_positive CHECK (quantity > 0)
);

-- Tabla de auditoría de productos
CREATE TABLE product_audit (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    changed_field VARCHAR(50) NOT NULL,
    old_value VARCHAR(255),
    new_value VARCHAR(255),
    changed_by INT,
    change_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    
    INDEX idx_product_id (product_id),
    INDEX idx_change_date (change_date),
    INDEX idx_changed_by (changed_by)
);

-- Tabla de sesiones de usuario
CREATE TABLE user_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

-- Procedimiento para registrar un nuevo usuario
DELIMITER //
CREATE PROCEDURE sp_register_user(
    IN p_username VARCHAR(50),
    IN p_email VARCHAR(100),
    IN p_password VARCHAR(255),
    IN p_role ENUM('admin', 'staff', 'sales')
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION 
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    INSERT INTO users (username, email, password, role) 
    VALUES (p_username, p_email, p_password, p_role);
    
    SELECT LAST_INSERT_ID() AS user_id, 'Usuario registrado exitosamente' AS message;
    
    COMMIT;
END //
DELIMITER ;

-- Procedimiento para autenticar usuario
DELIMITER //
CREATE PROCEDURE sp_authenticate_user(
    IN p_username VARCHAR(50),
    IN p_password VARCHAR(255)
)
BEGIN
    SELECT 
        user_id,
        username,
        email,
        role,
        password,
        is_active
    FROM users 
    WHERE (username = p_username OR email = p_username) 
    AND is_active = TRUE;
END //
DELIMITER ;

-- Procedimiento para actualizar el stock de un producto
DELIMITER //
CREATE PROCEDURE sp_update_product_stock(
    IN p_product_id INT,
    IN p_quantity_change INT,
    IN p_user_id INT
)
BEGIN
    DECLARE current_stock INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION 
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    SELECT stock INTO current_stock FROM products WHERE product_id = p_product_id;
    
    IF current_stock IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Producto no encontrado';
    END IF;
    
    IF current_stock + p_quantity_change < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay suficiente stock disponible';
    ELSE
        UPDATE products 
        SET stock = stock + p_quantity_change 
        WHERE product_id = p_product_id;
        
        -- Registrar el cambio en auditoría
        INSERT INTO product_audit (product_id, changed_field, old_value, new_value, changed_by)
        VALUES (p_product_id, 'stock', current_stock, current_stock + p_quantity_change, p_user_id);
    END IF;
    
    COMMIT;
END //
DELIMITER ;

-- Procedimiento para crear una nueva orden
DELIMITER //
CREATE PROCEDURE sp_create_order(
    IN p_client_name VARCHAR(100),
    IN p_client_email VARCHAR(100),
    IN p_client_phone VARCHAR(20),
    IN p_product_id INT,
    IN p_quantity INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_stock INT;
    DECLARE v_price DECIMAL(10,2);
    DECLARE v_total DECIMAL(10,2);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION 
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Verificar stock y obtener precio
    SELECT stock, IFNULL(price, 0) INTO v_stock, v_price 
    FROM products 
    WHERE product_id = p_product_id AND is_active = TRUE;
    
    IF v_stock IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Producto no encontrado o inactivo';
    END IF;
    
    IF v_stock < p_quantity THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock insuficiente';
    END IF;
    
    SET v_total = v_price * p_quantity;
    
    -- Crear la orden
    INSERT INTO orders (client_name, client_email, client_phone, product_id, quantity, unit_price, total_price, user_id)
    VALUES (p_client_name, p_client_email, p_client_phone, p_product_id, p_quantity, v_price, v_total, p_user_id);
    
    -- Actualizar stock
    UPDATE products SET stock = stock - p_quantity WHERE product_id = p_product_id;
    
    SELECT LAST_INSERT_ID() AS order_id, 'Orden creada exitosamente' AS message;
    
    COMMIT;
END //
DELIMITER ;

-- Procedimiento para obtener reporte de inventario
DELIMITER //
CREATE PROCEDURE sp_get_inventory_report()
BEGIN
    SELECT 
        p.product_id, 
        p.product_key, 
        p.name, 
        p.stock,
        p.min_stock,
        COUNT(o.order_id) AS total_orders,
        SUM(CASE WHEN o.order_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 ELSE 0 END) AS recent_orders,
        SUM(CASE WHEN o.order_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN o.quantity ELSE 0 END) AS recent_quantity,
        CASE 
            WHEN p.stock <= 0 THEN 'SIN_STOCK'
            WHEN p.stock <= p.min_stock THEN 'STOCK_BAJO' 
            ELSE 'STOCK_OK' 
        END AS stock_status
    FROM 
        products p
    LEFT JOIN 
        orders o ON p.product_id = o.product_id AND o.status != 'cancelled'
    WHERE 
        p.is_active = TRUE
    GROUP BY 
        p.product_id, p.product_key, p.name, p.stock, p.min_stock
    ORDER BY 
        p.stock ASC, p.name;
END //
DELIMITER ;

-- ===================================================================
-- VISTAS
-- ===================================================================

-- Vista para productos bajos en stock
CREATE VIEW vw_low_stock_products AS
SELECT 
    p.*,
    CASE 
        WHEN p.stock <= 0 THEN 'SIN_STOCK'
        WHEN p.stock <= p.min_stock THEN 'STOCK_BAJO'
        ELSE 'STOCK_OK'
    END AS stock_status
FROM products p 
WHERE p.stock <= p.min_stock AND p.is_active = TRUE
ORDER BY p.stock ASC;

-- Vista para pedidos recientes (últimos 7 días)
CREATE VIEW vw_recent_orders AS
SELECT 
    o.order_id,
    o.client_name,
    o.client_email,
    p.product_key,
    p.name AS product_name,
    o.quantity,
    o.unit_price,
    o.total_price,
    u.username AS seller,
    o.order_date,
    o.status
FROM 
    orders o
JOIN 
    products p ON o.product_id = p.product_id
JOIN 
    users u ON o.user_id = u.user_id
WHERE 
    o.order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY 
    o.order_date DESC;

-- Vista para resumen de ventas por producto
CREATE VIEW vw_sales_summary AS
SELECT 
    p.product_id,
    p.product_key,
    p.name,
    p.stock AS current_stock,
    COUNT(o.order_id) AS total_orders,
    SUM(o.quantity) AS total_quantity_sold,
    SUM(o.total_price) AS total_revenue,
    AVG(o.total_price) AS avg_order_value,
    MIN(o.order_date) AS first_order_date,
    MAX(o.order_date) AS last_order_date,
    COUNT(CASE WHEN o.order_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 END) AS orders_last_month
FROM 
    products p
LEFT JOIN 
    orders o ON p.product_id = o.product_id AND o.status != 'cancelled'
WHERE 
    p.is_active = TRUE
GROUP BY 
    p.product_id, p.product_key, p.name, p.stock
ORDER BY 
    total_revenue DESC;

-- Vista para usuarios activos
CREATE VIEW vw_active_users AS
SELECT 
    u.user_id,
    u.username,
    u.email,
    u.role,
    u.created_at,
    u.last_login,
    COUNT(o.order_id) AS total_orders,
    COUNT(CASE WHEN o.order_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 END) AS orders_last_month
FROM 
    users u
LEFT JOIN 
    orders o ON u.user_id = o.user_id
WHERE 
    u.is_active = TRUE
GROUP BY 
    u.user_id, u.username, u.email, u.role, u.created_at, u.last_login
ORDER BY 
    total_orders DESC;

-- ===================================================================
-- TRIGGERS
-- ===================================================================

-- Trigger para validar el stock antes de insertar un pedido
DELIMITER //
CREATE TRIGGER tr_before_order_insert
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
    DECLARE product_stock INT;
    DECLARE product_active BOOLEAN;
    
    SELECT stock, is_active INTO product_stock, product_active
    FROM products 
    WHERE product_id = NEW.product_id;
    
    IF product_active = FALSE THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'El producto no está activo';
    END IF;
    
    IF product_stock < NEW.quantity THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'No hay suficiente stock disponible para este producto';
    END IF;
    
    -- Calcular total si no se proporciona
    IF NEW.total_price IS NULL AND NEW.unit_price IS NOT NULL THEN
        SET NEW.total_price = NEW.unit_price * NEW.quantity;
    END IF;
END //
DELIMITER ;

-- Trigger para actualizar el stock después de insertar un pedido
DELIMITER //
CREATE TRIGGER tr_after_order_insert
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
    UPDATE products 
    SET stock = stock - NEW.quantity 
    WHERE product_id = NEW.product_id;
END //
DELIMITER ;

-- Trigger para auditar cambios en productos
DELIMITER //
CREATE TRIGGER tr_after_product_update
AFTER UPDATE ON products
FOR EACH ROW
BEGIN
    IF OLD.product_key != NEW.product_key THEN
        INSERT INTO product_audit (product_id, changed_field, old_value, new_value)
        VALUES (NEW.product_id, 'product_key', OLD.product_key, NEW.product_key);
    END IF;
    
    IF OLD.name != NEW.name THEN
        INSERT INTO product_audit (product_id, changed_field, old_value, new_value)
        VALUES (NEW.product_id, 'name', OLD.name, NEW.name);
    END IF;
    
    IF OLD.stock != NEW.stock THEN
        INSERT INTO product_audit (product_id, changed_field, old_value, new_value)
        VALUES (NEW.product_id, 'stock', OLD.stock, NEW.stock);
    END IF;
    
    IF OLD.price != NEW.price THEN
        INSERT INTO product_audit (product_id, changed_field, old_value, new_value)
        VALUES (NEW.product_id, 'price', OLD.price, NEW.price);
    END IF;
END //
DELIMITER ;

-- Trigger para actualizar last_login en usuarios
DELIMITER //
CREATE TRIGGER tr_after_session_insert
AFTER INSERT ON user_sessions
FOR EACH ROW
BEGIN
    UPDATE users 
    SET last_login = NOW() 
    WHERE user_id = NEW.user_id;
END //
DELIMITER ;

-- ===================================================================
-- DATOS DE EJEMPLO
-- ===================================================================

-- Insertar usuarios de ejemplo (contraseñas de 8 caracteres)
INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@bjxit.com', 'Admin123', 'admin'),
('staff1', 'staff1@bjxit.com', 'Staff123', 'staff'),
('staff2', 'staff2@bjxit.com', 'Staff123', 'staff'),
('sales1', 'sales1@bjxit.com', 'Sales123', 'sales'),
('sales2', 'sales2@bjxit.com', 'Sales123', 'sales');

-- Insertar productos de ejemplo
INSERT INTO products (product_key, name, description, stock, min_stock, price, category, supplier) VALUES 
('PROD001', 'Laptop HP EliteBook', 'Laptop empresarial de alto rendimiento', 15, 5, 1299.99, 'Computadoras', 'HP Inc.'),
('PROD002', 'Teclado inalámbrico', 'Teclado inalámbrico ergonómico', 32, 10, 45.99, 'Periféricos', 'Logitech'),
('PROD003', 'Mouse Logitech', 'Mouse óptico inalámbrico', 45, 15, 29.99, 'Periféricos', 'Logitech'),
('PROD004', 'Monitor 24" Samsung', 'Monitor LED Full HD 24 pulgadas', 8, 3, 189.99, 'Monitores', 'Samsung'),
('PROD005', 'Impresora Laser HP', 'Impresora láser monocromática', 5, 2, 199.99, 'Impresoras', 'HP Inc.'),
('PROD006', 'Disco Duro SSD 1TB', 'Unidad SSD SATA III 1TB', 12, 5, 89.99, 'Almacenamiento', 'Kingston'),
('PROD007', 'Memoria RAM 8GB', 'Memoria DDR4 8GB 2400MHz', 25, 8, 49.99, 'Componentes', 'Corsair'),
('PROD008', 'Webcam HD', 'Cámara web 1080p con micrófono', 18, 6, 39.99, 'Periféricos', 'Logitech'),
('PROD009', 'Router WiFi', 'Router inalámbrico AC1200', 7, 3, 79.99, 'Redes', 'TP-Link'),
('PROD010', 'Altavoces Bluetooth', 'Altavoces estéreo inalámbricos', 3, 5, 25.99, 'Audio', 'JBL');

-- Insertar pedidos de ejemplo
INSERT INTO orders (client_name, client_email, client_phone, product_id, quantity, unit_price, total_price, user_id) VALUES 
('Cliente A', 'clientea@email.com', '555-0001', 1, 2, 1299.99, 2599.98, 4),
('Cliente B', 'clienteb@email.com', '555-0002', 3, 5, 29.99, 149.95, 4),
('Cliente C', 'clientec@email.com', '555-0003', 2, 3, 45.99, 137.97, 5),
('Cliente D', 'cliented@email.com', '555-0004', 4, 1, 189.99, 189.99, 5),
('Cliente E', 'clientee@email.com', '555-0005', 6, 2, 89.99, 179.98, 4),
('Cliente F', 'clientef@email.com', '555-0006', 5, 1, 199.99, 199.99, 5),
('Cliente G', 'clienteg@email.com', '555-0007', 7, 4, 49.99, 199.96, 4),
('Cliente H', 'clienteh@email.com', '555-0008', 8, 2, 39.99, 79.98, 5),
('Cliente I', 'clientei@email.com', '555-0009', 9, 1, 79.99, 79.99, 4),
('Cliente J', 'clientej@email.com', '555-0010', 10, 1, 25.99, 25.99, 5);

-- ===================================================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ===================================================================

-- Índices compuestos para consultas frecuentes
CREATE INDEX idx_orders_date_status ON orders(order_date, status);
CREATE INDEX idx_orders_user_date ON orders(user_id, order_date);
CREATE INDEX idx_products_category_active ON products(category, is_active);
CREATE INDEX idx_audit_product_date ON product_audit(product_id, change_date);

-- ===================================================================
-- COMENTARIOS Y DOCUMENTACIÓN
-- ===================================================================

-- Agregar comentarios a las tablas
ALTER TABLE users COMMENT = 'Tabla de usuarios del sistema con roles y autenticación';
ALTER TABLE products COMMENT = 'Catálogo de productos con control de inventario';
ALTER TABLE orders COMMENT = 'Registro de pedidos y ventas realizadas';
ALTER TABLE product_audit COMMENT = 'Auditoría de cambios en productos para trazabilidad';
ALTER TABLE user_sessions COMMENT = 'Gestión de sesiones activas de usuarios';

-- ===================================================================
-- CONSULTAS DE VERIFICACIÓN
-- ===================================================================

-- Verificar que todo se creó correctamente
SELECT 'Tablas creadas:' AS info;
SHOW TABLES;

SELECT 'Usuarios insertados:' AS info;
SELECT COUNT(*) AS total_users FROM users;

SELECT 'Productos insertados:' AS info;
SELECT COUNT(*) AS total_products FROM products;

SELECT 'Órdenes insertadas:' AS info;
SELECT COUNT(*) AS total_orders FROM orders;

-- ===================================================================
-- USUARIOS DE PRUEBA 
-- ===================================================================
/*
USUARIOS PARA PROBAR:
- admin / Admin123 (administrador)
- staff1 / Staff123 (personal)
- staff2 / Staff123 (personal)  
- sales1 / Sales123 (ventas)
- sales2 / Sales123 (ventas)

NOTA: Las contraseñas están en texto plano.
*/
