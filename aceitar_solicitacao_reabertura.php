<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if (!isset($_POST['id_solicitacao'])) {
    $_SESSION['error_message'] = "ID da solicitação não fornecido.";
    header('Location: dashboard.php');
    exit();
}

$id_solicitacao = $_POST['id_solicitacao'];
$id_tecnico = $_SESSION['user_id'];

try {
    // Verificar se a solicitação existe e está pendente
    $stmt = mysqli_prepare($conn, "SELECT sr.*, c.id_usuario, c.id_chamado, u.nome as nome_tecnico 
                                 FROM solicitacoes_reabertura sr 
                                 INNER JOIN chamados c ON sr.id_chamado = c.id_chamado 
                                 INNER JOIN usuarios u ON u.id_usuario = ?
                                 WHERE sr.id_solicitacao = ? AND sr.status = 'Pendente'");
    mysqli_stmt_bind_param($stmt, "ii", $id_tecnico, $id_solicitacao);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $solicitacao = mysqli_fetch_assoc($result);

    if (!$solicitacao) {
        throw new Exception("Solicitação não encontrada ou já processada.");
    }

    mysqli_begin_transaction($conn);

    try {
        // Atualizar status da solicitação para Aceita
        $stmt = mysqli_prepare($conn, "UPDATE solicitacoes_reabertura SET status = 'Aprovada', data_resposta = NOW(), id_responsavel = ? WHERE id_solicitacao = ?");
        mysqli_stmt_bind_param($stmt, "ii", $id_tecnico, $id_solicitacao);
        mysqli_stmt_execute($stmt);

        // Reabrir o chamado
        $stmt = mysqli_prepare($conn, "UPDATE chamados SET status = 'Em Andamento' WHERE id_chamado = ?");
        mysqli_stmt_bind_param($stmt, "i", $solicitacao['id_chamado']);
        mysqli_stmt_execute($stmt);

        // Criar notificação para o usuário sobre a reabertura
        $id_usuario = $solicitacao['id_usuario'];
        $id_chamado = $solicitacao['id_chamado'];
        $nome_tecnico = $solicitacao['nome_tecnico'];
        
        $mensagem_notificacao = "Sua solicitação de reabertura foi aprovada por " . $nome_tecnico;
        $stmt = mysqli_prepare($conn, "INSERT INTO notificacoes (id_usuario, tipo, mensagem, data_criacao, id_referencia) 
                                     VALUES (?, 'chamado_reaberto', ?, NOW(), ?)");
        mysqli_stmt_bind_param($stmt, "isi", $id_usuario, $mensagem_notificacao, $id_chamado);
        mysqli_stmt_execute($stmt);

        // Adicionar registro no histórico
        $comentario = "Solicitação de reabertura aprovada por " . $nome_tecnico;
        $stmt = mysqli_prepare($conn, "INSERT INTO historico_chamados (id_chamado, id_usuario, acao, comentario) 
                                     VALUES (?, ?, 'aprovacao_reabertura', ?)");
        mysqli_stmt_bind_param($stmt, "iis", $id_chamado, $id_tecnico, $comentario);
        mysqli_stmt_execute($stmt);

        // Remover notificação de solicitação de reabertura para o técnico
        $stmt = mysqli_prepare($conn, "DELETE FROM notificacoes WHERE tipo = 'solicitacao_reabertura' AND id_referencia = ?");
        mysqli_stmt_bind_param($stmt, "i", $id_chamado);
        mysqli_stmt_execute($stmt);

        mysqli_commit($conn);
        
        // Retornar resposta JSON para requisições AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Chamado reaberto com sucesso!']);
            exit;
        }
        
        $_SESSION['success_message'] = "Chamado reaberto com sucesso!";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }

} catch (Exception $e) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    $_SESSION['error_message'] = $e->getMessage();
}

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    exit;
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();