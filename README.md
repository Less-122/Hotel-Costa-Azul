Hotel Costa Azul

Propósito Administrar reservaciones, habitaciones, pagos, tarifas y políticas de cancelación del hotel Costa Azul, con acceso diferenciado para huéspedes, recepcionistas, camaristas y administrador.
Tipo de aplicación Web (se usa desde el navegador; las pantallas de recepción y camarista se diseñaron también para celular).
Equipo 10 · Integrantes. Bonilla Sevilla Alejandra Kaori y Contreras Rivera Leslie.

Tecnologías utilizadas

| Elemento | Tecnología |
|---|---|
| Frontend | HTML5, CSS3, JavaScript |
| Backend | PHP |
| SGBD | MySQL / MariaDB |
| Entorno local | XAMPP o Laragon |
| Control de versiones | Git y GitHub |


 Guía de Instalación y Ejecución Local

Sigue estos pasos para clonar, configurar y ejecutar el proyecto en tu entorno local:

Prerrequisitos
Asegúrate de tener instalado en tu equipo:
[Git](https://git-scm.com/) (para clonar el repositorio).
Un servidor local con soporte para PHP y MySQL/MariaDB ([XAMPP](https://www.apachefriends.org/es/index.html) o Laragon).
 Un navegador web actualizado (Google Chrome, Firefox, Edge).

Paso 1: Clonar el repositorio
Abre tu terminal (consola o Git Bash) y ejecuta el siguiente comando:

```bash
git clone [https://github.com/Less-122/Hotel-Costa-Azul.git](https://github.com/Less-122/Hotel-Costa-Azul.git)
Mueve la carpeta resultante del proyecto al directorio raíz de tu servidor local:

•	En XAMPP (Windows): C:\xampp\htdocs\Hotel-Costa-Azul

•	En XAMPP (Linux): /opt/lampp/htdocs/Hotel-Costa-Azul

•	En Laragon: C:\laragon\www\Hotel-Costa-Azul

Paso 2: Configurar la Base de Datos
1.	Inicia los servicios de Apache y MySQL desde el panel de control de tu XAMPP o Laragon.

2.	Abre tu navegador y accede a phpMyAdmin: http://localhost/phpmyadmin/

3.	Crea una nueva base de datos (por ejemplo, hotel_costa_azul).

4.	Selecciona la base de datos creada, ve a la pestaña Importar, sube el archivo de respaldo con extensión .sql incluido en el repositorio y haz clic en Continuar.

5.	Revisa el archivo de conexión a la base de datos dentro del código (por ejemplo, conexion.php o similar) para verificar que las credenciales coincidan con tu entorno local:

o	Host: localhost

o	Usuario: root

o	Contraseña: (vacío por defecto)

Paso 3: Ejecutar la aplicación
1.	Abre tu navegador web.

2.	Ingresa a la ruta correspondiente:

o	Si usas XAMPP: http://localhost/Hotel-Costa-Azul/

o	Si usas Laragon: http://hotel-costa-azul.test/ (o la URL automática generada).

Estructura del Proyecto
Hotel-Costa-Azul/
├── Administrador/          # Vistas y lógica del módulo de administración
│   ├── Politicas/          # poli.css, poli.js, poli.php
│   ├── Reportes/           # repo.css, repo.js, repo.php
│   └── Tarifas/            # tari.css, tari.js, tari.php
├── BD/                     # Base de datos
│   └── hotel_costa_azul.sql
├── Camarista/              # Cama.css, Cama.js, Cama.php
├── Config/                 # Configuración del sistema
│   ├── conexion.php        # Conexión a MySQL/MariaDB
│   └── sesion.php          # Control y manejo de sesiones PHP
├── Hotel_Recep/            # Vistas y operaciones del módulo de recepción
│   ├── Habitaciones/       # hab.css, hab.js, hab.php
│   ├── Pagos/              # pagos.css, pagos.js, pagos.php
│   └── Recepcion/          # recep.css, recep.js, recep.php
├── Huesped/                # Módulo y vistas orientadas al huésped
│   ├── Cancelar/           # Cance.css, Cance.php, Canse.js
│   ├── Login_regis/        # index.php, login.css, login.js, regis.css, regis.js, regis.php
│   ├── Pagar/              # pagar.css, pagar.js, pagar.php
│   └── Reserva/            # Reser.css, Reser.js, Reser.php
├── Principal/              # Pantalla principal pública del hotel
│   ├── index.css
│   ├── index.js
│   └── index.php
└── README.md               # Documentación del proyecto
Seguridad
Los archivos de configuración con credenciales o variables de entorno locales se encuentran excluidos del control de versiones mediante el archivo .gitignore.