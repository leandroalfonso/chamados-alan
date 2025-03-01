<?php
session_start();
require_once 'db_connection.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$id_chamado = $_GET['id'];
$id_usuario = $_SESSION['user_id'];

// Verificar se o chamado está fechado e pertence ao usuário
$stmt = mysqli_prepare($conn, "
    SELECT c.*, u.nome as tecnico_nome 
    FROM chamados c
    LEFT JOIN usuarios u ON c.id_tecnico = u.id_usuario
    WHERE c.id_chamado = ? AND c.id_usuario = ? AND c.status = 'Fechado'
");
mysqli_stmt_bind_param($stmt, "ii", $id_chamado, $id_usuario);
mysqli_stmt_execute($stmt);
$chamado = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$chamado) {
    $_SESSION['error_message'] = "Chamado não encontrado ou você não tem permissão para avaliá-lo.";
    header('Location: dashboard.php');
    exit();
}


$stmt = mysqli_prepare($conn, "SELECT id_avaliacao FROM avaliacoes WHERE id_chamado = ? AND id_usuario = ?");
mysqli_stmt_bind_param($stmt, "ii", $id_chamado, $id_usuario);
mysqli_stmt_execute($stmt);
$avaliacao_existente = mysqli_stmt_get_result($stmt)->fetch_assoc();

if ($avaliacao_existente) {
    $_SESSION['error_message'] = "Você já avaliou este chamado.";
    header('Location: ver_chamado.php?id=' . $id_chamado);
    exit();
}


try {
    $result = mysqli_query($conn, "SELECT 1 FROM avaliacoes LIMIT 1");
    if (!$result) {
        throw new Exception("Tabela de avaliações não encontrada");
    }
} catch (Exception $e) {
    die("Erro no sistema: ".$e->getMessage());
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nota = filter_input(INPUT_POST, 'nota', FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 5
            ]
        ]);

        if (!$nota) {
            throw new Exception("Nota inválida");
        }

        $comentario = trim(strip_tags($_POST['comentario']));
        $sugestoes = trim(strip_tags($_POST['sugestoes']));

        $stmt = mysqli_prepare($conn, "INSERT INTO avaliacoes 
            (id_chamado, id_usuario, nota, comentario, data_avaliacao) 
            VALUES (?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "iiis", $id_chamado, $id_usuario, $nota, $comentario);
        
        if (mysqli_stmt_execute($stmt)) {
            // Atualizar métricas do técnico
            if ($chamado['id_tecnico']) {
                $stmt = mysqli_prepare($conn, "
                    UPDATE metricas_tecnicos 
                    SET satisfacao_media = (
                        SELECT AVG(nota) 
                        FROM avaliacoes a 
                        JOIN chamados c ON a.id_chamado = c.id_chamado 
                        WHERE c.id_tecnico = ?
                    )
                    WHERE id_tecnico = ?
                ");
                mysqli_stmt_bind_param($stmt, "ii", $chamado['id_tecnico'], $chamado['id_tecnico']);
                mysqli_stmt_execute($stmt);
            }

            $_SESSION['success_message'] = "Avaliação enviada com sucesso!";
            header('Location: ver_chamado.php?id=' . $id_chamado);
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erro ao enviar avaliação: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliar Chamado - Sistema de Chamados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .star-rating {
            font-size: 2em;
            cursor: pointer;
        }
        .star-rating .bi-star-fill {
            color: #ffc107;
        }
        .star-rating .bi-star {
            color: #dee2e6;
        }
        .rating-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .rating-label {
            font-size: 1.2em;
            color: #6c757d;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h2>Avaliar Chamado #<?php echo $id_chamado; ?></h2>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h5>Detalhes do Chamado</h5>
                    <p><strong>Título:</strong> <?php echo htmlspecialchars($chamado['titulo']); ?></p>
                    <p><strong>Técnico:</strong> <?php echo htmlspecialchars($chamado['tecnico_nome']); ?></p>
                </div>

                <form method="post" id="avaliacaoForm">
                    <div class="mb-4">
                        <label class="form-label">Sua Avaliação</label>
                        <div class="rating-container mb-2">
                            <div class="star-rating" id="starRating">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star" data-rating="<?php echo $i; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="rating-label" id="ratingLabel">Selecione uma nota</span>
                        </div>
                        <input type="hidden" name="nota" id="nota" required>
                    </div>

                    <div class="mb-3">
                        <label for="comentario" class="form-label">Comentários sobre o Atendimento</label>
                        <textarea class="form-control" id="comentario" name="comentario" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="sugestoes" class="form-label">Sugestões de Melhoria</label>
                        <textarea class="form-control" id="sugestoes" name="sugestoes" rows="3"></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Enviar Avaliação</button>
                        <a href="ver_chamado.php?id=<?php echo $id_chamado; ?>" class="btn btn-secondary">Voltar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const starRating = document.getElementById('starRating');
            const ratingLabel = document.getElementById('ratingLabel');
            const notaInput = document.getElementById('nota');
            const stars = starRating.getElementsByTagName('i');
            const labels = ['Muito Insatisfeito', 'Insatisfeito', 'Regular', 'Satisfeito', 'Muito Satisfeito'];

            function updateStars(rating) {
                for (let i = 0; i < stars.length; i++) {
                    stars[i].className = i < rating ? 'bi bi-star-fill' : 'bi bi-star';
                }
                notaInput.value = rating;
                ratingLabel.textContent = labels[rating - 1];
            }

            starRating.addEventListener('click', function(e) {
                if (e.target.tagName === 'I') {
                    const rating = parseInt(e.target.getAttribute('data-rating'));
                    updateStars(rating);
                }
            });

            starRating.addEventListener('mouseover', function(e) {
                if (e.target.tagName === 'I') {
                    const rating = parseInt(e.target.getAttribute('data-rating'));
                    for (let i = 0; i < stars.length; i++) {
                        stars[i].className = i < rating ? 'bi bi-star-fill' : 'bi bi-star';
                    }
                }
            });

            starRating.addEventListener('mouseout', function() {
                const currentRating = parseInt(notaInput.value) || 0;
                for (let i = 0; i < stars.length; i++) {
                    stars[i].className = i < currentRating ? 'bi bi-star-fill' : 'bi bi-star';
                }
            });

            document.getElementById('avaliacaoForm').addEventListener('submit', function(e) {
                if (!notaInput.value) {
                    e.preventDefault();
                    alert('Por favor, selecione uma nota para o atendimento.');
                }
            });
        });
    </script>
</body>
</html>
