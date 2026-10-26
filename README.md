# NexoSync 🛒📦

**NexoSync** es una plataforma web integral de comercio electrónico y gestión de inventario diseñada para sincronizar de manera eficiente las ventas en línea con el control de stock en múltiples sedes o sucursales físicas. Permite a los clientes comprar de forma centralizada y a los administradores gestionar productos y órdenes en tiempo real.

## 🚀 Características Principales

- **Gestión Multi-Sede:** Control de stock inteligente distribuido por sucursales independientes.
- **Carrito de Compras Dinámico:** Sistema interactivo que verifica la disponibilidad real de stock antes de procesar el pago.
- **Panel de Administración (Admin Dashboard):** Interfaz exclusiva para la carga, actualización y eliminación de productos, así como el seguimiento de órdenes de compra.
- **Sistema de Autenticación Seguro:** Registro e inicio de sesión de usuarios con diferenciación de roles (Clientes y Administradores).
- **Módulos Especializados:** Secciones organizadas por categorías operativas (Tecnología, Cocina, Lavado, Ofertas).

## 🛠️ Tecnologías Utilizadas

- **Frontend:** HTML5, CSS3 (Diseño responsivo), JavaScript (ES6+ para la lógica del carrito y peticiones asíncronas).
- **Backend:** PHP (Arquitectura modular para API de servicios y procesamiento de datos).
- **Base de Datos:** MySQL / MariaDB (Relacional, óptima para el manejo de inventario y transacciones).

## 📂 Estructura del Proyecto

El repositorio está organizado de la siguiente manera:
- `/` (Raíz): Vistas principales del sistema (`index.html`, `carrito.html`, `panel_admin.html`, etc.).
- `/img`: Almacenamiento de recursos visuales y multimedia del catálogo.
- `*.php`: Scripts de backend encargados de la conexión a la base de datos, sesiones y APIs de consulta (`obtener_productos.php`, `verificar_stock.php`, etc.).

## 🔧 Instalación y Configuración Local

Para ejecutar este proyecto en tu entorno local (por ejemplo, usando XAMPP, Laragon o WampServer):

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com
   ```
2. **Mover el proyecto:** Instala la carpeta dentro del directorio raíz de tu servidor local (ej. `htdocs` o `www`).
3. **Configurar la Base de Datos:**
   - Importa el archivo de la base de datos (asegúrate de incluir el script `.sql` si lo tenés).
   - Revisa el archivo `conexion.php` para ajustar las credenciales de tu servidor (`localhost`, `usuario`, `contraseña`, `nombre_bd`).
4. **Iniciar el servidor:** Abre tu navegador web e ingresa a `http://localhost/NexoSync`.

# NexoSync