<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['id_chamado']) || !isset($_POST['novo_status'])) {
    header('Location: dashboard.php');
    exit();
}

$id_chamado = $_POST['id_chamado'];
$novo_status = $_POST['novo_status'];
$id_usuario = $_SESSION['user_id'];
$cargo = $_SESSION['cargo'];

try {
    // Verificar permissão
    $stmt = mysqli_prepare($conn, "SELECT c.*, u.nome as usuario_nome, 
                                        t.nome as tecnico_nome
                                 FROM chamados c 
                                 JOIN usuarios u ON c.id_usuario = u.id_usuario 
                                 LEFT JOIN usuarios t ON t.id_usuario = ?
                                 WHERE c.id_chamado = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id_usuario, $id_chamado);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $chamado = mysqli_fetch_assoc($result);

    if (!$chamado || ($cargo !== 'Administrador' && $id_usuario !== $chamado['id_tecnico'])) {
        $_SESSION['error_message'] = "Você não tem permissão para atualizar este chamado.";
        header('Location: ver_chamado.php?id=' . $id_chamado);
        exit();
    }

    mysqli_begin_transaction($conn);

    // Atualizar status do chamado
    $stmt = mysqli_prepare($conn, "UPDATE chamados SET status = ?, data_fechamento = ? WHERE id_chamado = ?");
    $data_fechamento = $novo_status === 'Fechado' ? date('Y-m-d H:i:s') : null;
    mysqli_stmt_bind_param($stmt, "ssi", $novo_status, $data_fechamento, $id_chamado);
    
    if (mysqli_stmt_execute($stmt)) {
        // Se o chamado foi fechado, adicionar comentário automático
        if ($novo_status === 'Fechado') {
            $comentario = "Chamado encerrado por: " . $chamado['tecnico_nome'];
            $stmt = mysqli_prepare($conn, "INSERT INTO comentarios (id_chamado, id_usuario, comentario) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iis", $id_chamado, $id_usuario, $comentario);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_commit($conn);
                $_SESSION['success_message'] = "Status do chamado atualizado com sucesso!";
            } else {
                mysqli_rollback($conn);
                $_SESSION['error_message'] = "Erro ao adicionar comentário de encerramento.";
            }
        } else {
            mysqli_commit($conn);
            $_SESSION['success_message'] = "Status do chamado atualizado com sucesso!";
        }
    } else {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Erro ao atualizar status do chamado.";
    }
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error_message'] = "Erro ao processar a atualização: " . $e->getMessage();
}

header('Location: ver_chamado.php?id=' . $id_chamado);
exit();
