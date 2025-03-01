<?php
session_start();
require_once 'db_connection.php';

// Verificar se o usuário está logado e tem permissão (admin ou técnico)
if (!isset($_SESSION['user_id']) || ($_SESSION['cargo'] !== 'Administrador' && $_SESSION['cargo'] !== 'Técnico')) {
    header('Location: index.php');
    exit();
}

if (!isset($_POST['id_chamado'])) {
    $_SESSION['error_message'] = "ID do chamado não fornecido.";
    header('Location: dashboard.php');
    exit();
}

$id_chamado = $_POST['id_chamado'];
$id_usuario = $_SESSION['user_id'];
$cargo = $_SESSION['cargo'];

try {
    // Verificar se o chamado existe e está fechado
    $stmt = mysqli_prepare($conn, "SELECT id_tecnico, status FROM chamados WHERE id_chamado = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_chamado);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $chamado = mysqli_fetch_assoc($result);

    if (!$chamado) {
        throw new Exception("Chamado não encontrado.");
    }

    if ($chamado['status'] !== 'Fechado') {
        throw new Exception("Apenas chamados fechados podem ser reabertos.");
    }

    // Verificar se o usuário tem permissão (admin ou técnico atribuído)
    if ($cargo !== 'Administrador' && $chamado['id_tecnico'] !== $id_usuario) {
        throw new Exception("Você não tem permissão para reabrir este chamado.");
    }

    // Iniciar transação
    mysqli_begin_transaction($conn);

    // Reabrir o chamado
    $stmt = mysqli_prepare($conn, "UPDATE chamados SET status = 'Em Andamento', data_fechamento = NULL WHERE id_chamado = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_chamado);
    
    if (mysqli_stmt_execute($stmt)) {
        // Adicionar comentário automático
        $nome_usuario = $_SESSION['nome'];
        $comentario = "Chamado reaberto por " . htmlspecialchars($nome_usuario);
        $stmt = mysqli_prepare($conn, "INSERT INTO comentarios (id_chamado, id_usuario, comentario) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iis", $id_chamado, $id_usuario, $comentario);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_commit($conn);
            $_SESSION['success_message'] = "Chamado reaberto com sucesso!";
        } else {
            throw new Exception("Erro ao adicionar comentário de reabertura.");
        }
    } else {
        throw new Exception("Erro ao reabrir o chamado.");
    }

    header('Location: ver_chamado.php?id=' . $id_chamado);
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: ver_chamado.php?id=' . $id_chamado);
    exit();
}