<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT nome, cargo FROM usuarios WHERE id_usuario = ?");
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$usuario = mysqli_fetch_assoc($result);

// Parâmetros de filtro
$filtro_usuario = isset($_GET['filtro_usuario']) ? $_GET['filtro_usuario'] : 'todos';
$filtro_status = isset($_GET['filtro_status']) ? $_GET['filtro_status'] : 'todos';
$filtro_prioridade = isset($_GET['filtro_prioridade']) ? $_GET['filtro_prioridade'] : 'todos';
$filtro_usuario_especifico = isset($_GET['filtro_usuario_especifico']) ? $_GET['filtro_usuario_especifico'] : 'todos';
$filtro_tecnico_especifico = isset($_GET['filtro_tecnico_especifico']) ? $_GET['filtro_tecnico_especifico'] : 'todos';
$ordem = isset($_GET['ordem']) ? $_GET['ordem'] : 'data_abertura DESC';


if ($usuario['cargo'] === 'Técnico') {
    $filtro_usuario = 'meus'; 
}

// Buscar lista de usuários para o filtro (apenas para administradores)
$usuarios_lista = [];
$tecnicos_lista = [];
if ($usuario['cargo'] === 'Administrador') {
    // Buscar todos os usuários e técnicos
    $stmt = mysqli_prepare($conn, "SELECT id_usuario, nome, cargo FROM usuarios ORDER BY nome");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $usuarios_lista[] = $row;
        if ($row['cargo'] === 'Técnico') {
            $tecnicos_lista[] = $row;
        }
    }
} elseif ($usuario['cargo'] === 'Técnico') {
    // Para técnicos, buscar apenas usuários que têm chamados atribuídos a ele
    $stmt = mysqli_prepare($conn, "
        SELECT DISTINCT u.id_usuario, u.nome, u.cargo 
        FROM usuarios u 
        INNER JOIN chamados c ON u.id_usuario = c.id_usuario 
        WHERE c.id_tecnico = ? 
        ORDER BY u.nome");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $usuarios_lista[] = $row;
    }
}

// Consultas específicas para técnicos
if ($usuario['cargo'] === 'Técnico') {
    // Obter estatísticas do técnico
    $id_tecnico = $_SESSION['user_id'];
    
    // Chamados atribuídos ao técnico
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM chamados WHERE id_tecnico = ? AND status = 'Aberto'");
    mysqli_stmt_bind_param($stmt, "i", $id_tecnico);
    mysqli_stmt_execute($stmt);
    $chamados_atribuidos = mysqli_stmt_get_result($stmt)->fetch_assoc()['total'];
    
    // Chamados resolvidos pelo técnico
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM chamados WHERE id_tecnico = ? AND status = 'Fechado'");
    mysqli_stmt_bind_param($stmt, "i", $id_tecnico);
    mysqli_stmt_execute($stmt);
    $chamados_resolvidos = mysqli_stmt_get_result($stmt)->fetch_assoc()['total'];
    
    // Tempo médio de resolução
    $stmt = mysqli_prepare($conn, "
        SELECT AVG(TIMESTAMPDIFF(HOUR, data_captura, data_fechamento)) as media 
        FROM chamados 
        WHERE id_tecnico = ? AND status = 'Fechado'
    ");
    mysqli_stmt_bind_param($stmt, "i", $id_tecnico);
    mysqli_stmt_execute($stmt);
    $tempo_medio = mysqli_stmt_get_result($stmt)->fetch_assoc()['media'];
    
    // Média de avaliações recebidas
    $stmt = mysqli_prepare($conn, "
        SELECT AVG(a.nota) as media_avaliacoes, COUNT(*) as total_avaliacoes 
        FROM avaliacoes a 
        JOIN chamados c ON a.id_chamado = c.id_chamado 
        WHERE c.id_tecnico = ?
    ");
    mysqli_stmt_bind_param($stmt, "i", $id_tecnico);
    mysqli_stmt_execute($stmt);
    $metricas_avaliacoes = mysqli_stmt_get_result($stmt)->fetch_assoc();
}

// Construir a consulta SQL base
$sql = "SELECT 
            c.*,
            u1.nome as usuario_nome,
            u2.nome as tecnico_nome,
            (SELECT COUNT(*) FROM solicitacoes_reabertura sr WHERE sr.id_chamado = c.id_chamado AND sr.status = 'Pendente') as tem_solicitacao_reabertura,
            (SELECT sr.status FROM solicitacoes_reabertura sr WHERE sr.id_chamado = c.id_chamado ORDER BY sr.data_solicitacao DESC LIMIT 1) as ultimo_status_solicitacao,
            (SELECT u.nome FROM solicitacoes_reabertura sr 
             INNER JOIN usuarios u ON sr.id_responsavel = u.id_usuario 
             WHERE sr.id_chamado = c.id_chamado 
             ORDER BY sr.data_solicitacao DESC LIMIT 1) as responsavel_ultima_solicitacao,
            (SELECT sr.data_resposta FROM solicitacoes_reabertura sr 
             WHERE sr.id_chamado = c.id_chamado 
             ORDER BY sr.data_solicitacao DESC LIMIT 1) as data_ultima_resposta
        FROM chamados c
        LEFT JOIN usuarios u1 ON c.id_usuario = u1.id_usuario
        LEFT JOIN usuarios u2 ON c.id_tecnico = u2.id_usuario
        WHERE 1=1";

$params = [];
$types = "";


if ($usuario['cargo'] === 'Administrador') {
  
} elseif ($usuario['cargo'] === 'Técnico') {
    
    if ($filtro_usuario_especifico !== 'todos') {
        // Se um usuário específico foi selecionado, mostrar apenas chamados desse usuário atribuídos ao técnico
        $sql .= " AND c.id_tecnico = ? AND c.id_usuario = ?";
        $params[] = $_SESSION['user_id'];
        $params[] = $filtro_usuario_especifico;
        $types .= "ii";
    } else {
        // Caso contrário, mostrar todos os chamados atribuídos ao técnico e chamados em aberto
        $sql .= " AND (c.id_tecnico = ? OR (c.id_tecnico IS NULL AND c.status = 'Aberto'))";
        $params[] = $_SESSION['user_id'];
        $types .= "i";
    }
} else {
    // Usuário comum vê apenas seus próprios chamados
    $sql .= " AND c.id_usuario = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}

// Aplicar filtros adicionais
if ($filtro_usuario === 'meus') {
    if ($usuario['cargo'] === 'Técnico') {
      
    } else {
        $sql .= " AND c.id_usuario = ?";
        $params[] = $_SESSION['user_id'];
        $types .= "i";
    }
}

if ($filtro_usuario_especifico !== 'todos' && $filtro_usuario_especifico !== '') {
    $sql .= " AND c.id_usuario = ?";
    $params[] = $filtro_usuario_especifico;
    $types .= "i";
}

if ($filtro_tecnico_especifico !== 'todos' && $filtro_tecnico_especifico !== '') {
    $sql .= " AND c.id_tecnico = ?";
    $params[] = $filtro_tecnico_especifico;
    $types .= "i";
}

if ($filtro_status !== 'todos') {
    $sql .= " AND c.status = ?";
    $params[] = $filtro_status;
    $types .= "s";
}

if ($filtro_prioridade !== 'todos') {
    $sql .= " AND c.prioridade = ?";
    $params[] = $filtro_prioridade;
    $types .= "s";
}

// Adicionar ordenação
$sql .= " ORDER BY " . $ordem;

// Executar a consulta
$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$chamados = [];
while ($row = mysqli_fetch_assoc($result)) {
    $chamados[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Chamados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1400px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .header h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 16px;
            color: #666;
        }
        .header-actions {
            display: flex;
            gap: 10px;
        }
        .filtros-container {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: start;
        }
        .filtro-grupo {
            background: white;
            padding: 10px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .filtro-grupo h5 {
            font-size: 0.9rem;
            margin-bottom: 8px;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .filtro-grupo .material-symbols-outlined {
            font-size: 18px;
            vertical-align: text-bottom;
            color: #6c757d;
        }
        .filtro-grupo select {
            width: 100%;
            padding: 8px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background-color: white;
        }
        .filtro-grupo select:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .filtro-visualizacao {
            display: flex;
            gap: 8px;
        }
        .filtro-visualizacao .btn {
            flex: 1;
            text-align: center;
            padding: 8px;
            border-radius: 4px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background-color: white;
            border: 1px solid #dee2e6;
            color: #495057;
        }
        .filtro-visualizacao .btn:hover {
            background-color: #e9ecef;
        }
        .filtro-visualizacao .btn.active {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        .person-search-icon {
            color: #0d6efd !important;
        }
        .tech-search-icon {
            color: #198754 !important;
        }
        .form-select {
            font-size: 0.9rem;
            padding: 6px 12px;
        }
        .btn-group-vertical {
            width: 100%;
        }
        .btn-group-vertical .btn {
            text-align: left;
            padding: 6px 12px;
            font-size: 0.9rem;
        }
        .filtros-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: flex-end;
        }
        .filtros-actions button {
            font-size: 0.9rem;
            padding: 6px 12px;
        }
        .chamados-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .badge {
            font-size: 0.85em;
            padding: 0.4em 0.8em;
           
        }
        .btn-group-vertical .btn {
            text-align: left;
        }
        .status-badge {
            width: auto;
            text-align: center;
        }
        .table-responsive {
            height: 500px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            margin-top: 20px;
        }
        .table {
            margin-bottom: 0;
        }
        .table thead {
            position: sticky;
            top: 0;
            background-color: white;
            z-index: 1;
        }
        .table thead th {
            border-top: none;
            background-color: #f8f9fa;
            box-shadow: inset 0 1px 0 #dee2e6, inset 0 -1px 0 #dee2e6;
        }
        .table tbody tr:hover {
            background-color: rgba(0,0,0,.075);
        }
        .chamado-row-com-solicitacao {
            background-color: #fff3cd !important;
        }
        .chamado-row-com-solicitacao:hover {
            background-color: #ffe7b3 !important;
        }
        .chamado-row-rejeitado {
            background-color: #ffe6e6 !important;
        }
        .chamado-row-rejeitado:hover {
            background-color: #ffcccc !important;
        }
        .chamado-row-aprovado {
            background-color: #e6ffe6 !important;
        }
        .chamado-row-aprovado:hover {
            background-color: #ccffcc !important;
        }
        .badge-solicitacao {
            font-size: 0.8em;
            padding: 0.3em 0.6em;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-solicitacao.pendente {
            background-color: #ffc107;
            color: #000;
        }
        .badge-solicitacao.aprovada {
            background-color: #28a745;
            color: white;
        }
        .badge-solicitacao.rejeitada {
            background-color: #dc3545;
            color: white;
        }
        .badge-solicitacao .material-symbols-outlined {
            font-size: 1em;
        }
        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 10px;
            font-size: 0.85em;
        }
        .status-aberto { background-color: #28a745; color: white; }
        .status-em-andamento { background-color: #007bff; color: white; }
        .status-fechado { background-color: #6c757d; color: white; }
        .metric-box {
            background-color: white;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .metric-box h6 {
            margin-bottom: 10px;
            font-size: 1rem;
            color: #495057;
        }
        .metric-value {
            font-size: 2rem;
            font-weight: 600;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1>Bem-vindo, <?php echo htmlspecialchars($usuario['nome']); ?></h1>
                <p class="text-muted">Cargo: <?php echo htmlspecialchars($usuario['cargo']); ?></p>
            </div>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="header-actions">
                <?php if ($usuario['cargo'] === 'Administrador'): ?>
                <a href="admin/dashboard_metricas.php" class="btn btn-primary">
                    <span class="material-symbols-outlined">monitoring</span>
                    Dashboard de Métricas
                </a>
                <a href="admin/relatorio_avaliacoes.php" class="btn btn-primary">
                    <span class="material-symbols-outlined">star</span>
                    Relatório de Avaliações
                </a>
                <a href="admin_usuarios.php" class="btn btn-info">
                    <span class="material-symbols-outlined">admin_panel_settings</span>
                    Painel de Administração
                </a>
                <?php endif; ?>
                <a href="novo_chamado.php" class="btn btn-success">
                    <span class="material-symbols-outlined">add</span>
                    Novo Chamado
                </a>
                <a href="logout.php" class="btn btn-danger">
                    <span class="material-symbols-outlined">logout</span>
                    Sair
                </a>
            </div>
        </div>
        
        <?php if ($usuario['cargo'] === 'Técnico'): ?>
        <div class="card mb-4 mt-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Suas Métricas</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="metric-box">
                            <h6>Chamados Atribuídos</h6>
                            <div class="metric-value"><?php echo $chamados_atribuidos ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-box">
                            <h6>Chamados Resolvidos</h6>
                            <div class="metric-value"><?php echo $chamados_resolvidos ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-box">
                            <h6>Tempo Médio</h6>
                            <div class="metric-value"><?php echo isset($tempo_medio) && $tempo_medio ? round($tempo_medio, 1) . 'h' : 'N/A'; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-box">
                            <h6>Avaliação Média</h6>
                            <div class="metric-value">
                                <?php if (isset($metricas_avaliacoes) && $metricas_avaliacoes['total_avaliacoes'] > 0): ?>
                                    <div class="d-flex align-items-center">
                                        <span class="material-symbols-outlined" style="color: gold; margin-right: 5px;">star</span>
                                        <?php echo round($metricas_avaliacoes['media_avaliacoes'], 1); ?>/5
                                        <small class="text-muted ms-2">(<?php echo $metricas_avaliacoes['total_avaliacoes']; ?>)</small>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Sem avaliações</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="filtros-container">
            <form id="filtrosForm" method="GET" class="filtros-grid">
                <?php if ($usuario['cargo'] !== 'Usuário'): ?>
                <div class="filtro-grupo">
                    <h5>
                        <span class="material-symbols-outlined">visibility</span>
                        Visualizar filtros de:
                    </h5>
                    <div class="filtro-visualizacao">
                        <button type="button" class="btn <?php echo $filtro_usuario === 'todos' ? 'active' : ''; ?>" onclick="document.getElementById('todos').click();">
                            <span class="material-symbols-outlined">group</span>
                            Todos
                        </button>
                        <button type="button" class="btn <?php echo $filtro_usuario === 'meus' ? 'active' : ''; ?>" onclick="document.getElementById('meus').click();">
                            <span class="material-symbols-outlined">person</span>
                            Meus
                        </button>
                    </div>
                    <div style="display: none;">
                        <input type="radio" class="btn-check" name="filtro_usuario" id="todos" value="todos" <?php echo $filtro_usuario === 'todos' ? 'checked' : ''; ?> onchange="this.form.submit()">
                        <input type="radio" class="btn-check" name="filtro_usuario" id="meus" value="meus" <?php echo $filtro_usuario === 'meus' ? 'checked' : ''; ?> onchange="this.form.submit()">
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($usuario['cargo'] === 'Administrador' || ($usuario['cargo'] === 'Técnico' && !empty($usuarios_lista))): ?>
                <div class="filtro-grupo">
                    <h5>
                        <span class="material-symbols-outlined person-search-icon">person_search</span>
                        <?php if ($usuario['cargo'] === 'Administrador'): ?>
                            Usuário Específico
                        <?php else: ?>
                            Chamados por Usuário
                        <?php endif; ?>
                    </h5>
                    <select name="filtro_usuario_especifico" class="form-select" onchange="this.form.submit()">
                        <option value="todos">
                            <?php if ($usuario['cargo'] === 'Administrador'): ?>
                                Todos os usuários
                            <?php else: ?>
                                Todos os meus chamados
                            <?php endif; ?>
                        </option>
                        <?php foreach ($usuarios_lista as $usuario_lista): ?>
                            <option value="<?php echo $usuario_lista['id_usuario']; ?>" <?php echo $filtro_usuario_especifico == $usuario_lista['id_usuario'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($usuario_lista['nome']); ?> 
                                (<?php echo htmlspecialchars($usuario_lista['cargo']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($usuario['cargo'] === 'Administrador'): ?>
                <div class="filtro-grupo">
                    <h5>
                        <span class="material-symbols-outlined tech-search-icon">support_agent</span>
                        Técnico Específico
                    </h5>
                    <select name="filtro_tecnico_especifico" class="form-select" onchange="this.form.submit()">
                        <option value="todos">Todos os técnicos</option>
                        <?php foreach ($tecnicos_lista as $tecnico_lista): ?>
                            <option value="<?php echo $tecnico_lista['id_usuario']; ?>" <?php echo $filtro_tecnico_especifico == $tecnico_lista['id_usuario'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tecnico_lista['nome']); ?> 
                                (<?php echo htmlspecialchars($tecnico_lista['cargo']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="filtro-grupo">
                    <h5>
                        <span class="material-symbols-outlined">event</span>
                        Status
                    </h5>
                    <select name="filtro_status" class="form-select" onchange="this.form.submit()">
                        <option value="todos" <?php echo $filtro_status === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="Aberto" <?php echo $filtro_status === 'Aberto' ? 'selected' : ''; ?>>Aberto</option>
                        <option value="Em andamento" <?php echo $filtro_status === 'Em andamento' ? 'selected' : ''; ?>>Em andamento</option>
                        <option value="Fechado" <?php echo $filtro_status === 'Fechado' ? 'selected' : ''; ?>>Fechado</option>
                    </select>
                </div>

                <div class="filtro-grupo">
                    <h5>
                        <span class="material-symbols-outlined">flag</span>
                        Prioridade
                    </h5>
                    <select name="filtro_prioridade" class="form-select" onchange="this.form.submit()">
                        <option value="todos" <?php echo $filtro_prioridade === 'todos' ? 'selected' : ''; ?>>Todas</option>
                        <option value="Alta" <?php echo $filtro_prioridade === 'Alta' ? 'selected' : ''; ?>>Alta</option>
                        <option value="Média" <?php echo $filtro_prioridade === 'Média' ? 'selected' : ''; ?>>Média</option>
                        <option value="Baixa" <?php echo $filtro_prioridade === 'Baixa' ? 'selected' : ''; ?>>Baixa</option>
                    </select>
                </div>

                <div class="filtro-grupo">
                    <h5>
                        <span class="material-symbols-outlined">sort</span>
                        Ordenação
                    </h5>
                    <select name="ordem" class="form-select" onchange="this.form.submit()">
                        <option value="data_abertura DESC" <?php echo $ordem === 'data_abertura DESC' ? 'selected' : ''; ?>>Mais recentes</option>
                        <option value="data_abertura ASC" <?php echo $ordem === 'data_abertura ASC' ? 'selected' : ''; ?>>Mais antigos</option>
                        <option value="prioridade DESC" <?php echo $ordem === 'prioridade DESC' ? 'selected' : ''; ?>>Maior prioridade</option>
                        <option value="prioridade ASC" <?php echo $ordem === 'prioridade ASC' ? 'selected' : ''; ?>>Menor prioridade</option>
                    </select>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover chamados-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th style="min-width: 250px;">Título</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                            <th>Usuário</th>
                            <th>Técnico</th>
                            <th>Data Abertura</th>
                            <th>Ver</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($chamados)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px; color: #6c757d;">search_off</span>
                                <p class="mb-0">Nenhum chamado encontrado com os filtros selecionados.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($chamados as $chamado): 
                                $row_class = '';
                                $badge_class = '';
                                $badge_icon = '';
                                $badge_text = '';
                                
                                if ($chamado['tem_solicitacao_reabertura'] > 0) {
                                    $row_class = 'chamado-row-com-solicitacao';
                                    $badge_class = 'pendente';
                                    $badge_icon = 'refresh';
                                    $badge_text = 'Solicitação de Reabertura Pendente';
                                } elseif ($chamado['ultimo_status_solicitacao'] === 'Rejeitada') {
                                    $row_class = 'chamado-row-rejeitado';
                                    $badge_class = 'rejeitada';
                                    $badge_icon = 'block';
                                    $badge_text = 'Reabertura Rejeitada por ' . $chamado['responsavel_ultima_solicitacao'] . 
                                                ' em ' . date('d/m/Y', strtotime($chamado['data_ultima_resposta']));
                                } elseif ($chamado['ultimo_status_solicitacao'] === 'Aprovada') {
                                    $row_class = 'chamado-row-aprovado';
                                    $badge_class = 'aprovada';
                                    $badge_icon = 'check_circle';
                                    $badge_text = 'Reabertura Aprovada por ' . $chamado['responsavel_ultima_solicitacao'] . 
                                                ' em ' . date('d/m/Y', strtotime($chamado['data_ultima_resposta']));
                                }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td><?php echo htmlspecialchars($chamado['id_chamado']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($chamado['titulo']); ?>
                                    <?php if ($badge_text): ?>
                                        <span class="badge-solicitacao <?php echo $badge_class; ?>" title="<?php echo $badge_text; ?>">
                                            <span class="material-symbols-outlined"><?php echo $badge_icon; ?></span>
                                            <?php echo $badge_text; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $chamado['status'])); ?>">
                                        <?php echo htmlspecialchars($chamado['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo $chamado['prioridade'] === 'Alta' ? 'danger' : 
                                            ($chamado['prioridade'] === 'Média' ? 'warning' : 'success');
                                    ?>">
                                        <?php echo htmlspecialchars($chamado['prioridade']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($chamado['usuario_nome']); ?></td>
                                <td><?php echo $chamado['tecnico_nome'] ? htmlspecialchars($chamado['tecnico_nome']) : '-'; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($chamado['data_abertura'])); ?></td>
                                <td>
                                    <a href="ver_chamado.php?id=<?php echo $chamado['id_chamado']; ?>" class="btn btn-sm btn-primary">
                                        <span class="material-symbols-outlined">visibility</span>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="js/chart.min.js"></script>
        <script src="js/dashboard.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Inicializar dashboard
                
                // Auto-submit do formulário quando mudar qualquer filtro
                document.querySelectorAll('#filtrosForm input[type="radio"], #filtrosForm select').forEach(input => {
                    input.addEventListener('change', () => document.getElementById('filtrosForm').submit());
                });
            });
        </script>
    </body>
</html>
