<?php
    $host = "localhost";
    $dbname = "sistema_zapateria"; 
    $username = "root";
    $password = "";

    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 

    } catch (PDOException $e) {
        error_log("Error de conexión a la base de datos: " . $e->getMessage());
        throw $e;
    }
?>