<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if (!isset($_POST['id_chamado']) || !isset($_POST['justificativa'])) {
    $_SESSION['error_message'] = "Dados incompletos para solicitar reabertura.";
    header('Location: dashboard.php');
    exit();
}

$id_chamado = $_POST['id_chamado'];
$id_usuario = $_SESSION['user_id'];
$justificativa = strip_tags(trim($_POST['justificativa'])); // Remove tags HTML antes de salvar

try {
    // Verificar se o chamado existe e está fechado
    $stmt = mysqli_prepare($conn, "SELECT status, id_usuario FROM chamados WHERE id_chamado = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_chamado);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $chamado = mysqli_fetch_assoc($result);

    if (!$chamado) {
        throw new Exception("Chamado não encontrado.");
    }

    if ($chamado['status'] !== 'Fechado') {
        throw new Exception("Apenas chamados fechados podem receber solicitação de reabertura.");
    }

    if ($chamado['id_usuario'] !== $id_usuario) {
        throw new Exception("Apenas o criador do chamado pode solicitar reabertura.");
    }

    // Verificar se já existe uma solicitação pendente
    $stmt = mysqli_prepare($conn, "SELECT id_solicitacao FROM solicitacoes_reabertura WHERE id_chamado = ? AND status = 'Pendente'");
    mysqli_stmt_bind_param($stmt, "i", $id_chamado);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_fetch_assoc($result)) {
        throw new Exception("Já existe uma solicitação de reabertura pendente para este chamado.");
    }

    // Criar solicitação de reabertura
    $stmt = mysqli_prepare($conn, "INSERT INTO solicitacoes_reabertura (id_chamado, id_usuario, justificativa, data_solicitacao, status) VALUES (?, ?, ?, NOW(), 'Pendente')");
    mysqli_stmt_bind_param($stmt, "iis", $id_chamado, $id_usuario, $justificativa);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "Solicitação de reabertura enviada com sucesso!";
    } else {
        throw new Exception("Erro ao criar solicitação de reabertura.");
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
}

header('Location: ver_chamado.php?id=' . $id_chamado);
exit();