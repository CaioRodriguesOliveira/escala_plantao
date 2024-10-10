<?php
session_start();

// Lista inicial de funcionários
if (!isset($_SESSION['funcionarios'])) {
    $_SESSION['funcionarios'] = [

    ];
}

$funcionarios = $_SESSION['funcionarios'];

// Gerenciar feriados selecionados
if (!isset($_SESSION['feriados'])) {
    $_SESSION['feriados'] = [];
}
$feriados = $_SESSION['feriados'];

// Funções para geração da escala
function getDomingosFeriadosMes($feriados) {
    $datas = [];
    $mes = date('m');
    $ano = date('Y');

    // Adiciona os domingos
    for ($i = 1; $i <= 31; $i++) {
        $data = "$ano-$mes-$i";
        if (date('N', strtotime($data)) == 7) {
            $datas[] = $data;
        }
    }

    // Adiciona os feriados
    foreach ($feriados as $feriado) {
        if (date('m', strtotime($feriado)) == $mes) {
            $datas[] = $feriado;
        }
    }

    return array_unique($datas);
}

function gerarEscala($funcionarios, $turnos, &$historico_plantao, $data, &$funcionarios_ja_escalados, &$contador_turnos) {
    $escala = [];
    shuffle($funcionarios); // Embaralha a lista de funcionários

    // Ordena funcionários por quantidade de turnos escalados
    usort($funcionarios, function($a, $b) use ($contador_turnos) {
        return $contador_turnos[$a] <=> $contador_turnos[$b];
    });

    foreach ($turnos as $turno) {
        $dupla = [];
        foreach ($funcionarios as $funcionario) {
            if (!in_array($funcionario, $funcionarios_ja_escalados) && $contador_turnos[$funcionario] < 3) {
                $dupla[] = $funcionario;
                $funcionarios_ja_escalados[] = $funcionario;
                $contador_turnos[$funcionario]++;
            }
            if (count($dupla) === 2) {
                break; // Completa a dupla
            }
        }

        // Se a dupla não for completada, tenta novamente com outros funcionários
        if (count($dupla) < 3) {
            foreach ($funcionarios as $funcionario) {
                if (!in_array($funcionario, $funcionarios_ja_escalados) && $contador_turnos[$funcionario] < 3) {
                    $dupla[] = $funcionario;
                    $funcionarios_ja_escalados[] = $funcionario;
                    $contador_turnos[$funcionario]++;
                }
                if (count($dupla) === 2) {
                    break; // Completa a dupla
                }
            }
        }

        $escala[$turno] = $dupla;
    }

    return $escala;
}


// Adicionar ou remover funcionários e feriados
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adicionar novo funcionário
    if (!empty($_POST['novo_funcionario'])) {
        $novo_funcionario = htmlspecialchars($_POST['novo_funcionario']);
        if (!in_array($novo_funcionario, $funcionarios)) {
            $funcionarios[] = $novo_funcionario;
            $_SESSION['funcionarios'] = $funcionarios;
        }
    }
    // Remover funcionário
    if (!empty($_POST['remover_funcionario'])) {
        $remover_funcionario = $_POST['remover_funcionario'];
        if (($key = array_search($remover_funcionario, $funcionarios)) !== false) {
            unset($funcionarios[$key]);
            $_SESSION['funcionarios'] = array_values($funcionarios);
        }
    }
    // Adicionar feriado
    if (!empty($_POST['feriado'])) {
        $feriado = $_POST['feriado'];
        if (!in_array($feriado, $feriados)) {
            $feriados[] = $feriado;
            $_SESSION['feriados'] = $feriados;
        }
    }
    // Remover feriado
    if (!empty($_POST['remover_feriado'])) {
        $remover_feriado = $_POST['remover_feriado'];
        if (($key = array_search($remover_feriado, $feriados)) !== false) {
            unset($feriados[$key]);
            $_SESSION['feriados'] = array_values($feriados);
        }
    }
    // Remover funcionário em data específica
    if (!empty($_POST['remover_funcionario_data']) && !empty($_POST['data_especifica'])) {
        $funcionario_remover = $_POST['remover_funcionario_data'];
        $data_especifica = $_POST['data_especifica'];

        if (!empty($escala_completa[$data_especifica])) {
            foreach ($escala_completa[$data_especifica] as $turno => $dupla) {
                if (($key = array_search($funcionario_remover, $dupla)) !== false) {
                    unset($escala_completa[$data_especifica][$turno][$key]);
                    $escala_completa[$data_especifica][$turno] = array_values($escala_completa[$data_especifica][$turno]);
                }
            }
        }
    }
}

// Horários dos plantões
$turnos = [
    '08:00 - 15:00 MANHÃ <i class="fa fa-sun-o"></i>',
    '15:00 - 22:00 NOITE <i class="fa fa-moon-o"></i>'
];

// Inicializa o contador de turnos
$contador_turnos = array_fill_keys($funcionarios, 0);

// Obter as datas (domingos e feriados)
$datas = getDomingosFeriadosMes($feriados);

// Gerar a escala
$escala_completa = [];
foreach ($datas as $data) {
    $funcionarios_ja_escalados = [];
    $escala_completa[$data] = gerarEscala($funcionarios, $turnos, $historico_plantao, $data, $funcionarios_ja_escalados, $contador_turnos);
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escala de Plantão</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
    body {
        background-color: #f4f4f4;
    }
    h1, h2, h3 {
        color: #333;
    }
    .turno {
        margin: 10px 0;
    }
    .funcionarios {
        background-color: #f9f9f9;
        padding: 5px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px; /* Ajuste do tamanho da fonte */
    }
    .contador-turnos {
        margin-top: 10px;
        padding: 5px;
        background-color: #fff;
        border-radius: 5px;
        border: 1px solid black;
        font-size: 14px; /* Ajuste do tamanho da fonte */
    }
    .btn-update {
        position: absolute;
        right: 20px;
        top: 20px;
    }
</style>

</head>
<body>

<div class="container mt-4">
    <h1>Escala de Plantão - Mês Atual</h1>
    <br><br>
    <form method="POST" class="text-right mb-2">
        <button type="submit" class="btn btn-primary btn-update">Atualizar Escala</button>
    </form>
    
    <div class="row">
        <div class="col-md-8">
            <?php foreach ($escala_completa as $data => $escala): ?>
                <h2 style="font-size: 18px;"><i class="fa fa-calendar" aria-hidden="true"></i> Data: <?php echo date('d/m/Y', strtotime($data)); ?></h2><br>
                <div class="turno">
                    <?php foreach ($escala as $turno => $dupla): ?>
                        <h4 style="font-size: 16px;">Turno: <?php echo $turno; ?></h4>
                        <div class="funcionarios">
                            <?php if (empty($dupla)): ?>
                                <p style="margin: 0;">Nenhum funcionário escalado</p>
                            <?php else: ?>
                                <ul style="padding-left: 0; list-style-type: none;">
                                    <?php foreach ($dupla as $funcionario): ?>
                                        <li style="margin: 0;"><?php echo $funcionario; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <br>
                <hr>
                <br>
            <?php endforeach; ?>
        </div>
        

        <div class="col-md-4">
            <!-- Contador de turnos -->
            <div class="contador-turnos">
                <h3>Contador de Turnos</h3>
                <ul>
                    <?php foreach ($contador_turnos as $funcionario => $turnos): ?>
                        <li><?php echo $funcionario; ?> <span><?php echo $turnos; ?> turnos</span></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Adicionar Funcionário -->
            <div class="add-funcionario mt-3">
                <h3>Adicionar Funcionário</h3>
                <form method="POST">
                    <input type="text" name="novo_funcionario" class="form-control" placeholder="Nome do funcionário" required>
                    <button type="submit" class="btn btn-success mt-2">Adicionar</button>
                </form>
            </div>

            <!-- Remover Funcionário -->
            <div class="remove-funcionario mt-3">
                <h3>Remover Funcionário</h3>
                <form method="POST">
                    <select name="remover_funcionario" class="form-control" required>
                        <?php foreach ($funcionarios as $funcionario): ?>
                            <option value="<?php echo $funcionario; ?>"><?php echo $funcionario; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-danger mt-2">Remover</button>
                </form>
            </div>

            <!-- Adicionar Feriado -->
            <div class="add-feriado mt-3">
                <h3>Adicionar Feriado</h3>
                <form method="POST">
                    <input type="date" name="feriado" class="form-control" required>
                    <button type="submit" class="btn btn-warning mt-2">Adicionar Feriado</button>
                </form>
            </div>

            <!-- Remover Feriado -->
            <div class="remove-feriado mt-3">
                <h3>Remover Feriado</h3>
                <form method="POST">
                    <select name="remover_feriado" class="form-control" required>
                        <option value="">Selecione um feriado</option>
                        <?php foreach ($feriados as $feriado): ?>
                            <option value="<?php echo $feriado; ?>"><?php echo date('d/m/Y', strtotime($feriado)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-danger mt-2">Remover Feriado</button>
                </form>
            </div>

            <!-- Remover Funcionário em Data Específica -->
            <div class="remove-funcionario-data mt-3">
                <h3>Remover Funcionário em Data Específica</h3>
                <form method="POST">
                    <select name="remover_funcionario_data" class="form-control" required>
                        <?php foreach ($funcionarios as $funcionario): ?>
                            <option value="<?php echo $funcionario; ?>"><?php echo $funcionario; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="data_especifica" class="form-control mt-2" required>
                    <button type="submit" class="btn btn-danger mt-2">Remover Funcionário</button>
                </form>
            </div>
        </div>
    </div>
</div>


<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

