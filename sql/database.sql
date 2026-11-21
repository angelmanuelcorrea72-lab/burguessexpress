SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS comida_rapida CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE comida_rapida;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  telefono VARCHAR(30) NOT NULL,
  password VARCHAR(255) NOT NULL,
  rol ENUM('cliente','empleado','admin') NOT NULL DEFAULT 'cliente',
  creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(60) NOT NULL,
  orden INT NOT NULL DEFAULT 0,
  activa TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE productos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  categoria_id INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) DEFAULT '',
  precio DECIMAL(12,0) NOT NULL,
  imagen VARCHAR(160) DEFAULT NULL,
  disponible TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE configuracion (
  clave VARCHAR(40) PRIMARY KEY,
  valor VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE horarios (
  dia TINYINT PRIMARY KEY,           -- 0=Domingo ... 6=Sábado
  abre TIME NOT NULL,
  cierra TIME NOT NULL,
  cerrado TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tipo ENUM('delivery','retiro') NOT NULL,
  pago ENUM('efectivo','transferencia','tarjeta') NOT NULL,
  estado ENUM('pendiente','preparando','listo','en_camino','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  subtotal DECIMAL(12,0) NOT NULL,
  envio DECIMAL(12,0) NOT NULL DEFAULT 0,
  total DECIMAL(12,0) NOT NULL,
  direccion VARCHAR(255) DEFAULT NULL,
  lat DECIMAL(10,7) DEFAULT NULL,
  lng DECIMAL(10,7) DEFAULT NULL,
  notas VARCHAR(255) DEFAULT NULL,
  minutos INT NOT NULL DEFAULT 30,
  creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE pedido_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  producto_id INT NULL,
  nombre VARCHAR(100) NOT NULL,
  precio DECIMAL(12,0) NOT NULL,
  cantidad INT NOT NULL,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Datos iniciales
INSERT INTO usuarios (nombre,email,telefono,password,rol) VALUES
('Administrador','admin@local.com','0000000','$2y$10$8VCcqLzNhjTvn4YOvy91P.mtYFRDmGuNuiN5poKy6IN0eBXQXwSQe','admin');   -- clave inicial: admin123 (cámbiala)

INSERT INTO configuracion VALUES
('nombre_local','Burger Express'),
('whatsapp','595981000000'),
('direccion','Av. Principal 123'),
('moneda','Gs.'),
('costo_delivery','10000'),
('tiempo_retiro','15'),
('tiempo_delivery','35'),
('modo','auto'),
('mensaje_cierre','Hoy no estamos recibiendo pedidos. ¡Vuelve pronto!');

INSERT INTO horarios (dia,abre,cierra,cerrado) VALUES
(0,'18:00','23:00',0),(1,'18:00','23:30',0),(2,'18:00','23:30',0),(3,'18:00','23:30',0),
(4,'18:00','23:59',0),(5,'18:00','23:59',0),(6,'18:00','23:59',0);

INSERT INTO categorias (nombre,orden) VALUES ('Hamburguesas',1),('Lomitos y Sándwiches',2),('Papas y Extras',3),('Bebidas',4);
INSERT INTO productos (categoria_id,nombre,descripcion,precio) VALUES
(1,'Clásica','Carne 120 g, queso, lechuga, tomate',25000),
(1,'Doble Cheddar','Doble carne, doble cheddar, cebolla caramelizada',38000),
(1,'Bacon BBQ','Carne, panceta crocante, salsa BBQ',35000),
(2,'Lomito Completo','Lomo, jamón, queso, huevo, ensalada',32000),
(3,'Papas fritas','Porción individual',12000),
(3,'Papas con cheddar','Papas con salsa cheddar y panceta',18000),
(4,'Gaseosa 500 ml','Cola, lima-limón o naranja',8000),
(4,'Jugo natural','Frutilla o naranja',10000);

-- ===== Actualización v2: opciones, cupones, zonas, repartidores, recuperación de clave, pagos en línea =====
-- (Si instalas desde cero con database.sql, este bloque ya está incluido.)

ALTER TABLE productos
  ADD COLUMN detalle TEXT NULL AFTER descripcion,
  ADD COLUMN ingredientes VARCHAR(255) NULL AFTER detalle,
  ADD COLUMN precio_promo DECIMAL(12,0) NULL AFTER precio;

CREATE TABLE grupos_opciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  producto_id INT NOT NULL,
  nombre VARCHAR(60) NOT NULL,
  tipo ENUM('unica','multiple') NOT NULL DEFAULT 'unica',
  obligatorio TINYINT(1) NOT NULL DEFAULT 0,
  orden INT NOT NULL DEFAULT 0,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE opciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grupo_id INT NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  extra DECIMAL(12,0) NOT NULL DEFAULT 0,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (grupo_id) REFERENCES grupos_opciones(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cupones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  tipo ENUM('porcentaje','monto','envio_gratis') NOT NULL,
  valor DECIMAL(12,0) NOT NULL DEFAULT 0,
  minimo DECIMAL(12,0) NOT NULL DEFAULT 0,
  usos_max INT NOT NULL DEFAULT 0,
  usos INT NOT NULL DEFAULT 0,
  uno_por_usuario TINYINT(1) NOT NULL DEFAULT 1,
  vence DATE NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE zonas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  costo DECIMAL(12,0) NOT NULL DEFAULT 0,
  minutos_extra INT NOT NULL DEFAULT 0,
  activa TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE repartidores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira DATETIME NOT NULL,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (token_hash),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE pedidos
  MODIFY pago ENUM('efectivo','transferencia','tarjeta','bancard') NOT NULL,
  ADD COLUMN cupon VARCHAR(30) NULL,
  ADD COLUMN descuento DECIMAL(12,0) NOT NULL DEFAULT 0,
  ADD COLUMN zona_id INT NULL,
  ADD COLUMN repartidor_id INT NULL,
  ADD COLUMN pago_estado ENUM('no_aplica','pendiente','pagado','fallido') NOT NULL DEFAULT 'no_aplica',
  ADD COLUMN bancard_intentos INT NOT NULL DEFAULT 0;

ALTER TABLE pedido_items ADD COLUMN opciones VARCHAR(500) NULL;

-- Datos de ejemplo
INSERT INTO zonas (nombre,costo,minutos_extra) VALUES ('Centro (hasta 2 km)',8000,0),('Barrios cercanos (2-5 km)',15000,10),('Zona lejana (5-8 km)',25000,20);
INSERT INTO repartidores (nombre,telefono) VALUES ('Repartidor 1','0981000001');
INSERT INTO cupones (codigo,tipo,valor,minimo,uno_por_usuario) VALUES ('BIENVENIDO10','porcentaje',10,30000,1),('ENVIOGRATIS','envio_gratis',0,50000,0);

UPDATE productos SET detalle='Nuestra hamburguesa de la casa: carne de res 120 g a la plancha, queso derretido, lechuga fresca y tomate en pan brioche tostado.', ingredientes='Pan brioche, carne de res, queso, lechuga, tomate' WHERE nombre='Clásica';
UPDATE productos SET precio_promo=32000 WHERE nombre='Doble Cheddar';
INSERT INTO grupos_opciones (producto_id,nombre,tipo,obligatorio,orden)
  SELECT id,'Tamaño','unica',1,1 FROM productos WHERE nombre='Clásica';
INSERT INTO grupos_opciones (producto_id,nombre,tipo,obligatorio,orden)
  SELECT id,'Extras','multiple',0,2 FROM productos WHERE nombre='Clásica';
INSERT INTO grupos_opciones (producto_id,nombre,tipo,obligatorio,orden)
  SELECT id,'Quitar ingredientes','multiple',0,3 FROM productos WHERE nombre='Clásica';
INSERT INTO opciones (grupo_id,nombre,extra) SELECT id,'Simple',0 FROM grupos_opciones WHERE nombre='Tamaño' LIMIT 1;
INSERT INTO opciones (grupo_id,nombre,extra) SELECT id,'Doble carne',10000 FROM grupos_opciones WHERE nombre='Tamaño' LIMIT 1;
INSERT INTO opciones (grupo_id,nombre,extra) SELECT id,'Huevo frito',3000 FROM grupos_opciones WHERE nombre='Extras' LIMIT 1;
INSERT INTO opciones (grupo_id,nombre,extra) SELECT id,'Panceta',5000 FROM grupos_opciones WHERE nombre='Extras' LIMIT 1;
INSERT INTO opciones (grupo_id,nombre,extra) SELECT id,'Sin cebolla',0 FROM grupos_opciones WHERE nombre='Quitar ingredientes' LIMIT 1;
INSERT INTO opciones (grupo_id,nombre,extra) SELECT id,'Sin tomate',0 FROM grupos_opciones WHERE nombre='Quitar ingredientes' LIMIT 1;

-- ===== Actualización v3: panel del repartidor, tiempos de entrega, código de país para WhatsApp =====
-- (Si instalas desde cero con database.sql, este bloque ya está incluido.)

ALTER TABLE usuarios MODIFY rol ENUM('cliente','empleado','admin','repartidor') NOT NULL DEFAULT 'cliente';

ALTER TABLE repartidores
  ADD COLUMN usuario_id INT NULL,
  ADD UNIQUE KEY uq_rep_usuario (usuario_id),
  ADD CONSTRAINT fk_rep_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE pedidos
  ADD COLUMN asignado_en DATETIME NULL,
  ADD COLUMN entregado_en DATETIME NULL;

UPDATE pedidos SET entregado_en = actualizado WHERE estado = 'entregado' AND entregado_en IS NULL;
UPDATE pedidos SET asignado_en = actualizado WHERE repartidor_id IS NOT NULL AND asignado_en IS NULL;

INSERT IGNORE INTO configuracion (clave, valor) VALUES ('codigo_pais', '595');
