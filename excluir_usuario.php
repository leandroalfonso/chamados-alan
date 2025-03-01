<?php
session_start();
require_once 'db_connection.php';


if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'Administrador') {
    header('Location: index.php');
    exit();
}

if (isset($_GET['id'])) {
    $id_usuario = $_GET['id'];

    // Não permitir que o administrador exclua a si mesmo
    if ($id_usuario == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "Você não pode excluir seu próprio usuário.";
        header('Location: gerenciar_usuarios.php');
        exit();
    }

    try {
       
        mysqli_begin_transaction($conn);

       
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) as count 
            FROM chamados 
            WHERE id_tecnico = ? AND status != 'Fechado'
        ");
        mysqli_stmt_bind_param($stmt, "i", $id_usuario);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        if ($row['count'] > 0) {
            throw new Exception("Este usuário possui chamados em andamento como técnico e não pode ser excluído.");
        }

        // Atualizar chamados onde o usuário é técnico para remover a referência
        $stmt = mysqli_prepare($conn, "
            UPDATE chamados 
            SET id_tecnico = NULL 
            WHERE id_tecnico = ? AND status = 'Fechado'
        ");
        mysqli_stmt_bind_param($stmt, "i", $id_usuario);
        mysqli_stmt_execute($stmt);

        
        $stmt = mysqli_prepare($conn, "DELETE FROM usuarios WHERE id_usuario = ?");
        mysqli_stmt_bind_param($stmt, "i", $id_usuario);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_commit($conn);
            $_SESSION['success_message'] = "Usuário excluído com sucesso!";
        } else {
            throw new Exception("Erro ao excluir usuário.");
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = "ID do usuário não fornecido.";
}

header('Location: gerenciar_usuarios.php');
exit();
?>
