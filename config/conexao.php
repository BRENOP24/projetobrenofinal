<?php

$host = '127.0.0.1'; 
$port = '5432';
$db   = 'projeto_breno_backup'; 
$user = 'postgres';
$pass = 'root'; 

try {

    // Conexão PostgreSQL Render
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require;connect_timeout=5";

    $pdo = new PDO($dsn, $user, $pass);

    // Habilita exceções
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Opcional
    $pdo->exec("SET search_path TO public");
    
    // CORREÇÃO DO FUSO HORÁRIO NO POSTGRESQL (Render/UTC -> Brasília)
    $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");

} catch (PDOException $e) {

    die("Erro crítico na conexão com PostgreSQL: " . $e->getMessage());

}

?>
