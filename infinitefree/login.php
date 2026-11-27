<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$correo = $_POST['correo'] ?? '';
$contrasena = $_POST['pass'] ?? '';

if (!is_string($correo) || !is_string($contrasena) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['login_error'] = 'Correo o contraseña incorrectos.';
    header('Location: index.php');
    exit();
}

try {
    require_once __DIR__ . '/connect.php';
    $consulta = $conn->prepare('SELECT idUsuario, nombre, pass, activo FROM usuario WHERE correo = :correo LIMIT 1');
    $consulta->execute(['correo' => trim($correo)]);
    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$usuario || !password_verify($contrasena, $usuario['pass'])) {
        $_SESSION['login_error'] = 'Correo o contraseña incorrectos.';
        header('Location: index.php');
        exit();
    }

    if ((int) $usuario['activo'] !== 1) {
        $_SESSION['login_error'] = 'Esta cuenta está dada de baja. Contacta a un administrador.';
        header('Location: index.php');
        exit();
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $usuario['idUsuario'];
    $_SESSION['user_name'] = (string) $usuario['nombre'];

    header('Location: main.php');
    exit();
} catch (PDOException $e) {
    error_log('Error al iniciar sesión: ' . $e->getMessage());
    $_SESSION['login_error'] = 'No fue posible iniciar sesión. Inténtalo de nuevo.';
    header('Location: index.php');
    exit();
}
