<?php
$configPath = __DIR__ . '/hosting-config.php';
if (!is_file($configPath)) {
    throw new RuntimeException('Falta hosting-config.php. Cópialo desde hosting-config.example.php y completa los datos del hosting.');
}

$config = require $configPath;
if (!is_array($config)) {
    throw new RuntimeException('hosting-config.php debe devolver un arreglo de configuración.');
}

foreach (['host', 'database', 'username'] as $key) {
    if (!isset($config[$key]) || !is_string($config[$key]) || $config[$key] === '') {
        throw new RuntimeException('La configuración de la base de datos está incompleta.');
    }
}
if (!array_key_exists('password', $config) || !is_string($config['password'])) {
    throw new RuntimeException('La configuración de la base de datos está incompleta.');
}

try {
    $conn = new PDO(
        'mysql:host=' . $config['host'] . ';dbname=' . $config['database'] . ';port=3306;charset=utf8mb4',
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    error_log('Error al conectar con la base del hosting: ' . $e->getMessage());
    throw $e;
}
