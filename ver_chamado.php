<?php
require_once 'includes/config.php';
require_once 'db_connection.php';
require_once 'includes/header.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (!isset($_GET['id'])) {
    redirect('dashboard.php');
}

$id_chamado = $_GET['id'];
$id_usuario = $_SESSION['user_id'];
$cargo = $_SESSION['cargo'];

// Verificar permissão para ver o chamado
$stmt = mysqli_prepare($conn, "SELECT id_usuario, id_tecnico FROM chamados WHERE id_chamado = ?");
mysqli_stmt_bind_param($stmt, "i", $id_chamado);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$permissao_chamado = mysqli_fetch_assoc($result);

// Verifica se o usuário tem permissão para ver o chamado
if (!$permissao_chamado || 
    ($cargo !== 'Administrador' && 
     $id_usuario !== $permissao_chamado['id_usuario'] && 
     ($permissao_chamado['id_tecnico'] && $id_usuario !== $permissao_chamado['id_tecnico']))) {
    $_SESSION['error_message'] = "Você não tem permissão para visualizar este chamado.";
    header('Location: dashboard.php');
    exit();
}

try {
    // Buscar informações do chamado
    $stmt = mysqli_prepare($conn, "
        SELECT c.*, 
               u.nome as usuario_nome,
               u.email as usuario_email,
               t.nome as tecnico_nome,
               t.email as tecnico_email,
               (SELECT COUNT(*) FROM chamados WHERE id_tecnico = c.id_tecnico AND status = 'Fechado') as total_resolvidos,
               (SELECT COUNT(*) FROM chamados WHERE id_tecnico = c.id_tecnico) as total_atribuidos,
               (SELECT AVG(TIMESTAMPDIFF(HOUR, data_captura, data_fechamento)) 
                FROM chamados 
                WHERE id_tecnico = c.id_tecnico 
                AND status = 'Fechado' 
                AND data_fechamento IS NOT NULL) as tempo_medio_resolucao,
               (SELECT AVG(nota) FROM avaliacoes WHERE id_usuario = c.id_tecnico) as media_avaliacoes_tecnico,
               (SELECT COUNT(*) FROM avaliacoes WHERE id_usuario = c.id_tecnico) as total_avaliacoes_tecnico
        FROM chamados c
        LEFT JOIN usuarios u ON c.id_usuario = u.id_usuario
        LEFT JOIN usuarios t ON c.id_tecnico = t.id_usuario
        WHERE c.id_chamado = ?
    ");
    
    mysqli_stmt_bind_param($stmt, "i", $id_chamado);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $chamado = mysqli_fetch_assoc($result);

    if (!$chamado) {
        $_SESSION['error_message'] = "Chamado não encontrado.";
        header('Location: dashboard.php');
        exit();
    }

    // Buscar comentários do chamado
    $stmt = mysqli_prepare($conn, "
        SELECT c.*, u.nome as usuario_nome, u.cargo as usuario_cargo
        FROM comentarios c
        JOIN usuarios u ON c.id_usuario = u.id_usuario
        WHERE c.id_chamado = ?
        ORDER BY c.data_comentario ASC
    ");
    mysqli_stmt_bind_param($stmt, "i", $id_chamado);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $comentarios = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $comentarios[] = $row;
    }

    // Buscar anexos do chamado
    $stmt = mysqli_prepare($conn, "
        SELECT *
        FROM anexos
        WHERE id_chamado = ?
        ORDER BY data_upload ASC
    ");
    mysqli_stmt_bind_param($stmt, "i", $id_chamado);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $anexos = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $anexos[] = $row;
    }

    // Buscar solicitações de reabertura do chamado
    $stmt = mysqli_prepare($conn, "
        SELECT sr.*, u.nome as solicitante_nome
        FROM solicitacoes_reabertura sr
        JOIN usuarios u ON sr.id_usuario = u.id_usuario
        WHERE sr.id_chamado = ?
        ORDER BY sr.data_solicitacao DESC
    ");
    mysqli_stmt_bind_param($stmt, "i", $id_chamado);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $solicitacoes_reabertura = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $solicitacoes_reabertura[] = $row;
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = "Erro ao buscar chamado: " . $e->getMessage();
    header('Location: dashboard.php');
    exit();
}

function formatarData($data) {
    if (!$data) return 'N/A';
    return date('d/m/Y H:i', strtotime($data));
}

// Processar novo comentário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comentario'])) {
    try {
        $comentario = trim(strip_tags($_POST['comentario'])); // Remove tags HTML antes de salvar
        if (!empty($comentario)) {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO comentarios (id_chamado, id_usuario, comentario)
                VALUES (?, ?, ?)
            ");
            mysqli_stmt_bind_param($stmt, "iis", $id_chamado, $id_usuario, $comentario);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Comentário adicionado com sucesso!";
                header("Location: ver_chamado.php?id=" . $id_chamado);
                exit();
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erro ao adicionar comentário: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Chamado - Sistema de Chamados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        *{
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 15px;
        }
        .chamado-header {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 20px;
            margin-bottom: 24px;
            position: relative;
        }

        .chamado-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .chamado-id {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chamado-id .material-symbols-outlined {
            font-size: 28px;
            color: #4a5568;
        }

        .chamado-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .chamado-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .status-fechado {
            background-color: #def7ec;
            color: #046c4e;
        }

        .status-aberto {
            background-color: #e1effe;
            color: #1e429f;
        }

        .chamado-description {
            color: #4a5568;
            font-size: 1.1rem;
            margin-bottom: 16px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-capture {
            background-color: #3182ce;
            color: white;
        }

        .btn-capture:hover {
            background-color: #2c5282;
        }

        .btn-history {
            background-color: #805ad5;
            color: white;
        }

        .btn-history:hover {
            background-color: #6b46c1;
        }

        .btn-success {
            background-color: #48bb78;
            color: white;
        }

        .btn-success:hover {
            background-color: #2f855a;
        }

        @media (max-width: 768px) {
            .chamado-title-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .chamado-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .btn {
                padding: 10px 16px;
            }
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .info-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-section h5 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-section p {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: #555;
        }
        .info-section .material-symbols-outlined {
            color: #666;
        }
        .tech-stats {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .tech-stats p {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        .tech-stats .material-symbols-outlined {
            color: #0d6efd;
        }
        .stat-value {
            font-weight: 600;
            color: #0d6efd;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
        }
        .status-Aberto { background-color: #dc3545; color: white; }
        .status-Em-andamento { background-color: #ffc107; color: #000; }
        .status-Fechado { background-color: #28a745; color: white; }
        .prioridade-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .prioridade-Alta { background-color: #dc3545; color: white; }
        .prioridade-Média { background-color: #ffc107; color: #000; }
        .prioridade-Baixa { background-color: #28a745; color: white; }
        .description-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .description-section h5 {
            margin: 0 0 15px 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .attachments-section {
            margin: 20px 0;
        }
        .attachments-section img {
            cursor: pointer;
            transition: transform 0.2s;
        }
        .attachments-section img:hover {
            transform: scale(1.05);
        }
        .modal-image {
            max-width: 100%;
            height: auto;
        }
        .modal-body {
            padding: 0;
        }
        .modal-content {
            background-color: transparent;
            border: none;
        }
        .modal-header {
            border: none;
            padding: 1rem;
            background: rgba(0,0,0,0.5);
            color: white;
        }
        .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        .comentario {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .comentario-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            margin-bottom: 10px;
        }
        .comentario-text {
            color: #333;
            margin: 0;
        }
        .material-symbols-outlined {
            font-size: 20px;
            vertical-align: text-bottom;
        }
        /* Estilos para comentários formatados */
        .comentario {
            margin-bottom: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px;
        }
        .comentario-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: #666;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .comentario .card {
            margin-top: 10px;
            border: none;
            background: #f8f9fa;
        }
        .comentario .card-body {
            padding: 15px;
        }
        /* Estilos para o conteúdo do Summernote */
        .note-editor .note-editing-area {
            background: #fff;
        }
        .note-editor.note-frame {
            border-color: #dee2e6;
            width: 100%;
        }
        /* Estilos para tabelas nos comentários */
        .comentario table {
            width: 100%;
            margin-bottom: 1rem;
            border-collapse: collapse;
        }
        .comentario table td,
        .comentario table th {
            padding: 8px;
            border: 1px solid #dee2e6;
        }
        .comentario table thead th {
            background-color: #f8f9fa;
        }
        /* Estilos para listas nos comentários */
        .comentario ul,
        .comentario ol {
            padding-left: 20px;
            margin-bottom: 1rem;
        }
        .comments-section {
            max-width: 1200px;
            margin: 0 auto;
        }
        .novo-comentario {
            max-width: 1200px;
            margin: 0 auto;
        }
        .bi-arrow-left{
            font-size: 1rem;
        }
        .reopening-requests-section {
            margin-bottom: 1rem;
            max-width: 1200px;
            margin: 0 auto;
            background-color: #6495ED;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);

        }
        .avaliacao-usuario-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 8px 15px;
            margin-top: 10px;
            border: 1px solid #e9ecef;
            font-size: 0.9rem;
        }
        
        .estrelas {
            display: inline-flex;
            align-items: center;
        }
        
        .estrelas .material-symbols-outlined {
            font-size: 20px;
            width: 20px;
            height: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php
        // Verificar se já existe avaliação
        $avaliacao_existente = null;
        if ($chamado['status'] === 'Fechado' && $chamado['id_usuario'] === $id_usuario) {
            $stmt = mysqli_prepare($conn, "SELECT id_avaliacao FROM avaliacoes WHERE id_chamado = ? AND id_usuario = ?");
            mysqli_stmt_bind_param($stmt, "ii", $id_chamado, $id_usuario);
            mysqli_stmt_execute($stmt);
            $avaliacao_existente = mysqli_stmt_get_result($stmt)->fetch_assoc();
        }
        ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
           
            <div class="d-flex gap-2">
                <?php if ($chamado['status'] === 'Fechado' && $chamado['id_usuario'] === $id_usuario && !$avaliacao_existente): ?>
                    <a href="avaliar_chamado.php?id=<?php echo $id_chamado; ?>" class="btn btn-primary">
                        <i class="bi bi-star"></i> Avaliar Chamado
                    </a>
                <?php endif; ?>
                <a href="dashboard.php" style="align-self: flex-end;  position: relative;" class="btn btn-secondary"><i class="bi bi-arrow-left"></i></a>
            </div>
        </div>

        <div class="chamado-header">
            <div class="chamado-title-row">
                <div class="chamado-id">
                   
                    Chamado <?php echo $id_chamado; ?>
                </div>
                <div class="chamado-actions">
                    <?php if ($chamado['status'] === 'Fechado'): ?>
                        <div class="chamado-status status-fechado">
                            <span class="material-symbols-outlined">task_alt</span>
                            Fechado
                        </div>
                    <?php else: ?>
                        <div class="chamado-status status-aberto">
                            <span class="material-symbols-outlined">pending</span>
                            Aberto
                        </div>
                    <?php endif; ?>

                    <?php if (($chamado['status'] === 'Aberto') && 
                            ($_SESSION['cargo'] === 'Técnico' || $_SESSION['cargo'] === 'Administrador') &&
                            !$chamado['id_tecnico']): ?>
                        <a href="capturar_chamado.php?id=<?php echo $id_chamado; ?>" 
                           class="btn btn-capture"
                           onclick="return confirm('Tem certeza que deseja capturar este chamado?');">
                            <span class="material-symbols-outlined">person_add</span>
                            Capturar Chamado
                        </a>
                    <?php endif; ?>

                    <a href="historico_chamado.php?id=<?php echo $id_chamado; ?>" 
                       class="btn btn-history">
                        <span class="material-symbols-outlined">history</span>
                        Ver Histórico
                    </a>
                </div>
            </div>
            <div class="chamado-description">
                <?php echo htmlspecialchars($chamado['titulo']); ?>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-section">
                <h5>
                    <span class="material-symbols-outlined">person</span>
                    Informações do Chamado
                </h5>
                <p>
                    <span class="material-symbols-outlined">account_circle</span>
                    <strong>Aberto por:</strong> <?php echo htmlspecialchars($chamado['usuario_nome']); ?>
                </p>
                <p>
                    <span class="material-symbols-outlined">mail</span>
                    <strong>Email:</strong> <?php echo htmlspecialchars($chamado['usuario_email']); ?>
                </p>
                <p>
                    <span class="material-symbols-outlined">calendar_today</span>
                    <strong>Data de Abertura:</strong> <?php echo formatarData($chamado['data_abertura']); ?>
                </p>
                <?php if ($chamado['status'] === 'Fechado'): ?>
                <p>
                    <span class="material-symbols-outlined">event_available</span>
                    <strong>Data de Fechamento:</strong> <?php echo formatarData($chamado['data_fechamento']); ?>
                </p>
                <?php endif; ?>
                <p>
                    <span class="material-symbols-outlined">priority_high</span>
                    <span class="prioridade-badge prioridade-<?php echo $chamado['prioridade']; ?>">
                        <?php echo htmlspecialchars($chamado['prioridade']); ?>
                    </span>
                </p>
            </div>

            <div class="info-section">
                <h5>
                    <span class="material-symbols-outlined">support_agent</span>
                    Técnico Responsável
                </h5>
                <?php if ($chamado['id_tecnico']): ?>
                    <p>
                        <span class="material-symbols-outlined">person</span>
                        <?php echo htmlspecialchars($chamado['tecnico_nome']); ?>
                    </p>
                    <p>
                        <span class="material-symbols-outlined">mail</span>
                        <?php echo htmlspecialchars($chamado['tecnico_email']); ?>
                    </p>
                    
                    <?php if ($cargo === 'Administrador' || $id_usuario === $chamado['id_tecnico']): ?>
                        <?php if ($chamado['status'] !== 'Fechado'): ?>
                            <div class="mt-3">
                                <form action="processar_status.php" method="POST">
                                    <input type="hidden" name="id_chamado" value="<?php echo $id_chamado; ?>">
                                    <input type="hidden" name="novo_status" value="Fechado">
                                    <button type="submit" class="btn btn-success">
                                        <span class="material-symbols-outlined">task_alt</span>
                                        Encerrar Chamado
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="mt-3">
                                <span class="badge bg-secondary">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    Chamado Encerrado
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($chamado['total_resolvidos'] > 0): ?>
                        <div class="mt-3">
                            <h6>Métricas do Técnico</h6>
                            <p>
                                <span class="material-symbols-outlined">task_alt</span>
                                Chamados Resolvidos: <?php echo $chamado['total_resolvidos']; ?> de <?php echo $chamado['total_atribuidos']; ?>
                            </p>
                            <?php if ($chamado['tempo_medio_resolucao']): ?>
                                <p>
                                    <span class="material-symbols-outlined">timer</span>
                                    Tempo Médio de Resolução: <?php echo round($chamado['tempo_medio_resolucao'], 1); ?> horas
                                </p>
                            <?php endif; ?>
                            <?php if ($chamado['media_avaliacoes_tecnico'] && $id_usuario == $chamado['id_tecnico']): ?>
                                <p>
                                    <span class="material-symbols-outlined">star_rate</span>
                                    Sua Média de Avaliações: <?php echo round($chamado['media_avaliacoes_tecnico'], 1); ?>/5 (<?php echo $chamado['total_avaliacoes_tecnico']; ?> avaliações)
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted">
                        <span class="material-symbols-outlined">person_off</span>
                        Nenhum técnico atribuído
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="description-section">
            <h5>
                <span class="material-symbols-outlined">description</span>
                Descrição do Problema
            </h5>
            <div class="card">
                <div class="card-body">
                    <?php echo $chamado['descricao']; ?>
                </div>
            </div>
        </div>

        <?php if ($anexos): ?>
            <div class="attachments-section">
                <h5>
                    <span class="material-symbols-outlined">attach_file</span>
                    Anexos
                </h5>
                <div class="row">
                    <?php foreach ($anexos as $anexo): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <img src="<?php echo htmlspecialchars($anexo['imagem']); ?>" 
                                     class="card-img-top attachment-image" 
                                     alt="Anexo do chamado"
                                     data-title="Anexo do Chamado #<?php echo htmlspecialchars($chamado['id_chamado']); ?>"
                                     style="max-height: 200px; object-fit: cover;">
                                <div class="card-body">
                                    <p class="card-text text-muted">
                                        <span class="material-symbols-outlined">calendar_today</span>
                                        <?php echo formatarData($anexo['data_upload']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <hr>

    <?php if ($chamado['status'] === 'Fechado' && $chamado['id_usuario'] === $id_usuario): ?>
        <div class="reopening-request-section mb-4">
            <h4>Solicitar Reabertura</h4>
            <form action="criar_solicitacao_reabertura.php" method="POST" class="card p-3">
                <input type="hidden" name="id_chamado" value="<?php echo $id_chamado; ?>">
                <div class="mb-3">
                    <label for="justificativa" class="form-label">Justificativa para reabertura:</label>
                    <textarea name="justificativa" id="justificativa" class="form-control" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Enviar Solicitação</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if (($cargo === 'Administrador' || ($cargo === 'Técnico' && $chamado['id_tecnico'] === $id_usuario)) && !empty($solicitacoes_reabertura)): ?>
        <div class="reopening-requests-section mb-4">
            <h4>Solicitações de Reabertura</h4>
            <?php foreach ($solicitacoes_reabertura as $solicitacao): ?>
                <div class="card mb-3" id="solicitacao-<?php echo $solicitacao['id_solicitacao']; ?>">
                    <div class="card-body">
                        <h5 class="card-title">Solicitação de <?php echo htmlspecialchars($solicitacao['solicitante_nome']); ?></h5>
                        <p class="card-text"><strong>Data:</strong> <?php echo formatarData($solicitacao['data_solicitacao']); ?></p>
                        <p class="card-text"><strong>Justificativa:</strong> <?php echo htmlspecialchars($solicitacao['justificativa']); ?></p>
                        <?php if ($solicitacao['status'] === 'Pendente'): ?>
                            <form action="aceitar_solicitacao_reabertura.php" method="POST" class="d-inline" onsubmit="return handleSolicitacao(this, <?php echo $solicitacao['id_solicitacao']; ?>)">
                                <input type="hidden" name="id_solicitacao" value="<?php echo $solicitacao['id_solicitacao']; ?>">
                                <button type="submit" class="btn btn-success">Aprovar Reabertura</button>
                            </form>
                            <form action="rejeitar_solicitacao_reabertura.php" method="POST" class="d-inline" onsubmit="return handleSolicitacao(this, <?php echo $solicitacao['id_solicitacao']; ?>)">
                                <input type="hidden" name="id_solicitacao" value="<?php echo $solicitacao['id_solicitacao']; ?>">
                                <button type="submit" class="btn btn-danger ms-2">Rejeitar</button>
                            </form>
                        <?php else: ?>
                            <p class="card-text"><strong>Status:</strong> <?php echo htmlspecialchars($solicitacao['status']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="comments-section">
        <h4>
            <span class="material-symbols-outlined">forum</span>
            Comentários
        </h4>
        
        <?php if ($comentarios): ?>
            <?php foreach ($comentarios as $comentario): ?>
                <div class="comentario">
                    <div class="comentario-meta">
                        <span class="material-symbols-outlined">account_circle</span>
                        <strong><?php echo htmlspecialchars($comentario['usuario_nome']); ?></strong>
                        (<?php echo htmlspecialchars($comentario['usuario_cargo']); ?>)
                        <span class="material-symbols-outlined">schedule</span>
                        <?php echo formatarData($comentario['data_comentario']); ?>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <?php echo strip_tags($comentario['comentario']); ?>
                        </div>
                    </div>
                    
                    <?php
                    $stmt = mysqli_prepare($conn, "
                        SELECT *
                        FROM anexos
                        WHERE id_comentario = ?
                        ORDER BY data_upload ASC
                    ");
                    mysqli_stmt_bind_param($stmt, "i", $comentario['id_comentario']);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $anexos_comentario = [];
                    while ($row = mysqli_fetch_assoc($result)) {
                        $anexos_comentario[] = $row;
                    }
                    
                    if ($anexos_comentario): ?>
                        <div class="comentario-attachments mt-3">
                            <div class="row">
                                <?php foreach ($anexos_comentario as $anexo): ?>
                                    <div class="col-md-3 mb-2">
                                        <div class="card">
                                            <img src="<?php echo htmlspecialchars($anexo['imagem']); ?>" 
                                                 class="card-img-top attachment-image" 
                                                 alt="Anexo do comentário"
                                                 data-title="Anexo do Comentário - <?php echo htmlspecialchars($comentario['usuario_nome']); ?>"
                                                 style="max-height: 150px; object-fit: cover;">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">Nenhum comentário ainda.</p>
        <?php endif; ?>

        <?php if ($chamado['status'] !== 'Fechado'): ?>
            <div class="novo-comentario mt-4">
                <h5>
                    <span class="material-symbols-outlined">add_comment</span>
                    Adicionar Comentário
                </h5>
                <form action="process_comentario.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id_chamado" value="<?php echo $id_chamado; ?>">
                    <div class="mb-3">
                        <label for="comentario" class="form-label">Seu comentário:</label>
                        <textarea class="form-control" id="comentario" name="comentario" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="imagem" class="form-label">
                            <span class="material-symbols-outlined">attach_file</span>
                            Anexar imagem (opcional):
                        </label>
                        <input type="file" class="form-control" id="imagem" name="imagem" accept="image/jpeg,image/png,image/gif">
                        <div class="form-text">Formatos aceitos: JPG, PNG, GIF. Tamanho máximo: 5MB</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined">send</span>
                            Adicionar Comentário
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <span class="material-symbols-outlined">arrow_back</span>
                            Voltar
                        </a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-info mt-4">
                <span class="material-symbols-outlined">info</span>
                Este chamado está encerrado e não pode receber mais comentários.
                <a href="dashboard.php" class="btn btn-secondary ms-2">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Voltar
                </a>
                <?php if ($_SESSION['cargo'] === 'Administrador'): ?>
                    <form method="post" action="excluir_chamado.php" class="d-inline ms-2" onsubmit="return confirm('Tem certeza que deseja excluir este chamado?')">
                        <input type="hidden" name="id_chamado" value="<?php echo $id_chamado; ?>">
                        <button type="submit" class="btn btn-danger">
                            <span class="material-symbols-outlined">delete</span>
                            Excluir Chamado
                        </button>
                    </form>
                <?php endif; ?>
                <?php if (($chamado['status'] === 'Fechado') && ($_SESSION['cargo'] === 'Administrador' || ($_SESSION['cargo'] === 'Técnico' && $_SESSION['user_id'] === $chamado['id_tecnico']))): ?>
                    <form method="post" action="reabrir_chamado.php" class="d-inline ms-2" onsubmit="return confirm('Tem certeza que deseja reabrir este chamado?')">
                        <input type="hidden" name="id_chamado" value="<?php echo $id_chamado; ?>">
                        <button type="submit" class="btn btn-warning">
                            <span class="material-symbols-outlined">refresh</span>
                            Reabrir Chamado
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($chamado['status'] === 'Fechado' && $chamado['id_usuario'] === $id_usuario): ?>
                    <?php
                    // Verificar se já existe avaliação
                    $stmt = mysqli_prepare($conn, "SELECT id_avaliacao FROM avaliacoes WHERE id_chamado = ? AND id_usuario = ?");
                    mysqli_stmt_bind_param($stmt, "ii", $id_chamado, $_SESSION['user_id']);
                    mysqli_stmt_execute($stmt);
                    $avaliacao_existente = mysqli_stmt_get_result($stmt)->fetch_assoc();
                    
                    if (!$avaliacao_existente):
                    ?>
                        <a href="avaliar_chamado.php?id=<?php echo $id_chamado; ?>" class="btn btn-primary ms-2">
                            <i class="bi bi-star"></i> Avaliar Chamado
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para visualização de imagens -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Visualização da Imagem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <img src="" class="modal-image" id="modalImage" alt="Imagem em tamanho grande">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/lang/summernote-pt-BR.min.js"></script>
    
<script>
$(document).ready(function() {
    // Inicializa o Summernote para o campo de comentário
    $('#comentario').summernote({
        lang: 'pt-BR',
        height: 200,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'underline', 'clear']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ],
        placeholder: 'Digite seu comentário aqui...'
    });
});
</script>

<script>
// Função para abrir o modal com a imagem
function openImageModal(imageSrc, title) {
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const modalTitle = document.getElementById('imageModalLabel');
    
    modalImage.src = imageSrc;
    modalTitle.textContent = title;
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

// Adiciona evento de clique em todas as imagens de anexo
document.addEventListener('DOMContentLoaded', function() {
    const attachmentImages = document.querySelectorAll('.attachment-image');
    attachmentImages.forEach(img => {
        img.addEventListener('click', function() {
            openImageModal(this.src, this.getAttribute('data-title') || 'Visualização da Imagem');
        });
    });
});
</script>

<script>
function handleSolicitacao(form, idSolicitacao) {
    const formData = new FormData(form);
    
    // Adicionar cabeçalho para identificar requisição AJAX
    const headers = new Headers();
    headers.append('X-Requested-With', 'XMLHttpRequest');

    // Enviar o formulário via AJAX
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: headers
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remover o card da solicitação
            const card = document.getElementById('solicitacao-' + idSolicitacao);
            if (card) {
                card.remove();
                
                // Se não houver mais solicitações, remover a seção inteira
                const solicitacoesContainer = document.querySelector('.reopening-requests-section');
                if (solicitacoesContainer && !solicitacoesContainer.querySelector('.card')) {
                    solicitacoesContainer.remove();
                }
            }
            
            // Redirecionar para atualizar o status do chamado se necessário
            if (form.action.includes('aceitar_solicitacao_reabertura.php')) {
                window.location.reload();
            }
        } else {
            alert(data.message || 'Erro ao processar solicitação');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar solicitação. Por favor, tente novamente.');
    });
    
    // Impedir o envio normal do formulário
    return false;
}
</script>
</body>
</html>
