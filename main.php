<?php
session_start();

if (!isset($_SESSION['user_id'], $_SESSION['user_name'])) {
    header('Location: index.php');
    exit();
}

try {
    require_once 'connect.php';
    $consulta = $conn->prepare('SELECT rol, activo FROM Usuario WHERE idUsuario = :usuario');
    $consulta->execute(['usuario' => (int) $_SESSION['user_id']]);
    $usuarioActual = $consulta->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error al validar sesión de usuario: ' . $e->getMessage());
    http_response_code(503);
    exit('No fue posible validar tu cuenta. Inténtalo de nuevo más tarde.');
}

if (!$usuarioActual || (int) $usuarioActual['activo'] !== 1) {
    $_SESSION = ['login_error' => 'Tu cuenta está dada de baja. Contacta a un administrador.'];
    session_regenerate_id(true);
    header('Location: index.php');
    exit();
}
$esAdministrador = (int) $usuarioActual['rol'] === 1;

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escaparMain(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

function imagenUrlValida($url): bool
{
    if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $esquema = parse_url($url, PHP_URL_SCHEME);
    return is_string($esquema) && in_array(strtolower($esquema), ['http', 'https'], true);
}

function validarEnteroPostMain(string $campo, string $mensaje, int $maximo = 2147483647): int
{
    $valor = filter_var(
        $_POST[$campo] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => $maximo]]
    );

    if ($valor === false) {
        throw new DomainException($mensaje);
    }

    return $valor;
}

function obtenerTicketPendienteMain(PDO $conn, int $usuarioId)
{
    $consulta = $conn->prepare(
        'SELECT idTicket
         FROM Ticket
         WHERE idUsuario = :usuario AND pagado = 0
         ORDER BY idTicket DESC
         LIMIT 1
         FOR UPDATE'
    );
    $consulta->execute(['usuario' => $usuarioId]);

    return $consulta->fetch(PDO::FETCH_ASSOC);
}

function recalcularTicketMain(PDO $conn, int $ticketId, int $usuarioId): void
{
    $consulta = $conn->prepare(
        'SELECT COUNT(*) AS articulos, COALESCE(SUM(total), 0) AS total
         FROM Articulo
         WHERE idTicket = :ticket'
    );
    $consulta->execute(['ticket' => $ticketId]);
    $resumen = $consulta->fetch(PDO::FETCH_ASSOC);

    if ((int) $resumen['articulos'] === 0) {
        $consulta = $conn->prepare(
            'DELETE FROM Ticket
             WHERE idTicket = :ticket AND idUsuario = :usuario AND pagado = 0'
        );
        $consulta->execute(['ticket' => $ticketId, 'usuario' => $usuarioId]);
        return;
    }

    $consulta = $conn->prepare(
        'UPDATE Ticket
         SET total = :total, pagado = 0
         WHERE idTicket = :ticket AND idUsuario = :usuario'
    );
    $consulta->execute([
        'total' => number_format((float) $resumen['total'], 2, '.', ''),
        'ticket' => $ticketId,
        'usuario' => $usuarioId,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['carrito_error'] = 'La solicitud expiró. Inténtalo de nuevo.';
        header('Location: main.php#carrito');
        exit();
    }

    $conn = null;
    try {
        $accion = $_POST['action'] ?? '';
        if (!is_string($accion) || !in_array($accion, ['agregar', 'actualizar', 'eliminar', 'pagar'], true)) {
            throw new DomainException('La acción solicitada no es válida.');
        }

        $usuarioId = (int) $_SESSION['user_id'];
        require_once 'connect.php';
        $conn->beginTransaction();

        $consulta = $conn->prepare('SELECT idUsuario FROM Usuario WHERE idUsuario = :usuario FOR UPDATE');
        $consulta->execute(['usuario' => $usuarioId]);
        if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
            throw new DomainException('No se encontró la cuenta. Inicia sesión nuevamente.');
        }

        if ($accion === 'agregar') {
            $itemId = validarEnteroPostMain('item_id', 'Selecciona una talla válida.');
            $cantidad = validarEnteroPostMain('cantidad', 'La cantidad debe ser un número mayor que cero.', 99999);

            $consulta = $conn->prepare('SELECT idItem, precio, stock FROM Item WHERE idItem = :item FOR UPDATE');
            $consulta->execute(['item' => $itemId]);
            $item = $consulta->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                throw new DomainException('La talla seleccionada ya no está disponible.');
            }
            if ($item['precio'] === null) {
                throw new DomainException('Esta talla no tiene un precio disponible.');
            }

            $stock = (int) $item['stock'];
            if ($stock < 1) {
                throw new DomainException('Esta talla está agotada.');
            }

            $ticket = obtenerTicketPendienteMain($conn, $usuarioId);
            if (!$ticket) {
                $consulta = $conn->prepare(
                    'INSERT INTO Ticket (idUsuario, total, pagado)
                     VALUES (:usuario, 0, 0)'
                );
                $consulta->execute(['usuario' => $usuarioId]);
                $ticketId = (int) $conn->lastInsertId();
            } else {
                $ticketId = (int) $ticket['idTicket'];
            }

            $consulta = $conn->prepare(
                'SELECT idArticulo, cant
                 FROM Articulo
                 WHERE idTicket = :ticket AND idItem = :item
                 LIMIT 1
                 FOR UPDATE'
            );
            $consulta->execute(['ticket' => $ticketId, 'item' => $itemId]);
            $articulo = $consulta->fetch(PDO::FETCH_ASSOC);
            $nuevaCantidad = (int) ($articulo['cant'] ?? 0) + $cantidad;

            if ($nuevaCantidad > $stock) {
                throw new DomainException('No hay suficientes existencias para esa cantidad.');
            }

            $totalArticulo = number_format((float) $item['precio'] * $nuevaCantidad, 2, '.', '');
            if ($articulo) {
                $consulta = $conn->prepare(
                    'UPDATE Articulo SET cant = :cantidad, total = :total
                     WHERE idArticulo = :articulo AND idTicket = :ticket'
                );
                $consulta->execute([
                    'cantidad' => $nuevaCantidad,
                    'total' => $totalArticulo,
                    'articulo' => $articulo['idArticulo'],
                    'ticket' => $ticketId,
                ]);
            } else {
                $consulta = $conn->prepare(
                    'INSERT INTO Articulo (idItem, idTicket, cant, total)
                     VALUES (:item, :ticket, :cantidad, :total)'
                );
                $consulta->execute([
                    'item' => $itemId,
                    'ticket' => $ticketId,
                    'cantidad' => $nuevaCantidad,
                    'total' => $totalArticulo,
                ]);
            }

            recalcularTicketMain($conn, $ticketId, $usuarioId);
            $mensajeExito = 'Producto agregado al carrito.';
        } elseif ($accion === 'pagar') {
            $ticket = obtenerTicketPendienteMain($conn, $usuarioId);
            if (!$ticket) {
                throw new DomainException('No hay un carrito pendiente para pagar.');
            }
            $ticketId = (int) $ticket['idTicket'];

            $consulta = $conn->prepare(
                'SELECT a.idItem, a.cant, i.stock
                 FROM Articulo a
                 JOIN Item i ON i.idItem = a.idItem
                 WHERE a.idTicket = :ticket
                 ORDER BY a.idItem
                 FOR UPDATE'
            );
            $consulta->execute(['ticket' => $ticketId]);
            $articulos = $consulta->fetchAll(PDO::FETCH_ASSOC);
            if (!$articulos) {
                throw new DomainException('Tu carrito está vacío.');
            }

            $actualizarStock = $conn->prepare(
                'UPDATE Item
                 SET stock = stock - :cantidadCompra
                 WHERE idItem = :item AND stock >= :cantidadDisponible'
            );
            foreach ($articulos as $articulo) {
                $cantidadComprada = (int) $articulo['cant'];
                if ($cantidadComprada < 1 || $cantidadComprada > (int) $articulo['stock']) {
                    throw new DomainException('No hay suficientes existencias para completar la compra.');
                }

                $actualizarStock->execute([
                    'cantidadCompra' => $cantidadComprada,
                    'item' => $articulo['idItem'],
                    'cantidadDisponible' => $cantidadComprada,
                ]);
                if ($actualizarStock->rowCount() !== 1) {
                    throw new DomainException('No se pudo actualizar el inventario. Inténtalo de nuevo.');
                }
            }

            $consulta = $conn->prepare(
                'UPDATE Ticket
                 SET pagado = 1
                 WHERE idTicket = :ticket AND idUsuario = :usuario AND pagado = 0'
            );
            $consulta->execute(['ticket' => $ticketId, 'usuario' => $usuarioId]);
            if ($consulta->rowCount() !== 1) {
                throw new DomainException('El ticket ya no está pendiente de pago.');
            }
        } else {
            $articuloId = validarEnteroPostMain('articulo_id', 'Selecciona un artículo válido.');
            $ticket = obtenerTicketPendienteMain($conn, $usuarioId);
            if (!$ticket) {
                throw new DomainException('Tu carrito está vacío.');
            }
            $ticketId = (int) $ticket['idTicket'];

            $consulta = $conn->prepare(
                'SELECT idArticulo, idItem
                 FROM Articulo
                 WHERE idArticulo = :articulo AND idTicket = :ticket
                 FOR UPDATE'
            );
            $consulta->execute(['articulo' => $articuloId, 'ticket' => $ticketId]);
            $articulo = $consulta->fetch(PDO::FETCH_ASSOC);
            if (!$articulo) {
                throw new DomainException('El artículo ya no está en tu carrito.');
            }

            if ($accion === 'eliminar') {
                $consulta = $conn->prepare(
                    'DELETE FROM Articulo WHERE idArticulo = :articulo AND idTicket = :ticket'
                );
                $consulta->execute(['articulo' => $articuloId, 'ticket' => $ticketId]);
                $mensajeExito = 'Artículo eliminado del carrito.';
            } else {
                $cantidad = validarEnteroPostMain('cantidad', 'La cantidad debe ser un número mayor que cero.', 99999);
                $consulta = $conn->prepare(
                    'SELECT precio, stock FROM Item WHERE idItem = :item FOR UPDATE'
                );
                $consulta->execute(['item' => $articulo['idItem']]);
                $item = $consulta->fetch(PDO::FETCH_ASSOC);

                if (!$item) {
                    throw new DomainException('La talla ya no está disponible.');
                }
                if ($item['precio'] === null) {
                    throw new DomainException('Esta talla no tiene un precio disponible.');
                }
                if ($cantidad > (int) $item['stock']) {
                    throw new DomainException('No hay suficientes existencias para esa cantidad.');
                }

                $totalArticulo = number_format((float) $item['precio'] * $cantidad, 2, '.', '');
                $consulta = $conn->prepare(
                    'UPDATE Articulo SET cant = :cantidad, total = :total
                     WHERE idArticulo = :articulo AND idTicket = :ticket'
                );
                $consulta->execute([
                    'cantidad' => $cantidad,
                    'total' => $totalArticulo,
                    'articulo' => $articuloId,
                    'ticket' => $ticketId,
                ]);
                $mensajeExito = 'Cantidad actualizada.';
            }

            recalcularTicketMain($conn, $ticketId, $usuarioId);
        }

        $conn->commit();
        if ($accion === 'pagar') {
            $_SESSION['compra_completada'] = true;
            unset($_SESSION['carrito_exito']);
        } else {
            $_SESSION['carrito_exito'] = $mensajeExito;
        }
    } catch (DomainException $e) {
        if ($conn instanceof PDO && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['carrito_error'] = $e->getMessage();
    } catch (PDOException $e) {
        if ($conn instanceof PDO && $conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('Error al actualizar carrito: ' . $e->getMessage());
        $_SESSION['carrito_error'] = 'No fue posible actualizar el carrito. Inténtalo de nuevo.';
    }

    header('Location: main.php#carrito');
    exit();
}

$error = $_SESSION['carrito_error'] ?? '';
$exito = $_SESSION['carrito_exito'] ?? '';
$compraCompletada = !empty($_SESSION['compra_completada']);
unset($_SESSION['carrito_error'], $_SESSION['carrito_exito'], $_SESSION['compra_completada']);
$zapatos = [];
$ticket = null;
$articulosCarrito = [];

try {
    $consulta = $conn->query(
        'SELECT z.idZapato, z.nombre, z.img, m.nombre AS marca, t.nombre AS tipo,
                i.idItem, i.talla, i.precio, i.stock
         FROM Zapato z
         LEFT JOIN Marca m ON m.idMarca = z.idMarca
         LEFT JOIN TipoZapato t ON t.idTipoZapato = z.idTipoZapato
         LEFT JOIN Item i ON i.idZapato = z.idZapato
         WHERE EXISTS (
             SELECT 1
             FROM Item disponible
             WHERE disponible.idZapato = z.idZapato AND disponible.stock > 0
         )
         ORDER BY z.idZapato DESC, i.talla'
    );
    $filasZapatos = $consulta->fetchAll(PDO::FETCH_ASSOC);
    $zapatosPorId = [];

    foreach ($filasZapatos as $fila) {
        $zapatoId = (int) $fila['idZapato'];
        if (!isset($zapatosPorId[$zapatoId])) {
            $zapatosPorId[$zapatoId] = [
                'idZapato' => $zapatoId,
                'nombre' => $fila['nombre'],
                'img' => $fila['img'],
                'marca' => $fila['marca'],
                'tipo' => $fila['tipo'],
                'tallas' => [],
            ];
        }

        if ($fila['idItem'] !== null) {
            $zapatosPorId[$zapatoId]['tallas'][] = [
                'idItem' => $fila['idItem'],
                'talla' => $fila['talla'],
                'precio' => $fila['precio'],
                'stock' => $fila['stock'],
            ];
        }
    }
    $zapatos = array_values($zapatosPorId);

    $consulta = $conn->prepare(
        'SELECT idTicket, total
         FROM Ticket
         WHERE idUsuario = :usuario AND pagado = 0
         ORDER BY idTicket DESC
         LIMIT 1'
    );
    $consulta->execute(['usuario' => (int) $_SESSION['user_id']]);
    $ticket = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($ticket) {
        $consulta = $conn->prepare(
            'SELECT a.idArticulo, a.cant AS cantidad, a.total AS totalArticulo,
                    i.idItem, i.talla, i.precio, i.stock,
                    z.nombre AS zapato, z.img
             FROM Articulo a
             JOIN Item i ON i.idItem = a.idItem
             JOIN Zapato z ON z.idZapato = i.idZapato
             WHERE a.idTicket = :ticket
             ORDER BY a.idArticulo'
        );
        $consulta->execute(['ticket' => $ticket['idTicket']]);
        $articulosCarrito = $consulta->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Error al cargar catálogo y carrito: ' . $e->getMessage());
    $error = 'No fue posible cargar el catálogo o el carrito. Inténtalo de nuevo más tarde.';
}

$cantidadTotal = 0;
foreach ($articulosCarrito as $articulo) {
    $cantidadTotal += (int) $articulo['cantidad'];
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>RIMO | Catálogo</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <header class="site-header">
            <div class="header-inner">
                <a class="brand" href="main.php" aria-label="RIMO, inicio">RIMO</a>
                <div class="header-actions">
                    <?php if ($esAdministrador): ?>
                        <a class="header-link" href="admin.php">Administrar</a>
                    <?php endif; ?>
                    <a class="header-link" href="#carrito">Mi carrito (<?= $cantidadTotal ?>)</a>
                    <form class="logout-form" method="post" action="logout.php">
                        <input type="hidden" name="csrf_token" value="<?= escaparMain((string) $_SESSION['csrf_token']) ?>">
                        <button class="logout-button" type="submit">Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="page-shell catalog-page">
            <section class="welcome-card">
                <h1>Bienvenido <span><?= escaparMain((string) $_SESSION['user_name']) ?></span></h1>
                <p>Explora nuestro catálogo y encuentra tu próximo par favorito.</p>
            </section>

            <div class="catalog-layout">
                <section class="catalog-section" id="catalogo" aria-labelledby="catalog-title">
                    <div class="catalog-heading">
                        <div>
                            <p class="catalog-eyebrow">RIMO</p>
                            <h2 id="catalog-title">Catálogo de zapatos</h2>
                        </div>
                        <span class="catalog-count"><?= count($zapatos) ?> modelos</span>
                    </div>

                    <?php if (!$zapatos): ?>
                        <p class="catalog-empty">Por el momento no hay zapatos disponibles.</p>
                    <?php else: ?>
                        <div class="catalog-grid">
                            <?php foreach ($zapatos as $zapato): ?>
                                <?php
                                $nombre = (string) ($zapato['nombre'] ?? 'Zapato sin nombre');
                                $imagenValida = imagenUrlValida($zapato['img']);
                                $dialogoId = 'producto-' . (int) $zapato['idZapato'];
                                ?>
                                <article class="product-card">
                                    <div class="product-image-wrap">
                                        <?php if ($imagenValida): ?>
                                            <img
                                                class="product-image"
                                                src="<?= escaparMain((string) $zapato['img']) ?>"
                                                alt="<?= escaparMain($nombre) ?>"
                                                loading="lazy"
                                            >
                                        <?php else: ?>
                                            <span class="product-image-placeholder">Imagen no disponible</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-card-body">
                                        <p class="product-brand"><?= escaparMain((string) ($zapato['marca'] ?? 'RIMO')) ?></p>
                                        <h3><?= escaparMain($nombre) ?></h3>
                                        <p class="product-type"><?= escaparMain((string) ($zapato['tipo'] ?? 'Calzado')) ?></p>
                                        <button class="product-button" type="button" data-dialog-open="<?= escaparMain($dialogoId) ?>">Más información</button>
                                    </div>
                                </article>

                                <dialog class="product-dialog" id="<?= escaparMain($dialogoId) ?>" aria-labelledby="<?= escaparMain($dialogoId) ?>-title">
                                    <div class="dialog-content">
                                        <form method="dialog" class="dialog-close-form">
                                            <button class="dialog-close" type="submit" aria-label="Cerrar">×</button>
                                        </form>
                                        <div class="detail-image-wrap">
                                            <?php if ($imagenValida): ?>
                                                <img
                                                    class="detail-image"
                                                    src="<?= escaparMain((string) $zapato['img']) ?>"
                                                    alt="<?= escaparMain($nombre) ?>"
                                                >
                                            <?php else: ?>
                                                <span class="product-image-placeholder">Imagen no disponible</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="dialog-info">
                                            <p class="product-brand"><?= escaparMain((string) ($zapato['marca'] ?? 'RIMO')) ?></p>
                                            <h2 id="<?= escaparMain($dialogoId) ?>-title"><?= escaparMain($nombre) ?></h2>
                                            <p class="product-type"><?= escaparMain((string) ($zapato['tipo'] ?? 'Calzado')) ?></p>
                                            <h3>Tallas y disponibilidad</h3>

                                            <?php if (!$zapato['tallas']): ?>
                                                <p class="catalog-empty">No hay tallas registradas para este modelo.</p>
                                            <?php else: ?>
                                                <div class="size-list">
                                                    <?php foreach ($zapato['tallas'] as $talla): ?>
                                                        <div class="size-row">
                                                            <span>Talla <?= escaparMain(rtrim(rtrim(number_format((float) $talla['talla'], 2, '.', ''), '0'), '.')) ?></span>
                                                            <span>$<?= number_format((float) $talla['precio'], 2) ?></span>
                                                            <span class="size-stock">Disponibles: <?= (int) $talla['stock'] ?></span>
                                                            <?php if ((int) $talla['stock'] > 0 && $talla['precio'] !== null): ?>
                                                                <form class="add-cart-form" method="post" action="main.php">
                                                                    <input type="hidden" name="csrf_token" value="<?= escaparMain($_SESSION['csrf_token']) ?>">
                                                                    <input type="hidden" name="action" value="agregar">
                                                                    <input type="hidden" name="item_id" value="<?= (int) $talla['idItem'] ?>">
                                                                    <label for="cantidad-<?= (int) $talla['idItem'] ?>">Cantidad</label>
                                                                    <input
                                                                        type="number"
                                                                        id="cantidad-<?= (int) $talla['idItem'] ?>"
                                                                        name="cantidad"
                                                                        min="1"
                                                                        max="<?= (int) $talla['stock'] ?>"
                                                                        value="1"
                                                                        required
                                                                    >
                                                                    <button class="product-button" type="submit">Agregar</button>
                                                                </form>
                                                            <?php else: ?>
                                                                <span class="out-of-stock">Agotado o sin precio</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </dialog>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <aside class="cart-sidebar" id="carrito" aria-labelledby="cart-title">
                    <div class="cart-sidebar-heading">
                        <h2 id="cart-title">Mi carrito</h2>
                        <span><?= $cantidadTotal ?> producto<?= $cantidadTotal === 1 ? '' : 's' ?></span>
                    </div>

                    <?php if ($error !== ''): ?>
                        <p class="cart-inline-error"><?= escaparMain($error) ?></p>
                    <?php elseif ($exito !== ''): ?>
                        <p class="cart-inline-success"><?= escaparMain($exito) ?></p>
                    <?php endif; ?>

                    <?php if (!$articulosCarrito): ?>
                        <p class="cart-sidebar-empty">Tu carrito está vacío.</p>
                    <?php else: ?>
                        <div class="cart-sidebar-items">
                            <?php foreach ($articulosCarrito as $articulo): ?>
                                <?php $imagenCarritoValida = imagenUrlValida($articulo['img']); ?>
                                <article class="side-cart-item">
                                    <?php if ($imagenCarritoValida): ?>
                                        <img
                                            class="side-cart-image"
                                            src="<?= escaparMain((string) $articulo['img']) ?>"
                                            alt="<?= escaparMain((string) $articulo['zapato']) ?>"
                                            loading="lazy"
                                        >
                                    <?php endif; ?>
                                    <div class="side-cart-info">
                                        <h3><?= escaparMain((string) $articulo['zapato']) ?></h3>
                                        <p>Talla <?= escaparMain(rtrim(rtrim(number_format((float) $articulo['talla'], 2, '.', ''), '0'), '.')) ?> · $<?= number_format((float) $articulo['precio'], 2) ?></p>
                                        <strong>$<?= number_format((float) $articulo['totalArticulo'], 2) ?></strong>
                                    </div>
                                    <div class="side-cart-actions">
                                        <form method="post" action="main.php" class="side-cart-quantity">
                                            <input type="hidden" name="csrf_token" value="<?= escaparMain($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="actualizar">
                                            <input type="hidden" name="articulo_id" value="<?= (int) $articulo['idArticulo'] ?>">
                                            <input
                                                type="number"
                                                name="cantidad"
                                                min="1"
                                                max="99999"
                                                value="<?= (int) $articulo['cantidad'] ?>"
                                                aria-label="Cantidad de <?= escaparMain((string) $articulo['zapato']) ?>"
                                                required
                                            >
                                            <button class="small-button" type="submit">Actualizar</button>
                                        </form>
                                        <form method="post" action="main.php">
                                            <input type="hidden" name="csrf_token" value="<?= escaparMain($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="eliminar">
                                            <input type="hidden" name="articulo_id" value="<?= (int) $articulo['idArticulo'] ?>">
                                            <button class="remove-button" type="submit">Quitar</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <div class="cart-sidebar-total">
                            <span>Total del ticket</span>
                            <strong>$<?= number_format((float) $ticket['total'], 2) ?></strong>
                        </div>
                        <p class="cart-pending-note">Ticket pendiente de pago.</p>
                        <form class="cart-payment-form" method="post" action="main.php">
                            <input type="hidden" name="csrf_token" value="<?= escaparMain($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="pagar">
                            <button class="primary-button" type="submit">Pagar</button>
                        </form>
                    <?php endif; ?>
                </aside>
            </div>
        </main>

        <footer class="site-footer">© <?= date('Y') ?> RIMO. Hecho para acompañarte.</footer>

        <dialog
            class="purchase-dialog"
            id="purchase-success-dialog"
            aria-labelledby="purchase-success-title"
            data-open="<?= $compraCompletada ? 'true' : 'false' ?>"
        >
            <form method="dialog" class="dialog-close-form">
                <button class="dialog-close" type="submit" aria-label="Cerrar">×</button>
            </form>
            <div class="purchase-dialog-content">
                <p class="catalog-eyebrow">RIMO</p>
                <h2 id="purchase-success-title">¡Gracias por comprar en RIMO!</h2>
                <p>Tu compra se registró correctamente.</p>
                <form method="dialog">
                    <button class="primary-button" type="submit">Seguir comprando</button>
                </form>
            </div>
        </dialog>

        <script>
            document.addEventListener('click', function (event) {
                const button = event.target.closest('[data-dialog-open]');
                if (button) {
                    const dialog = document.getElementById(button.dataset.dialogOpen);
                    if (dialog) {
                        dialog.showModal();
                    }
                }

                if (event.target.matches('dialog.product-dialog, dialog.purchase-dialog')) {
                    event.target.close();
                }
            });

            const purchaseDialog = document.getElementById('purchase-success-dialog');
            if (purchaseDialog && purchaseDialog.dataset.open === 'true') {
                purchaseDialog.showModal();
            }
        </script>
    </body>
</html>
