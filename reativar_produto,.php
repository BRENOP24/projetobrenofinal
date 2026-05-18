<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        $stmt = $pdo->prepare("UPDATE produtos SET status = 'Ativo' WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: produtos.php?res=reativado");
    } catch (PDOException $e) {
        die("Erro ao reativar: " . $e->getMessage());
    }
} else {
    header("Location: produtos.php");
}
exit;