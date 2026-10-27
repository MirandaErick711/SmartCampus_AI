<?php
/**
 * SmartCampus AI - Conexion Centralizada a Base de Datos
 * Persistencia relacional mediante PHP Data Objects (PDO)
 */

$host = 'localhost';
$dbname = 'smartcampus_db';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Error al conectar con la base de datos MySQL: " . $e->getMessage());
}
