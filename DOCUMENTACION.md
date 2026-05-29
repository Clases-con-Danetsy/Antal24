# Documentación del Proyecto — Antal24

Esta documentación detalla la arquitectura, la composición de directorios, el flujo de datos y el diseño de la base de datos del proyecto **Antal24**.

---

## 1. Introducción

**Antal24** es un sitio web bilingüe (Español e Inglés) diseñado para una empresa de importaciones, exportaciones y despacho aduanal. La arquitectura del sistema está dividida en dos componentes clave:

1. **Frontend (Sitio Público)**: Construido con **Astro** y **Tailwind CSS v4** enfocado en un óptimo rendimiento estático (SSG).
2. **Backend / CMS de Administración**: Un panel ligero desarrollado en **PHP** con base de datos **MySQL** que permite gestionar de forma autónoma las noticias y artículos de prensa.

---

## 2. Estructura de Directorios del Proyecto

A continuación se muestra la distribución general de los directorios y archivos principales del proyecto:

```text
Antal24/
│
├── .astro/                 # Archivos temporales generados por Astro
├── admin/                  # Panel de administración en PHP
│   ├── config.php          # Configuración de base de datos y entorno
│   ├── index.php           # Dashboard administrativo y listado de noticias
│   ├── guardar.php         # Script para insertar noticias en BD e imágenes en disco
│   ├── actualizar.php      # Script para actualizar noticias e imágenes
│   ├── noticias.php        # API pública (JSON) que sirve las noticias al frontend
│   ├── indicadores.php     # Proxy público para consultar el tipo de cambio del dólar en el DOF
│   └── iniciador de tabla sql.txt   # Script SQL para inicializar la base de datos
│
├── public/                 # Recursos estáticos servidos directamente (ej: favicon, videos)
│   ├── videos/
│   │   └── final.mp4       # Video de fondo del hero en Home
│   └── ...
│
├── src/                    # Código fuente del Frontend (Astro)
│   ├── assets/
│   │   └── main.css        # Configuración principal de temas y fuentes de Tailwind CSS v4
│   ├── components/         # Componentes reutilizables
│   │   ├── Headers/
│   │   │   ├── Header.astro     # Navbar principal bilingüe
│   │   │   └── MapaSocios.astro # Mapa mundial interactivo con D3.js
│   │   └── Footer.astro    # Footer principal bilingüe
│   ├── css/
│   │   └── global.css      # Estilos css globales y animaciones de entrada
│   ├── layouts/
│   │   └── Layout.astro    # Plantilla base HTML, inicializa AOS (Animate On Scroll)
│   ├── locales.js          # Diccionarios de traducción estática (ES / EN)
│   └── pages/              # Enrutador de páginas
│       ├── [lang]/         # Páginas dinámicas internacionalizadas (es/en)
│       │   ├── home.astro       # Página de inicio
│       │   ├── about.astro      # Página de nosotros
│       │   ├── services.astro   # Página de servicios
│       │   ├── news.astro       # Página de noticias (carga dinámica vía fetch)
│       │   └── contact.astro    # Página de contacto
│       └── index.astro     # Redirección por defecto al idioma español (/es/home)
│
├── uploads/                # Directorio de imágenes de noticias subidas por el CMS
├── package.json            # Scripts de Node y dependencias de frontend
├── tsconfig.json           # Configuración de TypeScript para Astro
└── astro.config.mjs        # Configuración de Astro e integración con Tailwind v4
```

---

## 3. Frontend (Astro + Tailwind CSS v4)

### 3.1 Renderizado Estático y Enrutamiento (i18n)

- **SSG (Static Site Generation)**: Las páginas principales del sitio utilizan `export const prerender = true` para compilarse en archivos HTML estáticos en tiempo de build, maximizando la velocidad de carga y optimizando el posicionamiento SEO.
- **Rutas Multilingües**: Las páginas están bajo la ruta dinámica `src/pages/[lang]`. Astro genera versiones estáticas para `es` y `en` con base en el archivo [locales.js](/src/locales.js). El archivo raíz [index.astro](/src/pages/index.astro) redirecciona automáticamente al usuario a `/es/home` usando un meta refresco de HTML.

### 3.2 Animaciones e Interactividad

- **AOS (Animate On Scroll)**: Integrado en el layout base [Layout.astro](/src/layouts/Layout.astro) para revelar de forma fluida los componentes al deslizar la página.
- **Swiper JS**: Implementado para controlar el carrusel de servicios y el de noticias de la página de inicio de forma responsiva.
- **D3.js & TopoJSON**: Empleados en [MapaSocios.astro](/src/components/Headers/MapaSocios.astro) para renderizar un mapa SVG interactivo de socios comerciales (Estados Unidos, Alemania, España y China), trazando arcos de conectividad aérea con pulsaciones animadas mediante `requestAnimationFrame` y modales informativos al hacer clic sobre los pines.

### 3.3 Consumo de Datos Dinámicos

A pesar de ser un sitio estático pre-renderizado, el contenido interactivo y dinámico se consulta mediante peticiones `fetch` client-side hacia los endpoints en PHP:
- **Noticias**: Tanto la página de noticias (`news.astro`) como el carrusel en `home.astro` obtienen los artículos en tiempo real desde `/admin/noticias.php` sin necesidad de reconstruir todo el sitio estático ante cada publicación.
- **Tipo de Cambio (Dólar)**: El buscador histórico de dólares en la sección de noticias de `home.astro` consulta de forma directa a `/admin/indicadores.php?fechaInicio=YYYY-MM-DD&fechaFin=YYYY-MM-DD`, que a su vez actúa como proxy de la API del Diario Oficial de la Federación (DOF), previniendo problemas de CORS en el navegador y entregando los datos formateados a un modal responsivo.

---

## 4. Backend y Panel de Administración (PHP)

El sistema administrativo reside en la carpeta `/admin/`. Funciona bajo un stack tradicional Apache + PHP + MySQL:

- **Autenticación Simple**: Se gestiona mediante sesiones nativas de PHP (`$_SESSION['antal_admin']`). Valida el ingreso contra una contraseña cifrada o definida en texto plano (`ADMIN_PASSWORD`) en el archivo de configuración.
- **Operaciones CRUD**:
  - **Crear (`guardar.php`)**: Recibe el formulario de noticias, valida los campos requeridos, sanitiza las entradas y guarda el registro.
  - **Actualizar (`actualizar.php`)**: Modifica registros existentes. Si se carga una nueva imagen, elimina del servidor el archivo anterior para evitar archivos basura.
  - **Eliminar (`index.php?eliminar=ID`)**: Borra el registro de la base de datos y borra automáticamente la imagen asociada de la carpeta `uploads/`.
  - **Estado (`index.php?toggle=ID`)**: Alterna de forma rápida si la noticia está publicada (visible en la web) o es un borrador (oculta).
- **Procesador de Párrafos (`parseParagraphs`)**: Al guardar contenido multilínea, los saltos de línea dobles se unifican y guardan con el delimitador especial `|||` en la base de datos. Al ser consumidos, el cliente realiza un `explode('|||')` para renderizar párrafos `<p>` separados y estéticamente formateados.
- **Subida de Archivos**: Filtra y valida las imágenes (permite JPG, PNG y WebP de hasta 5MB). Almacena los archivos renombrándolos con un hash seguro (`noticia_TIMESTAMP_RANDOMBYTES.ext`) en la carpeta [uploads/](/uploads/).

---

## 5. Arquitectura de la Base de Datos

La base de datos contiene una única tabla llamada `noticias` destinada a registrar los artículos de prensa en ambos idiomas simultáneamente.

### 5.1 Estructura de la Tabla: `noticias`

```sql
CREATE TABLE IF NOT EXISTS `noticias` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY, -- Identificador único auto-incremental
  `categoria_es`  VARCHAR(100)  NOT NULL,         -- Categoría del artículo en español
  `categoria_en`  VARCHAR(100)  NOT NULL,         -- Categoría del artículo en inglés
  `titulo_es`     VARCHAR(300)  NOT NULL,         -- Título del artículo en español
  `titulo_en`     VARCHAR(300)  NOT NULL,         -- Título del artículo en inglés
  `contenido_es`  TEXT          NOT NULL,         -- Contenido en español (párrafos separados por |||)
  `contenido_en`  TEXT          NOT NULL,         -- Contenido en inglés (párrafos separados por |||)
  `tags_es`       VARCHAR(300)  NOT NULL,         -- Etiquetas en español separadas por coma
  `tags_en`       VARCHAR(300)  NOT NULL,         -- Etiquetas en inglés separadas por coma
  `imagen`        VARCHAR(300)  DEFAULT NULL,     -- Ruta relativa de la imagen (ej: uploads/noticia_xxx.jpg)
  `publicado`     TINYINT(1)    DEFAULT 1,        -- Estado del artículo (1 = Visible, 0 = Borrador)
  `fecha_es`      VARCHAR(80)   NOT NULL,         -- Fecha en español legible (ej: "12 de abril de 2026")
  `fecha_en`      VARCHAR(80)   NOT NULL,         -- Fecha en inglés legible (ej: "April 12, 2026")
  `creado_en`     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP -- Fecha de creación en el sistema
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 5.2 Flujo de Entrega de la Base de Datos (API)

El archivo [admin/noticias.php](/admin/noticias.php) expone un servicio JSON público. Realiza las siguientes transformaciones en tiempo de ejecución:

1. Recupera únicamente las noticias con `publicado = 1` ordenadas de la más reciente a la más antigua.
2. Convierte la cadena `contenido_es` y `contenido_en` de texto plano a arrays de párrafos mediante `explode('|||', ...)`.
3. Convierte las cadenas de tags en arrays de elementos limpios.
4. Construye el enlace absoluto a la imagen concatenando la constante `SITE_URL` y la ruta relativa almacenada en el campo `imagen`.
5. Envía la respuesta en JSON con cabeceras CORS (`Access-Control-Allow-Origin: *`) para habilitar la lectura remota desde el dominio web.

---

## 6. Configuración de Entornos y Conexión

Las credenciales y variables de entorno se aíslan de manera centralizada en [admin/config.php](/admin/config.php).

El archivo cuenta con una estructura inteligente para detectar de forma automática si el servidor se encuentra corriendo de forma local (XAMPP / entorno local de desarrollo) o en el servidor de producción (Hostinger), modificando los parámetros de conexión según corresponda:

```php
// Detección automática del entorno
$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'])
        || str_starts_with($_SERVER['SERVER_ADDR'] ?? '', '192.168.');

if ($isLocal) {
    // Parámetros para Base de Datos Local (XAMPP / WampServer)
    define('DB_HOST', 'localhost');
    define('DB_USER', '...'); // Usuario local (normalmente root)
    define('DB_PASS', '...'); // Contraseña local (normalmente vacío)
    define('DB_NAME', '...'); // Nombre de base de datos local
    define('SITE_URL', 'http://localhost/antal24');
} else {
    // Parámetros para Base de Datos en Producción (Hostinger)
    define('DB_HOST', '...'); // Servidor MySQL de producción
    define('DB_USER', '...'); // Usuario de la base de datos de producción
    define('DB_PASS', '...'); // Contraseña segura de producción
    define('DB_NAME', '...'); // Nombre de la base de datos de producción
    define('SITE_URL', 'https://antal24.com'); // Dominio público oficial
}

// Contraseña para ingresar al panel de administración /admin/index.php
define('ADMIN_PASSWORD', '...');
```

> [!IMPORTANT]
> **Seguridad**: Las credenciales reales y contraseñas administrativas están configuradas dentro del archivo `admin/config.php` en el servidor físico. **Nunca** deben ser subidas a repositorios públicos de control de versiones (como GitHub).

---

## 7. Requisitos y Guía de Despliegue

### 7.1 Requisitos Mínimos

- **Servidor Web**: Apache / Nginx (con módulo PHP habilitado).
- **PHP**: Versión 8.0 o superior (con extensiones `PDO`, `pdo_mysql` y `fileinfo` habilitadas).
- **Base de Datos**: MySQL o MariaDB.
- **Node.js**: Versión 22.12.0 o superior (solo para desarrollo y compilación del frontend).

### 7.2 Pasos para Configurar en Desarrollo Local (XAMPP)

1. Coloca el directorio del proyecto dentro de la carpeta `htdocs` de XAMPP (por ejemplo, `C:/xampp/htdocs/Antal24`).
2. Abre la consola en la raíz de tu proyecto e instala las dependencias:
   ```bash
   npm install
   ```
3. Ejecuta el servidor de desarrollo de Astro si vas a trabajar en la maquetación:
   ```bash
   npm run dev
   ```
4. Abre **phpMyAdmin** (`http://localhost/phpmyadmin`), crea una base de datos e importa las instrucciones SQL del archivo [iniciador de tabla sql.txt](/admin/iniciador%20de%20tabla%20sql.txt).
5. Modifica las credenciales correspondientes del bloque local (`$isLocal`) en `admin/config.php`.

### 7.3 Pasos para Desplegar en Producción (Hostinger)

1. Compila los recursos estáticos ejecutando:
   ```bash
   npm run build
   ```
2. Accede a la carpeta `/dist/` generada por Astro y sube todos sus archivos y carpetas a la raíz de tu servidor web (normalmente `public_html`).
3. Sube la carpeta `/admin/` completa a la raíz del servidor web de producción.
4. Sube una carpeta vacía llamada `/uploads/` en la raíz y asegúrate de otorgarle permisos de escritura (`chmod 755` o `775`) para que los archivos cargados por el panel puedan guardarse correctamente.
5. Crea la base de datos y su usuario correspondiente en tu panel de control de Hosting. Ejecuta el script SQL para crear la tabla de `noticias`.
6. Actualiza las constantes del bloque `else` en [admin/config.php](/admin/config.php) con las credenciales de producción creadas y asigna una contraseña robusta para la constante `ADMIN_PASSWORD`.
