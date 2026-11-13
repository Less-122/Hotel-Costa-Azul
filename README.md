# Hotel Costa Azul

**Propósito.** Aplicación web para administrar las reservaciones, habitaciones, pagos, tarifas y políticas de cancelación del hotel Costa Azul, con acceso diferenciado para huésped, recepcionista, camarista y administrador.

**Tipo de aplicación.** Web (se usa desde el navegador; las pantallas de recepción y camarista se diseñaron también para celular).

**Equipo 10, grupo XA.** Bonilla Sevilla Alejandra Kaori y Contreras Rivera Leslie. Proyecto de Ingeniería de Software, Unidad 3 (Desarrollo).

## Stack y versiones

| Elemento | Tecnología y versión |
|---|---|
| Frontend | HTML5, CSS3 y JavaScript (sin versión instalada) |
| Backend | PHP 8.2.12, sin frameworks ni gestor de dependencias |
| Acceso a datos | PDO con el controlador `pdo_mysql` y consultas preparadas |
| SGBD | MariaDB 10.4.32 (incluida en XAMPP; compatible con MySQL) |
| Servidor y entorno local | XAMPP 8.2.12 (panel de control 3.3.0): Apache 2.4.58 + MariaDB; phpMyAdmin 5.2.1 |
| Editor | Visual Studio Code 1.145.0 |
| Control de versiones | Git 2.54.0 y GitHub |

## Estado de la entrega E1

- Interfaces de los cuatro roles creadas y pasadas a PHP.
- BD `hotel_costa_azul` con las seis tablas del diseño U2 (`usuario`, `habitacion`, `reservacion`, `pago`, `tarifa`, `politica_cancelacion`), creada con un script SQL reproducible y datos ficticios.
- Conexión aplicación–BD con PDO (`config/conexion.php`) y control de sesión por rol (`config/sesion.php`).
- Operaciones ya conectadas a la BD: inicio de sesión, reservar, pagar en línea y cancelar (huésped) y tarifas, políticas y reportes (administrador).
- Pendiente para E2: registro de cuentas, pantallas de recepción y camarista conectadas a la BD, regla anti-traslape (RF-12) y el resto de la integración de CU-01 a CU-10.

## Instalación y ejecución (Windows con XAMPP)

No hay dependencias que instalar: no se usan frameworks ni Composer.

1. Inicia **Apache** y **MySQL** desde el panel de control de XAMPP.
2. Clona el repositorio dentro de `htdocs`:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/Less-122/Hotel-Costa-Azul.git
   ```
   Queda en `C:\xampp\htdocs\Hotel-Costa-Azul`.
3. Crea la BD: abre <http://localhost/phpmyadmin/>, pestaña **Importar**, y carga `database/esquemaBD.sql`. El script **borra y recrea** la BD `hotel_costa_azul`, crea las tablas con sus PK, FK, únicos y restricciones `CHECK`, y carga los datos ficticios. Úsalo solo en desarrollo. No hace falta crear la BD antes.
4. Configuración (opcional): por defecto `config/conexion.php` usa `127.0.0.1`, puerto `3306`, BD `hotel_costa_azul`, usuario `root` y contraseña vacía (valores de XAMPP). Para cambiarlos copia `.env.example` como `.env` y edita los valores; la aplicación lo carga sola y el archivo no se sube al repositorio.
5. Abre <http://localhost/Hotel-Costa-Azul/> (redirige a `Principal/index.php`).

### Usuarios de demostración

## Estado de la entrega E1

| Rol | Correo |Contraseña
|---|---|
| Admin | admin@costaazul.test |admin123
| Recepcionista | recep1@costaazul.test, recep2@costaazul.test |recep123
| Camarista | camarista@costaazul.test |camar123
| Huésped | huesped.a@correo.test, huesped.b@correo.test, huesped.c@correo.test |123

Los datos son ficticios; no incluyen datos personales reales.

## Cómo comprobar la conexión con la BD

Inicia sesión como `huesped.a@correo.test`, abre **Reservar**, elige fechas y tipo de habitación y confirma. La aplicación consulta la tarifa y la habitación disponibles en la BD y registra la reservación: se puede ver la fila nueva en la tabla `reservacion` desde phpMyAdmin.

## Estructura del proyecto

Hotel-Costa-Azul/
├── Administrador/        Politicas/, Reportes/, Tarifas/ (poli, repo, tari: .php, .css, .js)
├── Camarista/             Cama.php, Cama.css, Cama.js
├── Hotel_Recep/          Habitaciones/, Pagos/, Recepcion/ (módulo de recepción)
├── Huesped/              Cancelar/, Login_regis/, Pagar/, Reserva/
├── Principal/            Pantalla pública del hotel
├── config/
│   ├── conexion.php      Conexión PDO (obtenerConexion) y lectura opcional de .env
│   └── sesion.php        Sesión y verificación de rol (verificarAcceso)
├── database/
│   └── esquemaBD.sql     Creación de la BD, tablas, restricciones y datos ficticios
├── docs/
│   └── README.md         Alcance, responsables y referencias de evidencias
├── .env.example          Nombres de variables de configuración (sin claves reales)
├── .gitignore
├── index.php             Redirige a Principal/index.php
└── README.md


## Seguridad

- Consultas preparadas con PDO (`ATTR_EMULATE_PREPARES` desactivado) contra inyección SQL.
- Contraseñas guardadas con `password_hash` y verificadas con `password_verify`.
- Contraseñas guardadas con `password_hash` y verificadas con `password_verify`. El hash se genera en el servidor en el momento del registro (`Huesped/Login_regis/regis.php`); a la BD nunca llega el texto original, y el login ya no acepta contraseñas en texto plano.
- Acceso por rol centralizado en `config/sesion.php` (`verificarAcceso`): administrador, recepcionista y camarista solo entran a sus pantallas; un huésped que escribe la URL de una pantalla del personal es redirigido a su propia página (y al revés). La cuenta creada en *Crear cuenta* siempre es de rol Huésped.
- Cierre de sesión en `config/logout.php`.
- El archivo `.env` (configuración local) está en `.gitignore`; solo se versiona `.env.example`.
- Los errores técnicos de conexión se escriben en el log del servidor; al usuario se le muestra un mensaje genérico.

## Responsables

| Caso de uso | Prioridad | Responsable |
|---|---|---|
| CU-01 Gestionar reservación | Alta | Kaori |
| CU-02 Registrar solicitudes especiales | Alta | Kaori |
| CU-03 Notificar reservación | Alta | Kaori |
| CU-04 Gestionar pagos | Alta | Leslie |
| CU-05 Consultar saldo e historial de pagos | Alta | Leslie |
| CU-06 Actualizar inventario de habitaciones | Alta | Leslie |
| CU-07 Actualizar estado de limpieza | Alta | Leslie |
| CU-08 Consultar estado de habitaciones | Media | Leslie con Kaori |
| CU-09 Gestionar tarifas y políticas de cancelación | Media | Kaori |
| CU-10 Consultar reportes de ingresos y ocupación | Media | Kaori con Leslie |
| Autenticación y permisos por rol (soporte, RNF-09) | Alta | Kaori |

## Acuerdo de integración

- Cada integrante desarrolla su caso de uso en una rama y lo integra a `main` por pull request; la otra integrante lo revisa antes de fusionar.
- Tablas y campos en minúsculas con guion bajo. La conexión se reutiliza siempre desde `config/conexion.php`.
- Cualquier cambio a la BD se acuerda entre ambas y se hace en `database/esquemaBD.sql`, nunca solo en phpMyAdmin.
- `git pull` al iniciar cada sesión y `git push` al terminar. Antes de fusionar, se recrea la BD con el script y se comprueba que la aplicación ejecuta en XAMPP.
- Los conflictos se resuelven en la rama local, sin push forzado.

## Referencias de evidencias (documento U3-E1)

| Evidencia | Dónde |
|---|---|
| Diagrama de las seis tablas con PK y FK | Figura 1 del documento U3-E1 |
| Script de la BD y datos ficticios | `database/esquemaBD.sql` |
| Conexión PDO | `config/conexion.php` |
| Operación de la aplicación sobre la BD | Sección 3 del documento U3-E1 (reservación desde `Huesped/Reserva/Reser.php`) |