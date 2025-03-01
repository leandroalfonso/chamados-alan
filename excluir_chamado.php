<?php
session_start();
require_once 'db_connection.php';


if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'Administrador') {
    header('Location: index.php');
    exit();
}

if (!isset($_POST['id_chamado'])) {
    $_SESSION['error_message'] = "ID do chamado não fornecido.";
    header('Location: dashboard.php');
    exit();
}

$id_chamado = $_POST['id_chamado'];

try {
  
    $stmt = mysqli_prepare($conn, "DELETE FROM chamados WHERE id_chamado = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_chamado);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "Chamado excluído com sucesso!";
    } else {
        throw new Exception("Erro ao excluir o chamado.");
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
}

header('Location: dashboard.php');
exit();