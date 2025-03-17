<?php
require_once 'db_connection.php';

class PriorizacaoAutomatica {
    private $conn;
    private $palavrasChave;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->carregarPalavrasChave();
    }

    private function carregarPalavrasChave() {
        $stmt = mysqli_prepare($this->conn, "SELECT palavra, peso FROM palavras_chave_prioridade");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $this->palavrasChave = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $this->palavrasChave[$row['palavra']] = $row['peso'];
        }
    }

    public function calcularPrioridade($idChamado) {
        $pontuacao = 0;
        
        // Buscar informações do chamado
        $stmt = mysqli_prepare($this->conn, "
            SELECT c.*, u.cargo, 
                   (SELECT COUNT(*) FROM comentarios WHERE id_chamado = c.id_chamado) as total_interacoes,
                   (SELECT COUNT(*) FROM solicitacoes_reabertura WHERE id_chamado = c.id_chamado) as total_reaberturas
            FROM chamados c
            JOIN usuarios u ON c.id_usuario = u.id_usuario
            WHERE c.id_chamado = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $idChamado);
        mysqli_stmt_execute($stmt);
        $chamado = mysqli_stmt_get_result($stmt)->fetch_assoc();

        // 1. Pontuação por tempo de espera
        $tempoEspera = time() - strtotime($chamado['data_abertura']);
        $horasEspera = $tempoEspera / 3600;
        $pontuacao += min(5, floor($horasEspera / 24)); // Máximo de 5 pontos por tempo de espera

        // 2. Pontuação por palavras-chave
        $descricao = strtolower($chamado['descricao'] . ' ' . $chamado['titulo']);
        foreach ($this->palavrasChave as $palavra => $peso) {
            if (strpos($descricao, $palavra) !== false) {
                $pontuacao += $peso;
            }
        }

        // 3. Pontuação por cargo do solicitante
        switch ($chamado['cargo']) {
            case 'Administrador':
                $pontuacao += 3;
                break;
            case 'Gerente':
                $pontuacao += 2;
                break;
        }

        // 4. Pontuação por interações
        $pontuacao += min(3, floor($chamado['total_interacoes'] / 5));

        // 5. Pontuação por reaberturas
        $pontuacao += ($chamado['total_reaberturas'] * 2);

        // Normalizar pontuação para escala 1-5
        $prioridadeFinal = max(1, min(5, ceil($pontuacao / 5)));

        // Atualizar prioridade no banco de dados
        if ($prioridadeFinal != $chamado['prioridade_automatica']) {
            $stmt = mysqli_prepare($this->conn, "
                UPDATE chamados 
                SET prioridade_automatica = ?,
                    ultima_atualizacao_prioridade = NOW()
                WHERE id_chamado = ?
            ");
            mysqli_stmt_bind_param($stmt, "ii", $prioridadeFinal, $idChamado);
            mysqli_stmt_execute($stmt);

            // Registrar histórico
            $stmt = mysqli_prepare($this->conn, "
                INSERT INTO historico_priorizacao 
                (id_chamado, prioridade_anterior, nova_prioridade, motivo)
                VALUES (?, ?, ?, ?)
            ");
            $motivo = "Atualização automática baseada em: Tempo de espera, palavras-chave, cargo e interações";
            mysqli_stmt_bind_param($stmt, "iiis", $idChamado, $chamado['prioridade_automatica'], $prioridadeFinal, $motivo);
            mysqli_stmt_execute($stmt);
        }

        return $prioridadeFinal;
    }

    public function atualizarTodasPrioridades() {
        $stmt = mysqli_prepare($this->conn, "SELECT id_chamado FROM chamados WHERE status != 'Fechado'");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $this->calcularPrioridade($row['id_chamado']);
        }
    }
}
