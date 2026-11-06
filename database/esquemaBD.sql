SET NAMES utf8mb4;
DROP DATABASE IF EXISTS hotel_costa_azul;
CREATE DATABASE hotel_costa_azul CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE hotel_costa_azul;

CREATE TABLE usuario (
  id_usuario INT(11) NOT NULL AUTO_INCREMENT,
  nombre     VARCHAR(100) NOT NULL,
  contacto   VARCHAR(10)  NOT NULL,
  correo     VARCHAR(80)  NOT NULL,
  password   VARCHAR(255) NOT NULL,
  rol        VARCHAR(20)  NOT NULL DEFAULT 'Huesped',
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuario_correo (correo),
  CONSTRAINT ck_usuario_rol CHECK (rol IN ('Huesped','Recepcionista','Camarista','Admin'))
) ENGINE=InnoDB;

CREATE TABLE habitacion (
  numero_habitacion VARCHAR(5)  NOT NULL,
  tipo_habitacion   VARCHAR(30) NOT NULL,
  estado_limpieza   VARCHAR(20) NOT NULL,
  disponible        TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (numero_habitacion),
  CONSTRAINT ck_hab_tipo   CHECK (tipo_habitacion IN ('Sencilla','Doble')),
  CONSTRAINT ck_hab_limpia CHECK (estado_limpieza IN ('Limpia','Sucia','Mantenimiento'))
) ENGINE=InnoDB;

CREATE TABLE tarifa (
  id_tarifa       INT(11)       NOT NULL AUTO_INCREMENT,
  tipo_habitacion VARCHAR(30)   NOT NULL,
  precio_noche    DECIMAL(10,2) NOT NULL,
  vigente         TINYINT(1)    NOT NULL DEFAULT 1,
  fecha_inicio    DATE          NOT NULL,
  fecha_fin       DATE          NULL,
  PRIMARY KEY (id_tarifa)
) ENGINE=InnoDB;

CREATE TABLE politica_cancelacion (
  id_politica  INT(11)    NOT NULL AUTO_INCREMENT,
  descripcion  TEXT       NOT NULL,
  vigente      TINYINT(1) NOT NULL DEFAULT 1,
  fecha_inicio DATE       NOT NULL,
  fecha_fin    DATE       NULL,
  PRIMARY KEY (id_politica)
) ENGINE=InnoDB;

CREATE TABLE reservacion (
  id_reservacion      INT(11)     NOT NULL AUTO_INCREMENT,
  numero_confirmacion VARCHAR(5)  NOT NULL,
  fecha_entrada       DATE        NOT NULL,
  fecha_salida        DATE        NOT NULL,
  estado              VARCHAR(20) NOT NULL DEFAULT 'Activa',
  notas               TEXT        NULL,
  fecha_registro      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  canal               VARCHAR(20) NOT NULL,
  id_huesped          INT(11)     NOT NULL,
  id_recepcionista    INT(11)     NULL,
  numero_habitacion   VARCHAR(5)  NOT NULL,
  id_tarifa           INT(11)     NOT NULL,
  id_politica         INT(11)     NOT NULL,
  PRIMARY KEY (id_reservacion),
  UNIQUE KEY uq_reservacion_confirmacion (numero_confirmacion),
  CONSTRAINT ck_res_fechas CHECK (fecha_salida > fecha_entrada),
  CONSTRAINT ck_res_estado CHECK (estado IN ('Activa','Cancelada','Finalizada')),
  CONSTRAINT ck_res_canal  CHECK (canal IN ('En línea','Recepción')),
  CONSTRAINT fk_res_huesped  FOREIGN KEY (id_huesped)        REFERENCES usuario(id_usuario),
  CONSTRAINT fk_res_recep    FOREIGN KEY (id_recepcionista)  REFERENCES usuario(id_usuario),
  CONSTRAINT fk_res_hab      FOREIGN KEY (numero_habitacion) REFERENCES habitacion(numero_habitacion),
  CONSTRAINT fk_res_tarifa   FOREIGN KEY (id_tarifa)         REFERENCES tarifa(id_tarifa),
  CONSTRAINT fk_res_politica FOREIGN KEY (id_politica)       REFERENCES politica_cancelacion(id_politica)
) ENGINE=InnoDB;

CREATE TABLE pago (
  id_pago          INT(11)       NOT NULL AUTO_INCREMENT,
  monto            DECIMAL(10,2) NOT NULL,
  fecha_hora       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tipo_pago        VARCHAR(20)   NOT NULL,
  canal_pago       VARCHAR(20)   NOT NULL,
  id_reservacion   INT(11)       NOT NULL,
  id_recepcionista INT(11)       NULL,
  PRIMARY KEY (id_pago),
  CONSTRAINT ck_pago_tipo  CHECK (tipo_pago IN ('Anticipo','Liquidación')),
  CONSTRAINT ck_pago_canal CHECK (canal_pago IN ('Pasarela en línea','Terminal recepción')),
  CONSTRAINT fk_pago_res   FOREIGN KEY (id_reservacion)   REFERENCES reservacion(id_reservacion),
  CONSTRAINT fk_pago_recep FOREIGN KEY (id_recepcionista) REFERENCES usuario(id_usuario)
) ENGINE=InnoDB;

-- Datos FICTICIOS de demostración.
-- Contraseña de demostración de los 7 usuarios: 123 (en la BD solo se guarda el hash de password_hash)
INSERT INTO usuario (nombre, contacto, correo, password, rol) VALUES
('Admin Demo',         '5550000001', 'admin@costaazul.test',    '$2y$10$XZfVmwbMCpJc5ioxX69N2.555ScRQZmKbxFjh.nTUUc1k1jbO1fje', 'Admin'),
('Recepcion Uno',      '5550000002', 'recep1@costaazul.test',   '$2y$10$y6/wvXgRKHnj6Vpmvij1WOZIPG/kjO2qd97tGqINjibldn3p6NtwC', 'Recepcionista'),
('Recepcion Dos',      '5550000003', 'recep2@costaazul.test',   '$2y$10$MTuPUVFiuMM0v6tyOWwbTOgORx7ay3jnC5p7pGCDCwI/OTTBsQFLS', 'Recepcionista'),
('Camarista Demo',     '5550000004', 'camarista@costaazul.test','$2y$10$DEQHbJ2S.Y1alpRuwQExNOn9Cye4dIYJ085QB03J7TmWFoFdP3lpO', 'Camarista'),
('Huesped Ficticio A', '5550000005', 'huesped.a@correo.test',   '$2y$10$XaBHDwoQEt2jnznfNbWcMuVvDWAZR5mrn3aNeK1P6g/YclBP7qdyK', 'Huesped'),
('Huesped Ficticio B', '5550000006', 'huesped.b@correo.test',   '$2y$10$3qOkqx2vMffaOWYu1sgMgucn9eixMb3yaGLXmkhrM8vMoD3KAcM8e', 'Huesped'),
('Huesped Ficticio C', '5550000007', 'huesped.c@correo.test',   '$2y$10$YTgRsKMEXXvo1BmjxJpSVeDz8s7/5Mf87kwEZtPVPxDFzl18AYZam', 'Huesped');

INSERT INTO habitacion (numero_habitacion, tipo_habitacion, estado_limpieza, disponible) VALUES
('101','Sencilla','Limpia',1),
('102','Sencilla','Sucia',1),
('103','Sencilla','Limpia',1),
('201','Doble','Limpia',1),
('202','Doble','Mantenimiento',0),
('203','Doble','Limpia',1);

INSERT INTO tarifa (tipo_habitacion, precio_noche, vigente, fecha_inicio, fecha_fin) VALUES
('Sencilla',  780.00, 0, '2026-01-01', '2026-09-30'),
('Sencilla',  850.00, 1, '2026-10-01', NULL),
('Doble',    1200.00, 1, '2026-01-01', NULL);

INSERT INTO politica_cancelacion (descripcion, vigente, fecha_inicio, fecha_fin) VALUES
('Cancelacion gratuita hasta 48 horas antes de la fecha de entrada.', 0, '2026-01-01', '2026-09-30'),
('Cancelacion gratuita hasta 72 horas antes; despues se cobra la primera noche.', 1, '2026-10-01', NULL);

INSERT INTO reservacion (numero_confirmacion, fecha_entrada, fecha_salida, estado, notas, canal, id_huesped, id_recepcionista, numero_habitacion, id_tarifa, id_politica) VALUES
('AX7B9','2026-11-10','2026-11-13','Activa','Cama extra',      'Recepción',5,2,'101',2,2),
('K3M8Q','2026-11-15','2026-11-17','Activa',NULL,              'En línea',  6,NULL,'201',3,2),
('P9T4Z','2026-11-20','2026-11-22','Cancelada','Llegada tarde','En línea',  7,NULL,'103',2,2);

INSERT INTO pago (monto, fecha_hora, tipo_pago, canal_pago, id_reservacion, id_recepcionista) VALUES
(1000.00,'2026-11-01 10:30:00','Anticipo',   'Terminal recepción',1,2),
(1200.00,'2026-11-02 12:00:00','Anticipo',   'Pasarela en línea', 2,NULL),
(1200.00,'2026-11-02 12:05:00','Liquidación','Pasarela en línea', 2,NULL);
