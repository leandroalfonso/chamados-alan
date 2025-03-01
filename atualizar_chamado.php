<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_chamado']) && isset($_POST['status'])) {
    $id_chamado = $_POST['id_chamado'];
    $novo_status = $_POST['status'];
    $id_usuario = $_SESSION['id_usuario'];
    $cargo = $_SESSION['cargo'];

    try {
        
        $stmt = mysqli_prepare($conn, "SELECT id_usuario, id_tecnico FROM chamados WHERE id_chamado = ?");
        mysqli_stmt_bind_param($stmt, "i", $id_chamado);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $chamado = mysqli_fetch_assoc($result);

        if (!$chamado) {
            $_SESSION['error_message'] = "Chamado não encontrado.";
            header('Location: dashboard.php');
            exit;
        }

        // Verificar permissões
        $tem_permissao = (
            ($cargo === 'Técnico' && $chamado['id_tecnico'] == $id_usuario) || 
            ($cargo === 'Usuário' && $chamado['id_usuario'] == $id_usuario)
        );

        if (!$tem_permissao) {
            $_SESSION['error_message'] = "Você não tem permissão para atualizar este chamado.";
            header('Location: dashboard.php');
            exit;
        }

        // Atualizar o status do chamado
        if ($novo_status === 'Fechado') {
            $stmt = mysqli_prepare($conn, "UPDATE chamados SET status = ?, data_fechamento = CURRENT_TIMESTAMP WHERE id_chamado = ?");
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE chamados SET status = ?, data_fechamento = NULL WHERE id_chamado = ?");
        }

        mysqli_stmt_bind_param($stmt, "si", $novo_status, $id_chamado);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Status do chamado atualizado com sucesso!";
        } else {
            $_SESSION['error_message'] = "Erro ao atualizar o status do chamado.";
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erro ao processar a solicitação: " . $e->getMessage();
    }
}

header('Location: dashboard.php');
exit;
?>
