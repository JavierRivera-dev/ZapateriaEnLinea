<?php
require_once __DIR__ . '/connect.php';

function registrarUsuario(PDO $conn, array $datos): bool
{
    $consulta = $conn->prepare('SELECT idUsuario FROM usuario WHERE correo = :correo');
    $consulta->execute(['correo' => $datos['correo']]);

    if ($consulta->fetch(PDO::FETCH_ASSOC)) {
        return false;
    }

    $consulta = $conn->prepare(
        'INSERT INTO usuario (nombre, apellido_p, apellido_m, correo, telefono, rol, activo, pass)
         VALUES (:nombre, :apellido_p, :apellido_m, :correo, :telefono, 0, 1, :pass)'
    );

    return $consulta->execute([
        'nombre' => $datos['nombre'],
        'apellido_p' => $datos['apellido_p'],
        'apellido_m' => $datos['apellido_m'],
        'correo' => $datos['correo'],
        'telefono' => $datos['telefono'],
        'pass' => password_hash($datos['pass'], PASSWORD_DEFAULT),
    ]);
}

$error = '';
$registrado = false;
$datos = [
    'nombre' => '',
    'apellido_p' => '',
    'apellido_m' => '',
    'correo' => '',
    'telefono' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($datos as $campo => $valor) {
        $entrada = $_POST[$campo] ?? '';
        $datos[$campo] = is_string($entrada) ? trim($entrada) : '';
    }
    $contrasena = $_POST['pass'] ?? '';
    $datos['pass'] = is_string($contrasena) ? $contrasena : '';

    if (
        $datos['nombre'] === '' ||
        $datos['apellido_p'] === '' ||
        $datos['apellido_m'] === '' ||
        $datos['correo'] === '' ||
        $datos['telefono'] === '' ||
        $datos['pass'] === ''
    ) {
        $error = 'Completa todos los campos.';
    } elseif (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un correo electrónico válido.';
    } elseif (strlen($datos['nombre']) > 80 || strlen($datos['apellido_p']) > 80 || strlen($datos['apellido_m']) > 80) {
        $error = 'El nombre y los apellidos no deben superar los 80 caracteres.';
    } elseif (strlen($datos['correo']) > 100) {
        $error = 'El correo no debe superar los 100 caracteres.';
    } elseif (!preg_match('/^\d{1,12}$/', $datos['telefono'])) {
        $error = 'El teléfono debe contener entre 1 y 12 dígitos.';
    } elseif (strlen($datos['pass']) > 72) {
        $error = 'La contraseña no debe superar los 72 caracteres.';
    } else {
        try {
            $registrado = registrarUsuario($conn, $datos);
            if (!$registrado) {
                $error = 'Ya existe una cuenta registrada con ese correo.';
            }
        } catch (PDOException $e) {
            error_log('Error al registrar usuario: ' . $e->getMessage());
            $error = 'No fue posible registrar el usuario. Inténtalo de nuevo.';
        }
    }

    if ($registrado) {
        $datos = [
            'nombre' => '',
            'apellido_p' => '',
            'apellido_m' => '',
            'correo' => '',
            'telefono' => '',
        ];
    }
}

function escapar(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>RIMO | Crear cuenta</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <header class="site-header">
            <div class="header-inner">
                <a class="brand" href="index.php" aria-label="RIMO, inicio">
                    <span>RIMO</span>
                </a>
                <span class="header-note">Calzado para cada paso</span>
            </div>
        </header>

        <main class="page-shell auth-layout">
            <section class="form-card" aria-labelledby="register-title">
                <div class="card-heading">
                    <h1 id="register-title">Crea tu cuenta</h1>
                    <p>Completa tus datos para comenzar.</p>
                </div>

                <?php if ($registrado): ?>
                    <p class="form-message form-message-success" role="status">¡Tu cuenta está lista! Ya puedes iniciar sesión.</p>
                <?php elseif ($error !== ''): ?>
                    <p class="form-message form-message-error" role="alert"><?= escapar($error) ?></p>
                <?php endif; ?>

                <form class="form-grid" method="post" action="register.php">
                    <div class="form-field">
                        <label for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" maxlength="80" value="<?= escapar($datos['nombre']) ?>" autocomplete="given-name" required>
                    </div>
                    <div class="form-field">
                        <label for="apellido_p">Apellido paterno</label>
                        <input type="text" id="apellido_p" name="apellido_p" maxlength="80" value="<?= escapar($datos['apellido_p']) ?>" autocomplete="family-name" required>
                    </div>
                    <div class="form-field">
                        <label for="apellido_m">Apellido materno</label>
                        <input type="text" id="apellido_m" name="apellido_m" maxlength="80" value="<?= escapar($datos['apellido_m']) ?>" required>
                    </div>
                    <div class="form-field">
                        <label for="correo">Correo electrónico</label>
                        <input type="email" id="correo" name="correo" maxlength="100" value="<?= escapar($datos['correo']) ?>" autocomplete="email" required>
                    </div>
                    <div class="form-field">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" maxlength="12" pattern="[0-9]{1,12}" value="<?= escapar($datos['telefono']) ?>" autocomplete="tel" required>
                    </div>
                    <div class="form-field">
                        <label for="pass">Contraseña</label>
                        <input type="password" id="pass" name="pass" maxlength="72" autocomplete="new-password" required>
                    </div>
                    <button class="primary-button" type="submit">Crear cuenta <span aria-hidden="true">→</span></button>
                </form>

                <p class="form-footer">¿Ya tienes cuenta? <a href="index.php">Inicia sesión</a></p>
            </section>
        </main>

        <footer class="site-footer">© <?= date('Y') ?> RIMO. Hecho para acompañarte.</footer>
    </body>
</html>
