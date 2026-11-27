<?php
session_start();

if (!isset($_SESSION['user_id'], $_SESSION['user_name'])) {
    header('Location: index.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'connect.php';

$consulta = $conn->prepare('SELECT rol, activo FROM Usuario WHERE idUsuario = :usuario');
$consulta->execute(['usuario' => (int) $_SESSION['user_id']]);
$usuarioActual = $consulta->fetch(PDO::FETCH_ASSOC);
if (!$usuarioActual || (int) $usuarioActual['activo'] !== 1) {
    $_SESSION = ['login_error' => 'Tu cuenta está dada de baja. Contacta a un administrador.'];
    session_regenerate_id(true);
    header('Location: index.php');
    exit();
}
if ((int) $usuarioActual['rol'] !== 1) {
    http_response_code(403);
    exit('No tienes permiso para administrar el catálogo.');
}

function escaparAdmin(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

function campoAdmin(string $nombre): string
{
    $valor = $_POST[$nombre] ?? null;
    if (!is_string($valor)) {
        throw new DomainException('La solicitud contiene datos inválidos.');
    }

    return trim($valor);
}

function validarIdAdmin($valor, string $mensaje, int $maximo = 2147483647): int
{
    if (!is_string($valor) && !is_int($valor)) {
        throw new DomainException($mensaje);
    }

    $id = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => $maximo]]
    );
    if ($id === false) {
        throw new DomainException($mensaje);
    }

    return $id;
}

function validarNombreZapatoAdmin(string $nombre): string
{
    $longitud = preg_match_all('/./us', $nombre);
    if ($nombre === '' || $longitud === false || $longitud > 60) {
        throw new DomainException('El nombre del zapato es obligatorio y no debe superar 60 caracteres.');
    }

    return $nombre;
}

function validarImagenAdmin(string $url): string
{
    $esquema = filter_var($url, FILTER_VALIDATE_URL) ? parse_url($url, PHP_URL_SCHEME) : null;
    if (
        $url === ''
        || strlen($url) > 500
        || !filter_var($url, FILTER_VALIDATE_URL)
        || !is_string($esquema)
        || !in_array(strtolower($esquema), ['http', 'https'], true)
    ) {
        throw new DomainException('Ingresa una URL de imagen válida que comience con http o https.');
    }

    return $url;
}

function validarTallaAdmin($valor): string
{
    if (!is_string($valor) || !preg_match('/^\d{1,2}(?:\.\d{1,2})?$/D', $valor)) {
        throw new DomainException('La talla debe ser un número entre 0.01 y 99.99.');
    }

    $talla = (float) $valor;
    if ($talla <= 0 || $talla > 99.99) {
        throw new DomainException('La talla debe ser un número entre 0.01 y 99.99.');
    }

    return number_format($talla, 2, '.', '');
}

function validarPrecioAdmin($valor): string
{
    if (!is_string($valor) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $valor) || (float) $valor <= 0) {
        throw new DomainException('El precio debe ser mayor que cero y tener hasta dos decimales.');
    }

    return number_format((float) $valor, 2, '.', '');
}

function validarStockAdmin($valor): int
{
    if (!is_string($valor) || !preg_match('/^\d{1,5}$/D', $valor)) {
        throw new DomainException('El stock debe ser un número entero entre 0 y 99999.');
    }

    return (int) $valor;
}

function validarCategoriaAdmin(PDO $conn, string $tabla, string $columna, string $entrada, string $mensaje): int
{
    $maximo = $tabla === 'Marca' ? 9999999 : 2147483647;
    $id = validarIdAdmin($entrada, $mensaje, $maximo);
    $consulta = $conn->prepare("SELECT 1 FROM $tabla WHERE $columna = :id");
    $consulta->execute(['id' => $id]);
    if (!$consulta->fetchColumn()) {
        throw new DomainException($mensaje);
    }

    return $id;
}

function validarVariantesNuevasAdmin(): array
{
    $tallas = $_POST['tallas'] ?? null;
    $precios = $_POST['precios'] ?? null;
    $stocks = $_POST['stocks'] ?? null;
    if (
        !is_array($tallas)
        || !is_array($precios)
        || !is_array($stocks)
        || count($tallas) < 1
        || count($tallas) > 30
        || count($tallas) !== count($precios)
        || count($tallas) !== count($stocks)
    ) {
        throw new DomainException('Agrega al menos una talla y revisa los datos de las variantes.');
    }

    $variantes = [];
    $tallasVistas = [];
    foreach (array_values($tallas) as $indice => $tallaEntrada) {
        if (!isset($precios[$indice], $stocks[$indice]) || !is_string($tallaEntrada)) {
            throw new DomainException('Revisa los datos de las tallas, precios y existencias.');
        }

        $talla = validarTallaAdmin($tallaEntrada);
        if (isset($tallasVistas[$talla])) {
            throw new DomainException('No repitas la misma talla en un zapato.');
        }
        $tallasVistas[$talla] = true;
        $variantes[] = [
            'talla' => $talla,
            'precio' => validarPrecioAdmin($precios[$indice]),
            'stock' => validarStockAdmin($stocks[$indice]),
        ];
    }

    return $variantes;
}

function validarItemsActualizadosAdmin(PDO $conn, int $zapatoId, $entradas): array
{
    if (!is_array($entradas)) {
        throw new DomainException('No se recibieron las variantes del zapato.');
    }

    $consulta = $conn->prepare('SELECT idItem FROM Item WHERE idZapato = :zapato ORDER BY idItem FOR UPDATE');
    $consulta->execute(['zapato' => $zapatoId]);
    $existentes = array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
    $recibidos = [];
    foreach (array_keys($entradas) as $idEntrada) {
        $recibidos[] = validarIdAdmin((string) $idEntrada, 'Una variante del zapato no es válida.');
    }
    sort($existentes);
    sort($recibidos);
    if ($existentes !== $recibidos) {
        throw new DomainException('Las variantes cambiaron. Recarga la página e inténtalo de nuevo.');
    }

    $actualizaciones = [];
    foreach ($entradas as $idEntrada => $datos) {
        if (!is_array($datos)) {
            throw new DomainException('Los datos de una variante no son válidos.');
        }
        $idItem = validarIdAdmin((string) $idEntrada, 'Una variante del zapato no es válida.');
        $actualizaciones[] = [
            'idItem' => $idItem,
            'precio' => validarPrecioAdmin($datos['precio'] ?? null),
            'stock' => validarStockAdmin($datos['stock'] ?? null),
        ];
    }

    return $actualizaciones;
}

$error = '';
$exito = '';
$accion = '';
$destino = '#productos';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('La solicitud expiró. Recarga la página e inténtalo de nuevo.');
    }

    $conn->beginTransaction();
    try {
        $accion = campoAdmin('action');

        if ($accion === 'dar_baja_usuario') {
            $destino = '#usuarios';
            $usuarioId = validarIdAdmin($_POST['idUsuario'] ?? null, 'La cuenta seleccionada no es válida.');
            if ($usuarioId === (int) $_SESSION['user_id']) {
                throw new DomainException('No puedes dar de baja tu propia cuenta.');
            }

            $consulta = $conn->query(
                'SELECT idUsuario
                 FROM Usuario
                 WHERE rol = 1 AND activo = 1
                 ORDER BY idUsuario
                 FOR UPDATE'
            );
            $administradoresActivos = $consulta->fetchAll(PDO::FETCH_COLUMN);

            $consulta = $conn->prepare(
                'SELECT idUsuario, rol, activo FROM Usuario WHERE idUsuario = :usuario FOR UPDATE'
            );
            $consulta->execute(['usuario' => $usuarioId]);
            $cuenta = $consulta->fetch(PDO::FETCH_ASSOC);
            if (!$cuenta || (int) $cuenta['activo'] !== 1) {
                throw new DomainException('La cuenta ya no está activa.');
            }
            if ((int) $cuenta['rol'] === 1 && count($administradoresActivos) <= 1) {
                throw new DomainException('No se puede dar de baja al último administrador activo.');
            }

            $consulta = $conn->prepare(
                'UPDATE Usuario SET activo = 0 WHERE idUsuario = :usuario AND activo = 1'
            );
            $consulta->execute(['usuario' => $usuarioId]);
            if ($consulta->rowCount() !== 1) {
                throw new DomainException('No fue posible dar de baja esa cuenta.');
            }

            $exito = 'La cuenta se dio de baja. Se conservaron sus compras y datos.';
        } elseif ($accion === 'crear_zapato') {
            $destino = '#nuevo-zapato';
            $nombre = validarNombreZapatoAdmin(campoAdmin('nombre'));
            $imagen = validarImagenAdmin(campoAdmin('img'));
            $marcaId = validarCategoriaAdmin($conn, 'Marca', 'idMarca', campoAdmin('marca'), 'Selecciona una marca válida.');
            $tipoId = validarCategoriaAdmin($conn, 'TipoZapato', 'idTipoZapato', campoAdmin('tipo'), 'Selecciona un tipo válido.');
            $variantes = validarVariantesNuevasAdmin();

            $consulta = $conn->prepare(
                'INSERT INTO Zapato (idTipoZapato, idMarca, nombre, img)
                 VALUES (:tipo, :marca, :nombre, :img)'
            );
            $consulta->execute([
                'tipo' => $tipoId,
                'marca' => $marcaId,
                'nombre' => $nombre,
                'img' => $imagen,
            ]);
            $zapatoId = (int) $conn->lastInsertId();

            $consulta = $conn->prepare(
                'INSERT INTO Item (idZapato, talla, precio, stock)
                 VALUES (:zapato, :talla, :precio, :stock)'
            );
            foreach ($variantes as $variante) {
                $consulta->execute([
                    'zapato' => $zapatoId,
                    'talla' => $variante['talla'],
                    'precio' => $variante['precio'],
                    'stock' => $variante['stock'],
                ]);
            }

            $exito = 'El zapato y sus tallas se agregaron correctamente.';
        } elseif ($accion === 'actualizar_zapato') {
            $zapatoId = validarIdAdmin($_POST['idZapato'] ?? null, 'El zapato seleccionado no es válido.');
            $nombre = validarNombreZapatoAdmin(campoAdmin('nombre'));
            $imagen = validarImagenAdmin(campoAdmin('img'));
            $marcaId = validarCategoriaAdmin($conn, 'Marca', 'idMarca', campoAdmin('marca'), 'Selecciona una marca válida.');
            $tipoId = validarCategoriaAdmin($conn, 'TipoZapato', 'idTipoZapato', campoAdmin('tipo'), 'Selecciona un tipo válido.');

            $consulta = $conn->prepare('SELECT idZapato FROM Zapato WHERE idZapato = :zapato FOR UPDATE');
            $consulta->execute(['zapato' => $zapatoId]);
            if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
                throw new DomainException('El zapato ya no existe.');
            }
            $actualizaciones = validarItemsActualizadosAdmin($conn, $zapatoId, $_POST['items'] ?? []);

            $consulta = $conn->prepare(
                'UPDATE Zapato
                 SET nombre = :nombre, img = :img, idMarca = :marca, idTipoZapato = :tipo
                 WHERE idZapato = :zapato'
            );
            $consulta->execute([
                'nombre' => $nombre,
                'img' => $imagen,
                'marca' => $marcaId,
                'tipo' => $tipoId,
                'zapato' => $zapatoId,
            ]);

            $consulta = $conn->prepare(
                'UPDATE Item
                 SET precio = :precio, stock = :stock
                 WHERE idItem = :item AND idZapato = :zapato'
            );
            foreach ($actualizaciones as $actualizacion) {
                $consulta->execute([
                    'precio' => $actualizacion['precio'],
                    'stock' => $actualizacion['stock'],
                    'item' => $actualizacion['idItem'],
                    'zapato' => $zapatoId,
                ]);
            }

            $exito = 'Los datos del zapato se actualizaron correctamente.';
        } elseif ($accion === 'agregar_talla') {
            $zapatoId = validarIdAdmin($_POST['idZapato'] ?? null, 'El zapato seleccionado no es válido.');
            $talla = validarTallaAdmin(campoAdmin('talla'));
            $precio = validarPrecioAdmin(campoAdmin('precio'));
            $stock = validarStockAdmin(campoAdmin('stock'));

            $consulta = $conn->prepare('SELECT idZapato FROM Zapato WHERE idZapato = :zapato FOR UPDATE');
            $consulta->execute(['zapato' => $zapatoId]);
            if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
                throw new DomainException('El zapato ya no existe.');
            }

            $consulta = $conn->prepare(
                'SELECT idItem FROM Item WHERE idZapato = :zapato AND talla = :talla LIMIT 1 FOR UPDATE'
            );
            $consulta->execute(['zapato' => $zapatoId, 'talla' => $talla]);
            if ($consulta->fetch(PDO::FETCH_ASSOC)) {
                throw new DomainException('Ese zapato ya tiene registrada esa talla.');
            }

            $consulta = $conn->prepare(
                'INSERT INTO Item (idZapato, talla, precio, stock)
                 VALUES (:zapato, :talla, :precio, :stock)'
            );
            $consulta->execute([
                'zapato' => $zapatoId,
                'talla' => $talla,
                'precio' => $precio,
                'stock' => $stock,
            ]);
            $exito = 'La talla se agregó correctamente.';
        } else {
            throw new DomainException('La acción solicitada no es válida.');
        }

        $conn->commit();
        $_SESSION['admin_exito'] = $exito;
    } catch (DomainException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['admin_error'] = $e->getMessage();
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('Error al administrar el catálogo: ' . $e->getMessage());
        $_SESSION['admin_error'] = 'No fue posible guardar los cambios. Inténtalo de nuevo.';
    }

    header('Location: admin.php' . $destino);
    exit();
}

$error = $_SESSION['admin_error'] ?? '';
$exito = $_SESSION['admin_exito'] ?? '';
unset($_SESSION['admin_error'], $_SESSION['admin_exito']);

$consulta = $conn->query('SELECT idMarca, nombre FROM Marca ORDER BY nombre');
$marcas = $consulta->fetchAll(PDO::FETCH_ASSOC);
$consulta = $conn->query('SELECT idTipoZapato, nombre FROM TipoZapato ORDER BY nombre');
$tipos = $consulta->fetchAll(PDO::FETCH_ASSOC);
$consulta = $conn->query(
    'SELECT z.idZapato, z.nombre, z.img, z.idMarca, z.idTipoZapato,
            m.nombre AS marca, t.nombre AS tipo,
            COUNT(i.idItem) AS cantidadItems,
            SUM(CASE WHEN COALESCE(i.stock, 0) > 0 THEN 1 ELSE 0 END) AS tallasConStock
     FROM Zapato z
     LEFT JOIN Marca m ON m.idMarca = z.idMarca
     LEFT JOIN TipoZapato t ON t.idTipoZapato = z.idTipoZapato
     LEFT JOIN Item i ON i.idZapato = z.idZapato
     GROUP BY z.idZapato, z.nombre, z.img, z.idMarca, z.idTipoZapato, m.nombre, t.nombre
     ORDER BY z.idZapato DESC'
);
$zapatos = $consulta->fetchAll(PDO::FETCH_ASSOC);
$consulta = $conn->query('SELECT idItem, idZapato, talla, precio, stock FROM Item ORDER BY talla');
$itemsPorZapato = [];
foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $item) {
    $itemsPorZapato[(int) $item['idZapato']][] = $item;
}

$consulta = $conn->query(
    'SELECT idUsuario, nombre, apellido_p, apellido_m, correo, telefono, rol, activo
     FROM Usuario
     ORDER BY activo DESC, rol DESC, nombre, apellido_p, apellido_m'
);
$usuarios = $consulta->fetchAll(PDO::FETCH_ASSOC);

$consulta = $conn->query(
    'SELECT t.idTicket, t.idUsuario, t.total, t.pagado,
            u.nombre, u.apellido_p, u.apellido_m, u.correo
     FROM Ticket t
     LEFT JOIN Usuario u ON u.idUsuario = t.idUsuario
     ORDER BY t.pagado ASC, t.idTicket DESC'
);
$tickets = $consulta->fetchAll(PDO::FETCH_ASSOC);
$comprasRealizadas = [];
$comprasPendientes = [];
foreach ($tickets as $ticket) {
    if ((int) $ticket['pagado'] === 1) {
        $comprasRealizadas[] = $ticket;
    } else {
        $comprasPendientes[] = $ticket;
    }
}

$consulta = $conn->query(
    'SELECT a.idTicket, a.cant, a.total, i.talla, z.nombre AS zapato
     FROM Articulo a
     LEFT JOIN Item i ON i.idItem = a.idItem
     LEFT JOIN Zapato z ON z.idZapato = i.idZapato
     ORDER BY a.idTicket DESC, a.idArticulo'
);
$articulosPorTicket = [];
foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $articulo) {
    $articulosPorTicket[(int) $articulo['idTicket']][] = $articulo;
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>RIMO | Administración</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <header class="site-header admin-header">
            <div class="header-inner">
                <a class="brand" href="main.php" aria-label="RIMO, tienda">RIMO</a>
                <div class="header-actions">
                    <a class="header-link" href="main.php">Volver a la tienda</a>
                    <form class="logout-form" method="post" action="logout.php">
                        <input type="hidden" name="csrf_token" value="<?= escaparAdmin((string) $_SESSION['csrf_token']) ?>">
                        <button class="logout-button" type="submit">Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="admin-page-shell">
            <section class="admin-welcome">
                <p class="catalog-eyebrow">PANEL DE ADMINISTRACIÓN</p>
                <h1>Administración de tienda</h1>
                <p>Hola, <?= escaparAdmin((string) $_SESSION['user_name']) ?>. Administra el catálogo, las cuentas y las compras.</p>
            </section>

            <?php if ($error !== ''): ?>
                <p class="admin-alert admin-alert-error" role="alert"><?= escaparAdmin($error) ?></p>
            <?php elseif ($exito !== ''): ?>
                <p class="admin-alert admin-alert-success" role="status"><?= escaparAdmin($exito) ?></p>
            <?php endif; ?>

            <div class="admin-layout">
                <aside class="admin-sidebar" aria-label="Menú de administración">
                    <h2>Menú</h2>
                    <a href="#nuevo-zapato">Agregar zapato</a>
                    <a href="#productos">Productos existentes</a>
                    <a href="#usuarios">Usuarios</a>
                    <a href="#compras">Compras</a>
                    <a href="main.php">Volver a la tienda</a>
                </aside>

                <div class="admin-content">
                    <section class="admin-panel" id="nuevo-zapato" aria-labelledby="new-shoe-title">
                        <div class="admin-section-heading">
                            <div>
                                <p class="catalog-eyebrow">NUEVO PRODUCTO</p>
                                <h2 id="new-shoe-title">Agregar zapato</h2>
                            </div>
                        </div>

                        <form class="admin-form" method="post" action="admin.php#nuevo-zapato">
                            <input type="hidden" name="csrf_token" value="<?= escaparAdmin((string) $_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="crear_zapato">
                            <div class="admin-form-grid">
                                <label class="admin-field">
                                    <span>Nombre</span>
                                    <input type="text" name="nombre" maxlength="60" required>
                                </label>
                                <label class="admin-field">
                                    <span>URL de la imagen</span>
                                    <input type="url" name="img" maxlength="500" placeholder="https://..." required>
                                </label>
                                <label class="admin-field">
                                    <span>Marca</span>
                                    <select name="marca" required>
                                        <option value="">Selecciona una marca</option>
                                        <?php foreach ($marcas as $marca): ?>
                                            <option value="<?= escaparAdmin((string) $marca['idMarca']) ?>"><?= escaparAdmin((string) $marca['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="admin-field">
                                    <span>Tipo de calzado</span>
                                    <select name="tipo" required>
                                        <option value="">Selecciona un tipo</option>
                                        <?php foreach ($tipos as $tipo): ?>
                                            <option value="<?= escaparAdmin((string) $tipo['idTipoZapato']) ?>"><?= escaparAdmin((string) $tipo['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>

                            <div class="admin-variants-heading">
                                <h3>Tallas, precios y existencias</h3>
                                <button class="admin-secondary-button" type="button" data-add-variant>Agregar otra talla</button>
                            </div>
                            <div class="admin-new-variants" data-variants>
                                <div class="admin-variant-row">
                                    <label class="admin-field">
                                        <span>Talla</span>
                                        <input type="number" name="tallas[]" min="0.01" max="99.99" step="0.01" required>
                                    </label>
                                    <label class="admin-field">
                                        <span>Precio</span>
                                        <input type="number" name="precios[]" min="0.01" max="99999999.99" step="0.01" required>
                                    </label>
                                    <label class="admin-field">
                                        <span>Stock inicial</span>
                                        <input type="number" name="stocks[]" min="0" max="99999" step="1" value="0" required>
                                    </label>
                                    <button class="admin-remove-variant" type="button" data-remove-variant aria-label="Quitar talla" disabled>Quitar</button>
                                </div>
                            </div>
                            <button class="primary-button admin-submit" type="submit">Guardar zapato</button>
                        </form>
                    </section>

                    <section class="admin-panel" id="productos" aria-labelledby="products-title">
                        <div class="admin-section-heading">
                            <div>
                                <p class="catalog-eyebrow">INVENTARIO</p>
                                <h2 id="products-title">Productos existentes</h2>
                            </div>
                            <span class="catalog-count"><?= count($zapatos) ?> productos</span>
                        </div>

                        <?php if (!$zapatos): ?>
                            <p class="catalog-empty">Todavía no hay zapatos registrados.</p>
                        <?php else: ?>
                            <div class="admin-product-list">
                                <?php foreach ($zapatos as $zapato): ?>
                                    <?php
                                    $zapatoId = (int) $zapato['idZapato'];
                                    $items = $itemsPorZapato[$zapatoId] ?? [];
                                    $visibleEnTienda = (int) $zapato['tallasConStock'] > 0;
                                    $imagenAdminValida = is_string($zapato['img'])
                                        && filter_var($zapato['img'], FILTER_VALIDATE_URL)
                                        && in_array(strtolower((string) parse_url($zapato['img'], PHP_URL_SCHEME)), ['http', 'https'], true);
                                    ?>
                                    <article class="admin-product-card">
                                        <details class="admin-product-disclosure">
                                            <summary class="admin-product-summary" aria-label="Mostrar u ocultar opciones de <?= escaparAdmin((string) $zapato['nombre']) ?>">
                                            <?php if ($imagenAdminValida): ?>
                                                <img class="admin-product-image" src="<?= escaparAdmin((string) $zapato['img']) ?>" alt="" loading="lazy">
                                            <?php else: ?>
                                                <span class="admin-product-image admin-image-placeholder">Sin imagen</span>
                                            <?php endif; ?>
                                            <div>
                                                <h3><?= escaparAdmin((string) ($zapato['nombre'] ?? 'Zapato sin nombre')) ?></h3>
                                            </div>
                                            <span class="admin-stock-badge <?= $visibleEnTienda ? 'is-visible' : 'is-hidden' ?>">
                                                <?= $visibleEnTienda ? 'Visible en tienda' : 'Oculto por falta de stock' ?>
                                            </span>
                                            </summary>

                                        <div class="admin-product-editor">
                                            <form class="admin-form admin-product-edit" method="post" action="admin.php#productos">
                                            <input type="hidden" name="csrf_token" value="<?= escaparAdmin((string) $_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="actualizar_zapato">
                                            <input type="hidden" name="idZapato" value="<?= $zapatoId ?>">
                                            <div class="admin-form-grid">
                                                <label class="admin-field">
                                                    <span>Nombre</span>
                                                    <input type="text" name="nombre" maxlength="60" value="<?= escaparAdmin((string) $zapato['nombre']) ?>" required>
                                                </label>
                                                <label class="admin-field">
                                                    <span>URL de la imagen</span>
                                                    <input type="url" name="img" maxlength="500" value="<?= escaparAdmin((string) $zapato['img']) ?>" required>
                                                </label>
                                                <label class="admin-field">
                                                    <span>Marca</span>
                                                    <select name="marca" required>
                                                        <?php foreach ($marcas as $marca): ?>
                                                            <option value="<?= escaparAdmin((string) $marca['idMarca']) ?>" <?= (string) $zapato['idMarca'] === (string) $marca['idMarca'] ? 'selected' : '' ?>><?= escaparAdmin((string) $marca['nombre']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                                <label class="admin-field">
                                                    <span>Tipo de calzado</span>
                                                    <select name="tipo" required>
                                                        <?php foreach ($tipos as $tipo): ?>
                                                            <option value="<?= escaparAdmin((string) $tipo['idTipoZapato']) ?>" <?= (string) $zapato['idTipoZapato'] === (string) $tipo['idTipoZapato'] ? 'selected' : '' ?>><?= escaparAdmin((string) $tipo['nombre']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                            </div>

                                            <div class="admin-variants-heading">
                                                <h4>Variantes (<?= count($items) ?>)</h4>
                                                <span class="admin-muted">Las tallas no se eliminan para conservar el historial.</span>
                                            </div>
                                            <?php if ($items): ?>
                                                <div class="admin-item-list">
                                                    <?php foreach ($items as $item): ?>
                                                        <div class="admin-item-row">
                                                            <label class="admin-field">
                                                                <span>Talla</span>
                                                                <input type="text" value="<?= escaparAdmin((string) $item['talla']) ?>" readonly>
                                                            </label>
                                                            <label class="admin-field">
                                                                <span>Precio</span>
                                                                <input type="number" name="items[<?= (int) $item['idItem'] ?>][precio]" min="0.01" max="99999999.99" step="0.01" value="<?= escaparAdmin((string) $item['precio']) ?>" required>
                                                            </label>
                                                            <label class="admin-field">
                                                                <span>Stock</span>
                                                                <input type="number" name="items[<?= (int) $item['idItem'] ?>][stock]" min="0" max="99999" step="1" value="<?= (int) $item['stock'] ?>" required>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="admin-muted">Este zapato aún no tiene tallas.</p>
                                            <?php endif; ?>
                                            <button class="primary-button admin-submit" type="submit">Guardar cambios</button>
                                            </form>

                                            <form class="admin-add-variant" method="post" action="admin.php#productos">
                                            <input type="hidden" name="csrf_token" value="<?= escaparAdmin((string) $_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="agregar_talla">
                                            <input type="hidden" name="idZapato" value="<?= $zapatoId ?>">
                                            <h4>Agregar otra talla</h4>
                                            <div class="admin-variant-row">
                                                <label class="admin-field">
                                                    <span>Talla</span>
                                                    <input type="number" name="talla" min="0.01" max="99.99" step="0.01" required>
                                                </label>
                                                <label class="admin-field">
                                                    <span>Precio</span>
                                                    <input type="number" name="precio" min="0.01" max="99999999.99" step="0.01" required>
                                                </label>
                                                <label class="admin-field">
                                                    <span>Stock inicial</span>
                                                    <input type="number" name="stock" min="0" max="99999" step="1" value="0" required>
                                                </label>
                                                <button class="admin-secondary-button admin-add-submit" type="submit">Agregar talla</button>
                                            </div>
                                            </form>
                                        </div>
                                        </details>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="admin-panel" id="usuarios" aria-labelledby="users-title">
                        <div class="admin-section-heading">
                            <div>
                                <p class="catalog-eyebrow">CUENTAS</p>
                                <h2 id="users-title">Usuarios existentes</h2>
                            </div>
                            <span class="catalog-count"><?= count($usuarios) ?> usuarios</span>
                        </div>
                        <p class="admin-section-description">Dar de baja impide iniciar sesión, pero conserva la cuenta y sus compras. No puedes dar de baja tu propia cuenta ni al último administrador activo.</p>

                        <?php if (!$usuarios): ?>
                            <p class="catalog-empty">No hay usuarios registrados.</p>
                        <?php else: ?>
                            <div class="admin-table-wrap">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Usuario</th>
                                            <th>Correo y teléfono</th>
                                            <th>Rol</th>
                                            <th>Estado</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usuarios as $usuario): ?>
                                            <?php
                                            $usuarioId = (int) $usuario['idUsuario'];
                                            $nombreUsuario = trim(implode(' ', array_filter([
                                                $usuario['nombre'],
                                                $usuario['apellido_p'],
                                                $usuario['apellido_m'],
                                            ], static function ($parte): bool {
                                                return is_string($parte) && $parte !== '';
                                            })));
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= escaparAdmin($nombreUsuario !== '' ? $nombreUsuario : 'Usuario sin nombre') ?></strong>
                                                    <span class="admin-table-subtext">ID #<?= $usuarioId ?></span>
                                                </td>
                                                <td>
                                                    <span><?= escaparAdmin((string) ($usuario['correo'] ?? 'Sin correo')) ?></span>
                                                    <span class="admin-table-subtext"><?= escaparAdmin((string) ($usuario['telefono'] ?? 'Sin teléfono')) ?></span>
                                                </td>
                                                <td><?= (int) $usuario['rol'] === 1 ? 'Administrador' : 'Cliente' ?></td>
                                                <td>
                                                    <span class="admin-user-status <?= (int) $usuario['activo'] === 1 ? 'is-active' : 'is-inactive' ?>">
                                                        <?= (int) $usuario['activo'] === 1 ? 'Activo' : 'Dado de baja' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ((int) $usuario['activo'] !== 1): ?>
                                                        <span class="admin-muted">Sin acción</span>
                                                    <?php elseif ($usuarioId === (int) $_SESSION['user_id']): ?>
                                                        <span class="admin-muted">Tu cuenta</span>
                                                    <?php else: ?>
                                                        <form method="post" action="admin.php#usuarios">
                                                            <input type="hidden" name="csrf_token" value="<?= escaparAdmin((string) $_SESSION['csrf_token']) ?>">
                                                            <input type="hidden" name="action" value="dar_baja_usuario">
                                                            <input type="hidden" name="idUsuario" value="<?= $usuarioId ?>">
                                                            <button class="admin-deactivate-button" type="submit" onclick="return confirm('¿Dar de baja esta cuenta? Se conservarán sus compras.')">Dar de baja</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="admin-panel" id="compras" aria-labelledby="purchases-title">
                        <div class="admin-section-heading">
                            <div>
                                <p class="catalog-eyebrow">VENTAS</p>
                                <h2 id="purchases-title">Compras realizadas y pendientes</h2>
                            </div>
                            <span class="catalog-count"><?= count($tickets) ?> tickets</span>
                        </div>
                        <p class="admin-section-description">Las compras se muestran con el total registrado en el ticket. Las cuentas dadas de baja y sus compras se conservan en este historial.</p>

                        <div class="admin-purchase-groups">
                            <?php foreach ([
                                ['titulo' => 'Realizadas', 'tickets' => $comprasRealizadas, 'estado' => 'paid'],
                                ['titulo' => 'Pendientes', 'tickets' => $comprasPendientes, 'estado' => 'pending'],
                            ] as $grupo): ?>
                                <section class="admin-purchase-group">
                                    <div class="admin-purchase-heading">
                                        <h3><?= escaparAdmin($grupo['titulo']) ?></h3>
                                        <span class="catalog-count"><?= count($grupo['tickets']) ?></span>
                                    </div>
                                    <?php if (!$grupo['tickets']): ?>
                                        <p class="admin-muted">No hay tickets en esta sección.</p>
                                    <?php else: ?>
                                        <div class="admin-ticket-list">
                                            <?php foreach ($grupo['tickets'] as $ticket): ?>
                                                <?php
                                                $ticketId = (int) $ticket['idTicket'];
                                                $ticketItems = $articulosPorTicket[$ticketId] ?? [];
                                                $nombreCliente = trim(implode(' ', array_filter([
                                                    $ticket['nombre'],
                                                    $ticket['apellido_p'],
                                                    $ticket['apellido_m'],
                                                ], static function ($parte): bool {
                                                    return is_string($parte) && $parte !== '';
                                                })));
                                                if ($nombreCliente === '') {
                                                    $nombreCliente = 'Usuario #' . (int) $ticket['idUsuario'];
                                                }
                                                ?>
                                                <article class="admin-ticket-card">
                                                    <div class="admin-ticket-heading">
                                                        <div>
                                                            <h4>Ticket #<?= $ticketId ?></h4>
                                                            <p><?= escaparAdmin($nombreCliente) ?></p>
                                                            <p class="admin-table-subtext"><?= escaparAdmin((string) ($ticket['correo'] ?? 'Sin correo')) ?></p>
                                                        </div>
                                                        <div class="admin-ticket-total">
                                                            <span class="admin-ticket-status <?= escaparAdmin($grupo['estado']) ?>">
                                                                <?= escaparAdmin($grupo['titulo']) ?>
                                                            </span>
                                                            <strong>$<?= number_format((float) $ticket['total'], 2) ?></strong>
                                                        </div>
                                                    </div>
                                                    <details class="admin-ticket-details">
                                                        <summary>Artículos (<?= count($ticketItems) ?>)</summary>
                                                        <?php if (!$ticketItems): ?>
                                                            <p class="admin-muted">Este ticket no tiene artículos registrados.</p>
                                                        <?php else: ?>
                                                            <ul>
                                                                <?php foreach ($ticketItems as $ticketItem): ?>
                                                                    <li>
                                                                        <span><?= escaparAdmin((string) ($ticketItem['zapato'] ?? 'Producto no disponible')) ?> · talla <?= escaparAdmin((string) ($ticketItem['talla'] ?? 'N/D')) ?> · cantidad <?= (int) $ticketItem['cant'] ?></span>
                                                                        <strong>$<?= number_format((float) $ticketItem['total'], 2) ?></strong>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>
                                                    </details>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
            </div>
        </main>

        <script>
            const variantList = document.querySelector('[data-variants]');
            const addVariantButton = document.querySelector('[data-add-variant]');

            addVariantButton.addEventListener('click', () => {
                const row = variantList.querySelector('.admin-variant-row').cloneNode(true);
                row.querySelectorAll('input').forEach((input) => {
                    input.value = input.name === 'stocks[]' ? '0' : '';
                });
                row.querySelector('[data-remove-variant]').disabled = false;
                variantList.appendChild(row);
            });

            variantList.addEventListener('click', (event) => {
                const removeButton = event.target.closest('[data-remove-variant]');
                if (removeButton && variantList.children.length > 1) {
                    removeButton.closest('.admin-variant-row').remove();
                }
            });
        </script>
    </body>
</html>
