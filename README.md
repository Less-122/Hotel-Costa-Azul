# Hotel Costa Azul

**Propósito.** Aplicación web para administrar las reservaciones, habitaciones, pagos, tarifas y políticas de cancelación del hotel Costa Azul, con acceso diferenciado para huésped, recepcionista, camarista y administrador.

**Tipo de aplicación.** Web (se usa desde el navegador; las pantallas de recepción y camarista se diseñaron también para celular).

**Equipo 10, grupo XA.** Bonilla Sevilla Alejandra Kaori y Contreras Rivera Leslie. Proyecto de Ingeniería de Software.

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
| Huésped | Crear cuenta e iniciar sesión; consultar disponibilidad por fechas y reservar; pagar en línea (anticipo o liquidación, sin exceder el saldo); cancelar sus reservaciones con la política vigente al reservar. |
| Recepcionista | Ver todas las reservaciones (en línea y de recepción); buscar disponibilidad por fechas y tipo; crear reservaciones con notas o solicitudes especiales; registrar la entrada y la salida del huésped; cancelar reservaciones; actualizar limpieza y disponibilidad de habitaciones; registrar pagos y consultar saldo e historial. |
| Camarista | Cambiar el estado de limpieza de las habitaciones (Limpia, Sucia, Mantenimiento). |
| Administrador | Consultar reportes de ingresos y ocupación (este mes, mes anterior o rango de fechas); gestionar tarifas y políticas de cancelación con historial; gestionar usuarios. |

Reglas del sistema:

- **Sin traslapes (RF-12, RN-03).** Una habitación no se reserva dos veces en fechas que se cruzan; el día de salida queda libre para la siguiente entrada. La reserva y la validación van en una transacción que bloquea las habitaciones del tipo.
- **Validación de captura.** La entrada no puede ser anterior a hoy, la salida debe ser posterior a la entrada, se admite una anticipación máxima de 365 días y una estancia máxima de 90 noches. El teléfono debe tener 10 dígitos y los nombres se validan por formato (se aceptan partículas como "de" y "del", guiones y apóstrofos).
- **Estados de la reservación.** `Activa` → `En curso` (al registrar la entrada) → `Finalizada` (al registrar la salida). Una reservación `Activa` puede pasar a `Cancelada`.
- **Cancelación.** Se aplica la política que la reservación tenía al reservar: cancelación gratuita hasta 72 horas antes de la entrada; después se cobra la primera noche. Si el huésped había pagado más que ese cargo, se registra la devolución de la diferencia.
- **Pagos.** Ningún pago puede superar el saldo pendiente. Una liquidación cubre todo el saldo; un anticipo, solo una parte. Los pagos no se editan ni se eliminan.
- **Trazabilidad (RNF-03).** Cada reservación guarda el canal de origen (`En línea` o `Recepción`) y, si la creó recepción, quién (`id_recepcionista`). Al cancelar o modificar se guarda quién y cuándo (`modificado_por`, `fecha_modificacion`). Las reservas en línea no llevan recepcionista porque no interviene ninguna.
- **Tarifas y políticas.** Una nueva tarifa o política deja la anterior como histórica; las reservaciones conservan la que tenían al reservar (`id_tarifa`, `id_politica`).
- **Habitaciones.** Una habitación con una reservación activa o en curso hoy no puede ponerse en Mantenimiento, y una habitación en Mantenimiento no queda disponible.

## Instalación y ejecución (Windows con XAMPP)

No hay dependencias que instalar: no se usan frameworks ni Composer.

1. Inicia **Apache** y **MySQL** desde el panel de control de XAMPP.
2. Clona el repositorio dentro de `htdocs`:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/Less-122/Hotel-Costa-Azul.git
   ```
   Queda en `C:\xampp\htdocs\Hotel-Costa-Azul`.
3. Crea la BD: abre <http://localhost/phpmyadmin/> y crea una base de datos vacía llamada `hotel_costa_azul` con cotejamiento `utf8mb4_general_ci`. Selecciónala, entra a la pestaña **Importar** y carga `database/esquemaBD.sql`. El script crea las tablas con sus PK, FK y únicos, y carga los datos ficticios. **No crea la base de datos ni borra tablas existentes**, por eso hay que crearla antes.
4. Configuración (opcional): `config/conexion.php` usa `127.0.0.1`, puerto `3306`, BD `hotel_costa_azul`, usuario `root` y contraseña vacía (valores de XAMPP). Si tu MySQL usa otros, edita esas variables en ese archivo.
5. Abre <http://localhost/Hotel-Costa-Azul/Principal/index.php>.

Para restablecer los datos de prueba: elimina la BD `hotel_costa_azul` desde phpMyAdmin, vuelve a crearla vacía e importa de nuevo `database/esquemaBD.sql`.

### Accesos

- Huéspedes: inician sesión en `Huesped/Login_regis/login.php` (la cuenta se crea en `regis.php`).
- Personal (administrador, recepcionista y camarista): `personal_login/login_personal.php`. El sistema envía a cada persona a su panel según su rol.

| Rol | Pantallas |
|---|---|
| Huésped | `Huesped/Reserva/Reser.php`, `Huesped/Pagar/pagar.php`, `Huesped/Cancelar/Cance.php` |
| Recepcionista | `Hotel_Recep/Recepcion/recep.php`, `Hotel_Recep/Pagos/pagos.php`, `Hotel_Recep/Habitaciones/hab.php` |
| Camarista | `Camarista/Cama.php` |
| Administrador | `Administrador/Tarifas/tari.php`, `Administrador/Politicas/poli.php`, `Administrador/Usuarios/usuarios.php`, `Administrador/Reportes/repo.php` |

### Usuarios de demostración

| Rol | Correo | Contraseña |
|---|---|---|
| Admin | admin@costaazul.test | 123 |
| Recepcionista | recep1@costaazul.test | 123 |
| Camarista | camarista@costaazul.test | 123 |
| Huésped | huesped.a@correo.test | 123 |
| Admin (alterno) | admin@gmail.com | admin123 |
| Recepcionista (alterno) | recepcionista1@gmail.com | recep123 |
| Camarista (alterno) | camarista@gmail.com | camar123 |

Los datos son ficticios; no incluyen datos personales reales. Las tarifas vigentes son Sencilla $850.00 y Doble $1,400.00 por noche, y la política vigente es cancelación gratuita hasta 72 horas antes (después se cobra la primera noche).

## Cómo comprobar la conexión con la BD

Inicia sesión como `huesped.a@correo.test`, abre **Reservar**, elige fechas futuras y un tipo de habitación y confirma. La aplicación consulta la tarifa y la habitación disponibles en la BD y registra la reservación: se puede ver la fila nueva en la tabla `reservacion` desde phpMyAdmin. Después, en **Pagar**, registra un anticipo con el código obtenido (para la tarjeta usa un número ficticio válido, por ejemplo `4242 4242 4242 4242`, un vencimiento futuro y cualquier CVV de 3 dígitos) y revisa la tabla `pago`.

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
│   ├── conexion.php      Conexión PDO y zona horaria (America/Mexico_City)
│   ├── sesion.php        Sesión, roles (verificarAcceso) y tokens CSRF
│   └── logout.php        Cierre de sesión
├── database/
│   └── esquemaBD.sql     Tablas, llaves y datos ficticios
└── README.md
```

## Seguridad

- Consultas preparadas con PDO contra inyección SQL, y salida escapada con `htmlspecialchars` contra XSS.
- Contraseñas guardadas con `password_hash` y verificadas con `password_verify`. El hash se genera en el servidor al registrar la cuenta; a la BD nunca llega el texto original.
- Acceso por rol centralizado en `config/sesion.php` (`verificarAcceso`): cada rol solo entra a sus pantallas, y quien escribe la URL de otro rol es redirigido a su propia página. La cuenta creada en *Crear cuenta* siempre es de rol Huésped.
- La sesión se vuelve a validar contra la BD cada pocos segundos: si el usuario se elimina o se desactiva, pierde el acceso sin esperar a cerrar sesión.
- Tokens CSRF en los formularios y en las peticiones AJAX de las pantallas autenticadas.
- Un huésped solo puede ver, pagar y cancelar sus propias reservaciones (se filtra por el usuario de la sesión).
- Los datos de la tarjeta se validan en el servidor (algoritmo de Luhn, vencimiento y CVV) y no se almacenan.
- Cierre de sesión en `config/logout.php`, que destruye la sesión y la cookie.

## Limitaciones conocidas y mejoras posibles

- La pasarela de pago en línea es **simulada**: valida el formato de la tarjeta, pero no se conecta a un procesador real.
- No se envía notificación por correo de la reservación.
- No se midió el tiempo de respuesta de las pantallas (RNF-06, meta de 3 segundos).
- Al registrar la entrada no se valida que la fecha de entrada sea hoy ni que la habitación esté Limpia, y al finalizar una reservación no se exige saldo en cero.
- El cargo de la primera noche por cancelación tardía se guarda como un pago de tipo `Liquidación` con canal `Sistema`, y la devolución como un monto negativo; los reportes de ingresos lo suman tal cual.
- El pago en línea (`pagar.php`) solo acepta reservaciones `Activa`; recepción también acepta `En curso`.
- El plazo y el cargo de la política de cancelación se leen del texto de su descripción (por ejemplo "72 horas"); si se redacta de otra forma, se usa 72 horas por defecto.
- No hay límite de intentos de inicio de sesión, la conexión usa el usuario `root` sin contraseña (solo para desarrollo) y `conexion.php` muestra el mensaje de error si falla la conexión.
- La disponibilidad de un día depende de las reservaciones activas o en curso por fecha; `disponible` en `habitacion` refleja la ocupación actual que marca recepción.
- Mejoras posibles: mover la configuración de la BD a un archivo `.env` fuera del repositorio, y agregar restricciones `CHECK` y un script de borrado y recreación de la BD.

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

## Referencias de evidencias 

| Evidencia | Dónde |
|---|---|
| Diagrama de las seis tablas con PK y FK |  documento  1 |
| Script de la BD y datos ficticios | `database/esquemaBD.sql` |
| Conexión PDO | `config/conexion.php` |
| Operación de la aplicación sobre la BD | Reservación desde `Huesped/Reserva/Reser.php` |