# RIMO — Zapatería en línea

Aplicación web de una tienda de zapatos, desarrollada en PHP con PDO y MySQL/MariaDB. Incluye registro e inicio de sesión, catálogo, carrito, confirmación interna de compra y un panel para administrar productos, usuarios y tickets.

## Contenido

1. [Funciones de la aplicación](#funciones-de-la-aplicación)
2. [Roles y seguridad](#roles-y-seguridad)
3. [Estructura de la base de datos](#estructura-de-la-base-de-datos)
4. [Archivos del proyecto](#archivos-del-proyecto)
5. [Ejecución local con XAMPP](#ejecución-local-con-xampp)
6. [Copia para InfinityFree](#copia-para-infinityfree)
7. [Limitaciones importantes](#limitaciones-importantes)

## Funciones de la aplicación

### Cliente

- Crea una cuenta desde el formulario de registro e inicia sesión con correo y contraseña.
- Consulta el catálogo de zapatos. Las imágenes se leen desde las URLs guardadas en la base de datos.
- Solo aparecen en el catálogo los zapatos que tienen al menos una talla con existencias mayores que cero.
- Abre los detalles de un zapato y agrega una talla y cantidad al carrito lateral.
- Modifica cantidades o quita artículos del carrito. El sistema revisa el stock y actualiza los totales.
- Confirma la compra. En una transacción se valida y descuenta el inventario, y el ticket cambia de pendiente (`pagado = 0`) a realizado (`pagado = 1`).
- Cierra la sesión desde la cabecera.

### Administrador

Las cuentas con `rol = 1` ven el enlace **Administrar**. El panel `admin.php` contiene secciones para:

- **Agregar zapato:** nombre, URL de imagen, marca, tipo y una o varias variantes con talla, precio y stock inicial.
- **Productos existentes:** cada producto aparece contraído; al abrirlo se pueden actualizar sus datos, precios y existencias, o agregar una talla.
- **Usuarios:** consulta las cuentas y su estado. Dar de baja establece `activo = 0`; la cuenta y sus compras se conservan.
- **Compras:** consulta todos los tickets, separados entre realizados y pendientes, con sus artículos y totales.

Los productos y sus tallas no se borran desde el panel, para conservar las relaciones con compras anteriores. Para retirar un zapato del catálogo, el administrador establece en cero el stock de todas sus tallas. Si se agrega un zapato con todas sus existencias en cero, también permanecerá oculto en la tienda hasta que se actualice el inventario.

## Roles y seguridad

- `rol = 0`: cliente. Es el rol asignado a las cuentas nuevas.
- `rol = 1`: administrador.
- `activo = 1`: cuenta habilitada. El registro asigna este valor explícitamente.
- `activo = 0`: cuenta dada de baja. El inicio de sesión la rechaza y `main.php` y `admin.php` vuelven a comprobar el estado, por lo que una sesión existente se invalida en la siguiente solicitud.
- No se permite dar de baja la propia cuenta ni al último administrador activo.
- Las contraseñas se guardan usando `password_hash`; el inicio de sesión las verifica con `password_verify`.
- Las operaciones del carrito y del panel, y el cierre de sesión, validan tokens CSRF. Las escrituras usan consultas preparadas; las compras y los cambios de inventario se ejecutan con transacciones.
- Los datos de conexión de InfinityFree se mantienen en `infinitefree/hosting-config.php`, ignorado por Git. No copies credenciales en este README ni en el repositorio.

## Estructura de la base de datos

El esquema y los datos de ejemplo están en [`sistema_zapateria.sql`](./sistema_zapateria.sql). Las tablas del volcado están escritas en minúsculas:

| Tabla | Propósito |
| --- | --- |
| `usuario` | Datos de la cuenta, rol, estado `activo` y hash de contraseña. |
| `zapato` | Nombre, imagen y referencias a marca y tipo. |
| `marca` | Catálogo de marcas disponibles. |
| `tipozapato` | Categorías de calzado. |
| `item` | Variante de un zapato: talla, precio y stock. |
| `ticket` | Carrito o compra de un usuario, total y estado `pagado`. |
| `articulo` | Variante, ticket, cantidad y total de línea. |

Relaciones principales:

- `zapato` → `marca` y `tipozapato`.
- `item` → `zapato`.
- `articulo` → `item` y `ticket`.
- `ticket` → `usuario`.

Los artículos de cada compra guardan su cantidad y total de línea. El esquema actual no guarda fecha de creación del ticket ni una pasarela de pago.

## Archivos del proyecto

### Aplicación principal (raíz del proyecto)

| Archivo | Responsabilidad |
| --- | --- |
| `index.php` | Formulario de inicio de sesión y mensajes de error. |
| `login.php` | Valida credenciales, comprueba que la cuenta esté activa y crea la sesión. |
| `register.php` | Valida los datos y registra clientes con `rol = 0`, `activo = 1` y contraseña hasheada. |
| `main.php` | Catálogo, detalles de producto, carrito lateral, acciones del carrito y confirmación de compra. También exige una sesión activa. |
| `admin.php` | Panel protegido por rol para gestionar el catálogo, consultar usuarios, dar cuentas de baja y revisar tickets. |
| `logout.php` | Cierre de sesión por POST protegido con token CSRF. |
| `connect.php` | Conexión local de XAMPP a `sistema_zapateria`. Sus valores actuales (`localhost`, usuario `root`) son solo para desarrollo local. |
| `style.css` | Estilos compartidos y adaptables para autenticación, tienda, carrito, diálogos y panel de administración. |
| `sistema_zapateria.sql` | Exportación SQL con estructura, relaciones y datos de ejemplo. |

### Carpeta `infinitefree/`

Contiene una copia de las páginas PHP y de `style.css`, preparada para el alojamiento:

| Archivo | Responsabilidad |
| --- | --- |
| `index.php`, `login.php`, `register.php`, `main.php`, `admin.php`, `logout.php` | Copias de las páginas de la aplicación. |
| `connect.php` | Conexión PDO que carga la configuración de hosting y usa `utf8mb4`. |
| `hosting-config.example.php` | Plantilla de configuración con el host, usuario y nombre de base indicados para el hosting; no contiene una contraseña válida. |
| `hosting-config.php` | Configuración local de despliegue. Reemplaza el marcador por la contraseña nueva; no se incluye en Git. |
| `.gitignore` | Evita agregar `hosting-config.php` al repositorio. |
| `.htaccess` | Impide el acceso HTTP directo a `hosting-config.php`. |

Las consultas de esta copia usan nombres de tablas en minúsculas para coincidir con el SQL y evitar fallos por diferencias entre servidores Windows y Linux.

## Ejecución local con XAMPP

1. Copia el proyecto dentro de `C:\xampp\htdocs\`.
2. Inicia Apache y MySQL desde XAMPP.
3. Abre phpMyAdmin y crea o selecciona la base `sistema_zapateria`.
4. Importa `sistema_zapateria.sql` en una base vacía. El volcado incluye datos de ejemplo; no lo importes repetidamente en una base que ya contiene esos registros.
5. Comprueba que `connect.php` tenga los datos de tu instalación local. El archivo incluido utiliza `localhost`, `root` y contraseña vacía, configuración habitual de XAMPP pero no adecuada para producción.
6. Abre `http://localhost/ZapateriaEnLinea/index.php`.
7. Para habilitar una cuenta de administrador, verifica su `idUsuario` y cambia su rol en phpMyAdmin:

   ```sql
   UPDATE usuario
   SET rol = 1, activo = 1
   WHERE idUsuario = ID_DE_TU_CUENTA;
   ```

   Sustituye `ID_DE_TU_CUENTA` por el identificador correcto. No compartas ni publiques la base con datos personales.

## Copia para InfinityFree

1. Crea o selecciona en InfinityFree la base MySQL que corresponda a tu cuenta.
2. Importa `sistema_zapateria.sql` desde el phpMyAdmin remoto. Importa el archivo en la base seleccionada, no lo subas como archivo público a `htdocs`.
3. Cambia la contraseña de MySQL si se mostró en una captura o se compartió. En `infinitefree/hosting-config.php`, reemplaza el marcador de contraseña con la nueva clave. No la envíes por chat ni la guardes en `hosting-config.example.php`.
4. Sube el **contenido** de `infinitefree/` a la carpeta `htdocs` del hosting. Incluye `hosting-config.php` y `.htaccess`. Si obtienes el proyecto desde Git, recuerda que el archivo de configuración está ignorado: créalo a partir de `hosting-config.example.php` y complétalo localmente antes de subirlo por FTP.
5. Selecciona una versión PHP 8.x disponible y confirma que PDO MySQL esté habilitado.
6. Abre el dominio y prueba registro, inicio de sesión, catálogo y panel. Confirma que las cuentas activas tengan `activo = 1` y que al menos una cuenta administradora tenga `rol = 1`.

La conexión remota depende de que `hosting-config.php` contenga los datos actuales del panel y de que el SQL se haya importado. La contraseña no se incluye en el repositorio ni en este documento.

## Limitaciones importantes

- El botón **Pagar** realiza una confirmación interna: descuenta stock y marca el ticket como pagado. No se conecta a una pasarela y no realiza cargos reales.
- Las imágenes son URLs externas. Deben ser públicas y permitir que el navegador las cargue.
- `sistema_zapateria.sql` puede contener datos personales y hashes de contraseñas. Trátalo como información privada y no lo publiques en el directorio web.
- No hay fecha de compra en las columnas actuales de `ticket`; el administrador identifica las compras por su número de ticket y estado.
