<?php
session_start();
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>RIMO | Iniciar sesión</title>
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
            <section class="form-card" aria-labelledby="login-title">
                <div class="card-heading">
                    <h1 id="login-title">Iniciar sesión</h1>
                    <p>Inicia sesión para continuar.</p>
                </div>

                <?php if ($error !== ''): ?>
                    <p class="form-message form-message-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="form-grid" method="post" action="login.php">
                    <div class="form-field">
                        <label for="correo">Correo electrónico</label>
                        <input type="email" id="correo" name="correo" placeholder="tu@correo.com" autocomplete="email" required>
                    </div>
                    <div class="form-field">
                        <label for="pass">Contraseña</label>
                        <input type="password" id="pass" name="pass" placeholder="Tu contraseña" autocomplete="current-password" required>
                    </div>
                    <button class="primary-button" type="submit">Iniciar sesión <span aria-hidden="true">→</span></button>
                </form>

                <p class="form-footer">¿Aún no tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
            </section>
        </main>

        <footer class="site-footer">© <?= date('Y') ?> RIMO. Hecho para acompañarte.</footer>
    </body>
</html>