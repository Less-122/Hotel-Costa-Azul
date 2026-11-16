# Hotel Costa Azul

**Propósito.** Aplicación web para administrar las reservaciones, habitaciones, pagos, tarifas y políticas de cancelación del hotel Costa Azul, con acceso diferenciado para huésped, recepcionista, camarista y administrador.

**Tipo de aplicación.** Web (se usa desde el navegador; las pantallas de recepción y camarista se diseñaron también para celular).

**Equipo 10, grupo XA.** Bonilla Sevilla Alejandra Kaori y Contreras Rivera Leslie. Proyecto de Ingeniería de Software, Unidad 3 (Desarrollo).

## Stack y versiones

| Elemento | Tecnología y versión |
|---|---|
| Frontend | HTML5, CSS3 y JavaScript (sin versión instalada); Chart.js (CDN) para la gráfica de reportes |
| Backend | PHP 8.2.12, sin frameworks ni gestor de dependencias |
| Acceso a datos | PDO con el controlador `pdo_mysql` y consultas preparadas |
| SGBD | MariaDB 10.4.32 (incluida en XAMPP; compatible con MySQL) |
| Servidor y entorno local | XAMPP 8.2.12 (panel de control 3.3.0): Apache 2.4.58 + MariaDB; phpMyAdmin 5.2.1 |
| Editor | Visual Studio Code 1.145.0 |
| Control de versiones | Git 2.54.0 y GitHub |

## Funcionalidad por rol

| Rol | Qué puede hacer |
|---|---|
| Huésped | Crear cuenta e iniciar sesión; consultar disponibilidad por fechas y reservar; pagar en línea (anticipo o liquidación, sin exceder el saldo); cancelar sus reservaciones. |
| Recepcionista | Ver todas las reservaciones (en línea y de recepción); crear reservaciones; cancelarlas; actualizar limpieza y disponibilidad de habitaciones; registrar pagos y consultar el historial. |
| Camarista | Cambiar el estado de limpieza de las habitaciones (Limpia, Sucia, Mantenimiento). |
| Administrador | Consultar reportes de ingresos y ocupación (este mes, mes anterior o rango de fechas); gestionar tarifas y políticas de cancelación con historial; gestionar usuarios. |

Reglas del sistema:

- **Sin traslapes (RF-12).** Una habitación no se reserva dos veces en fechas que se cruzan. La reserva y la validación van en una transacción que bloquea las habitaciones del tipo.
- **Pagos.** Ningún pago puede superar el saldo pendiente. Una liquidación cubre todo el saldo; un anticipo, solo una parte. Los pagos no se editan ni se eliminan.
- **Trazabilidad (RNF-03).** Cada reservación guarda el canal de origen (`En línea` o `Recepción`) y, si la creó recepción, quién (`id_recepcionista`). Al cancelar se guarda quién y cuándo (`modificado_por`, `fecha_modificacion`). Las reservas en línea no llevan recepcionista porque no interviene ninguna.
- **Tarifas y políticas.** Una nueva tarifa o política deja la anterior como histórica; las reservaciones conservan la que tenían al reservar.

## Instalación y ejecución (Windows con XAMPP)

No hay dependencias que instalar: no se usan frameworks ni Composer.

1. Inicia **Apache** y **MySQL** desde el panel de control de XAMPP.
2. Clona el repositorio dentro de `htdocs`:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/Less-122/Hotel-Costa-Azul.git
   ```
   Queda en `C:\xampp\htdocs\Hotel-Costa-Azul`.
3. Crea la BD: abre <http://localhost/phpmyadmin/>, pestaña **Importar**, y carga `database/esquemaBD.sql`. El script **borra y recrea** la BD `hotel_costa_azul`, crea las tablas con sus PK, FK, únicos y restricciones, y carga los datos ficticios. Úsalo solo en desarrollo. No hace falta crear la BD antes.
4. Configuración (opcional): por defecto `config/conexion.php` usa `127.0.0.1`, puerto `3306`, BD `hotel_costa_azul`, usuario `root` y contraseña vacía (valores de XAMPP). Para cambiarlos copia `.env.example` como `.env` y edita los valores; el archivo no se sube al repositorio.
5. Abre <http://localhost/Hotel-Costa-Azul/> (redirige a `Principal/index.php`).

### Accesos

- Huéspedes: inician sesión en `Huesped/Login_regis/login.php`.
- Personal (administrador, recepcionista y camarista): `personal_login/login_personal.php`.

### Usuarios de demostración

| Rol | Correo | Contraseña |
|---|---|---|
| Admin | admin@costaazul.test | admin123 |
| Recepcionista | recep1@costaazul.test, recep2@costaazul.test | recep123 |
| Camarista | camarista@costaazul.test | camar123 |
| Huésped | huesped.a@correo.test, huesped.b@correo.test, huesped.c@correo.test | 123 |

Los datos son ficticios; no incluyen datos personales reales.

## Cómo comprobar la conexión con la BD

Inicia sesión como `huesped.a@correo.test`, abre **Reservar**, elige fechas y tipo de habitación y confirma. La aplicación consulta la tarifa y la habitación disponibles en la BD y registra la reservación: se puede ver la fila nueva en la tabla `reservacion` desde phpMyAdmin. Después, en **Pagar**, registra un anticipo con el código obtenido y revisa la tabla `pago`.

## Estructura del proyecto

```
Hotel-Costa-Azul/
├── Administrador/        Politicas/, Reportes/, Tarifas/, Usuarios/ (.php y .css)
├── Camarista/            Cama.php, Cama.css
├── Hotel_Recep/          Habitaciones/, Pagos/, Recepcion/ (módulo de recepción)
├── Huesped/              Cancelar/, Login_regis/, Pagar/, Reserva/
├── personal_login/       Inicio de sesión del personal
├── Principal/            Pantalla pública del hotel
├── config/
│   ├── conexion.php      Conexión PDO y lectura opcional de .env
│   ├── sesion.php        Sesión y verificación de rol (verificarAcceso)
│   └── logout.php        Cierre de sesión
├── database/
│   └── esquemaBD.sql     Creación de la BD, tablas, restricciones y datos ficticios
├── docs/
│   └── README.md         Alcance, responsables y referencias de evidencias
├── .env.example          Nombres de variables de configuración (sin claves reales)
├── .gitignore
├── index.php             Redirige a Principal/index.php
└── README.md
```

## Seguridad

- Consultas preparadas con PDO contra inyección SQL.
- Contraseñas guardadas con `password_hash` y verificadas con `password_verify`. El hash se genera en el servidor al registrar la cuenta; a la BD nunca llega el texto original.
- Acceso por rol centralizado en `config/sesion.php` (`verificarAcceso`): cada rol solo entra a sus pantallas, y quien escribe la URL de otro rol es redirigido a su propia página. La cuenta creada en *Crear cuenta* siempre es de rol Huésped.
- Un huésped solo puede ver, pagar y cancelar sus propias reservaciones (se filtra por el usuario de la sesión).
- Cierre de sesión en `config/logout.php`, que destruye la sesión y la cookie.
- Los errores técnicos se escriben en el log del servidor; al usuario se le muestra un mensaje genérico.
- El archivo `.env` está en `.gitignore`; solo se versiona `.env.example`.


## Limitaciones conocidas y mejoras posibles

- La pasarela de pago en línea es **simulada**: valida el formato de la tarjeta, pero no se conecta a un procesador real y no guarda datos de tarjeta.
- No se envía notificación por correo de la reservación.
- La disponibilidad de un día depende de las reservaciones activas por fecha; `disponible` en `habitacion` refleja la ocupación actual que marca recepción.
- Configuración de la BD: se puede mover por completo a `.env` fuera del repositorio.
- No se midió el tiempo de respuesta de las pantallas (RNF-06, meta de 3 segundos).

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

## Referencias de evidencias (documento U3)

| Evidencia | Dónde |
|---|---|
| Diagrama de las seis tablas con PK y FK | Figura 1 del documento |
| Script de la BD y datos ficticios | `database/esquemaBD.sql` |
| Conexión PDO | `config/conexion.php` |
| Operación de la aplicación sobre la BD | Reservación desde `Huesped/Reserva/Reser.php` |