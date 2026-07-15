<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

$arquivo_dados = 'caixinha_dados.json';

// --- BACKUP ZIP COM TODOS OS ARQUIVOS JSON ---
if (isset($_GET['download_backup'])) {
    if (!extension_loaded('zip')) {
        die("A extensão ZIP não está habilitada no seu servidor PHP.");
    }

    $zip = new ZipArchive();
    $zip_filename = 'backup_completo_json_' . date('Y-m-d_H-i') . '.zip';
    $temp_zip = tempnam(sys_get_temp_dir(), 'bkp_');

    if ($zip->open($temp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $dir = __DIR__;
        $files = scandir($dir);
        $arquivos_encontrados = 0;
        
        foreach ($files as $file) {
            // Verifica se a extensão do arquivo é .json e adiciona ao ZIP
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $zip->addFile($dir . '/' . $file, $file);
                $arquivos_encontrados++;
            }
        }
        $zip->close();

        if ($arquivos_encontrados > 0) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
            header('Content-Length: ' . filesize($temp_zip));
            readfile($temp_zip);
        } else {
            echo "Nenhum arquivo JSON encontrado para backup.";
        }
        unlink($temp_zip);
        exit;
    } else {
        die("Falha ao criar o arquivo ZIP.");
    }
}

if (!file_exists($arquivo_dados)) {
    $dados_iniciais = array(
        'usuarios' => array(
            'admin' => array('senha' => md5('admin'), 'role' => 'admin')
        ),
        'tecnicos' => array('Alan', 'Carlos Eduardo', 'Clayton', 'David', 'Gabriel Ferreira', 'Gustavo', 'João Gabriel', 'João Paulo', 'Luan', 'Mateus', 'Max Gomes', 'Rafael Araújo'),
        'tecnicos_ocultos' => array(), 
        'transportes' => array('metro' => 7.90, 'onibus' => 4.30, 'vlt' => 4.30, 'trem' => 7.40),
        'periodo_atual' => '17/06 a 24/06/2026',
        'periodos' => array('17/06 a 24/06/2026' => array('valor_recebido' => 1200.00, 'lancamentos' => array()))
    );
    file_put_contents($arquivo_dados, json_encode($dados_iniciais), LOCK_EX);
}

$dados = json_decode(file_get_contents($arquivo_dados), true);

// Migração para sistema de usuários
if (!isset($dados['usuarios'])) {
    $dados['usuarios'] = array('admin' => array('senha' => md5('admin'), 'role' => 'admin'));
    if (isset($dados['senha'])) { unset($dados['senha']); }
    file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX);
}

if (!isset($dados['tecnicos_ocultos'])) { $dados['tecnicos_ocultos'] = array(); }

if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fazer_login'])) {
    $user_input = trim(strtolower($_POST['usuario']));
    $pass_input = md5($_POST['senha']);
    
    // Busca ignorando maiúsculas e minúsculas no nome de usuário
    $user_found = null;
    foreach ($dados['usuarios'] as $u => $u_data) {
        if (strtolower($u) === $user_input) { $user_found = $u; break; }
    }
    
    if ($user_found && $dados['usuarios'][$user_found]['senha'] === $pass_input) { 
        $_SESSION['logado'] = true; 
        $_SESSION['usuario_logado'] = $user_found;
        $_SESSION['role'] = $dados['usuarios'][$user_found]['role'];
        
        // FORÇA O SERVIDOR A SALVAR O LOGIN (Correção para o PHP 7.3 Debian)
        session_write_close(); 
        
        header("Location: index.php"); exit; 
    }
    else { $erro_login = "Usuário ou senha incorretos!"; }
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    ?>
    <!DOCTYPE html><html lang="pt-br" data-bs-theme="light"><head><title>Login</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>const savedTheme = localStorage.getItem('theme') || 'light'; document.documentElement.setAttribute('data-bs-theme', savedTheme);</script></head>
    <body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
        <div class="card shadow p-4" style="width: 350px;">
            <form method="post">
                <h4 class="text-center mb-4">🔐 Login do Sistema</h4>
                <?php if(isset($erro_login)): ?><div class="alert alert-danger py-2 text-center"><?php echo $erro_login; ?></div><?php endif; ?>
                <input type="text" name="usuario" class="form-control mb-3" placeholder="Nome de Usuário" required autofocus>
                <input type="password" name="senha" class="form-control mb-3" placeholder="Senha" required>
                <button type="submit" name="fazer_login" class="btn btn-primary w-100 fw-bold">Entrar</button>
            </form>
        </div>
    </body></html>
    <?php exit;
}

// Verifica se o usuário atual é Admin
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// Retira o acesso a links via URL (GET) caso o usuário seja apenas visualizador
if (!$is_admin) {
    unset($_GET['del_periodo'], $_GET['del_tecnico'], $_GET['toggle_vis_tecnico'], $_GET['del_transporte'], $_GET['del_lancamento'], $_GET['del_usuario']);
    if (isset($_GET['edit_lancamento'])) { header("Location: index.php"); exit; }
}

$msg_senha = '';
$msg_usuarios = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // QUALQUER UM PODE MUDAR A PRÓPRIA SENHA
    if (isset($_POST['alterar_senha'])) {
        $user_logado = $_SESSION['usuario_logado'];
        if (md5($_POST['senha_atual']) === $dados['usuarios'][$user_logado]['senha']) {
            $dados['usuarios'][$user_logado]['senha'] = md5($_POST['nova_senha']);
            file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX);
            $msg_senha = "<div class='alert alert-success py-2 mt-2 small'>Sua senha foi alterada!</div>";
        } else { $msg_senha = "<div class='alert alert-danger py-2 mt-2 small'>Senha atual incorreta!</div>"; }
    }
    
    // APENAS ADMIN PODE GERENCIAR USUÁRIOS
    if (isset($_POST['add_usuario']) && $is_admin) {
        $novo_user = trim(strtolower($_POST['novo_usuario']));
        if (!empty($novo_user) && !isset($dados['usuarios'][$novo_user])) {
            $dados['usuarios'][$novo_user] = array(
                'senha' => md5($_POST['senha_novo_usuario']),
                'role' => $_POST['role_novo_usuario']
            );
            file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX);
            $msg_usuarios = "<div class='alert alert-success py-2 mt-2 small'>Usuário criado!</div>";
        } else {
            $msg_usuarios = "<div class='alert alert-danger py-2 mt-2 small'>Nome inválido ou já existe!</div>";
        }
    }
}

if (isset($_GET['del_usuario']) && $is_admin) {
    $u_del = $_GET['del_usuario'];
    if ($u_del !== 'admin' && $u_del !== $_SESSION['usuario_logado']) {
        unset($dados['usuarios'][$u_del]);
        file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX);
    }
    header("Location: index.php"); exit;
}

$periodo_selecionado = isset($_GET['periodo']) ? $_GET['periodo'] : (isset($dados['periodo_atual']) ? $dados['periodo_atual'] : '');
if (!isset($dados['periodos'][$periodo_selecionado])) {
    $chaves = array_keys($dados['periodos']);
    if (count($chaves) > 0) {
        $periodo_selecionado = end($chaves);
        $dados['periodo_atual'] = $periodo_selecionado;
        file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX);
    } else {
        $periodo_selecionado = 'Novo Período';
        $dados['periodos']['Novo Período'] = array('valor_recebido' => 0, 'lancamentos' => array());
        $dados['periodo_atual'] = 'Novo Período';
        file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX);
    }
}

$edit_item = null;
if (isset($_GET['edit_lancamento']) && $is_admin) {
    foreach ($dados['periodos'][$periodo_selecionado]['lancamentos'] as $l) {
        if ((string)$l['id'] === (string)$_GET['edit_lancamento']) { $edit_item = $l; break; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['alterar_senha']) && !isset($_POST['add_usuario']) && $is_admin) {
    
    // --- LÓGICA PARA RENOMEAR PERÍODO ---
    if (isset($_POST['renomear_periodo'])) {
        $nome_antigo = $_POST['nome_periodo_antigo'];
        $novo_nome = trim($_POST['novo_nome_periodo']);

        if (!empty($novo_nome) && $novo_nome !== $nome_antigo && !isset($dados['periodos'][$novo_nome])) {
            $novos_periodos = array();
            foreach ($dados['periodos'] as $chave => $valor) {
                if ((string)$chave === (string)$nome_antigo) {
                    $novos_periodos[$novo_nome] = $valor;
                } else {
                    $novos_periodos[$chave] = $valor;
                }
            }
            $dados['periodos'] = $novos_periodos;
            if ($dados['periodo_atual'] === $nome_antigo) {
                $dados['periodo_atual'] = $novo_nome;
            }
            file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX);
            header("Location: index.php?periodo=" . urlencode($novo_nome));
            exit;
        }
    }
    
    function remover_acentos($str) {
        $com_acentos = array('à','á','â','ã','ä','å','ç','è','é','ê','ë','ì','í','î','ï','ñ','ò','ó','ô','õ','ö','ù','ú','û','ü','ý','ÿ','À','Á','Â','Ã','Ä','Å','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ñ','Ò','Ó','Ô','Õ','Ö','Ù','Ú','Û','Ü','Ý');
        $sem_acentos = array('a','a','a','a','a','a','c','e','e','e','e','i','i','i','i','n','o','o','o','o','o','u','u','u','u','y','y','A','A','A','A','A','A','C','E','E','E','E','I','I','I','I','N','O','O','O','O','O','U','U','U','U','Y');
        return str_replace($com_acentos, $sem_acentos, $str);
    }
    
    if (isset($_POST['criar_periodo'])) {
        $novo_nome_periodo = trim($_POST['nome_periodo']);
        $valor_inicial = (float)str_replace(',', '.', $_POST['valor_inicial_periodo']);
        
        if (!empty($novo_nome_periodo) && !isset($dados['periodos'][$novo_nome_periodo])) {
            copy($arquivo_dados, 'backup_historico_' . date('Y-m-d_H-i') . '.json');
            
            $saldos_transicao = array();
            foreach ($dados['tecnicos'] as $t) { $saldos_transicao[$t] = 0; }
            if (isset($dados['periodos'][$periodo_selecionado])) {
                foreach ($dados['periodos'][$periodo_selecionado]['lancamentos'] as $l) {
                    $tec = $l['tecnico'];
                    if (!isset($saldos_transicao[$tec])) { $saldos_transicao[$tec] = 0; }
                    if ($l['tipo'] === 'Entrega') { $saldos_transicao[$tec] += $l['valor']; } else { $saldos_transicao[$tec] -= $l['valor']; }
                }
            }

            $lancamentos_iniciais = array();
            $hoje = date('Y-m-d');
            $contador_id = 0;
            
            foreach ($saldos_transicao as $tec => $saldo) {
                if (round($saldo, 2) != 0) {
                    $contador_id++;
                    $lancamentos_iniciais[] = array(
                        'id' => time() . rand(100, 999) . $contador_id,
                        'data' => $hoje,
                        'horario' => date('H:i'),
                        'data_insercao' => date('d/m/Y'),
                        'tecnico' => $tec,
                        'valor' => abs($saldo),
                        'tipo' => $saldo > 0 ? 'Entrega' : 'Gasto',
                        'observacao' => "Saldo transportado do período: " . $periodo_selecionado,
                        'criado_por' => 'Sistema'
                    );
                }
            }
            
            $dados['periodos'][$novo_nome_periodo] = array('valor_recebido' => $valor_inicial, 'lancamentos' => $lancamentos_iniciais);
            $dados['periodo_atual'] = $novo_nome_periodo;
            
            file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX);
            header("Location: index.php?periodo=" . urlencode($novo_nome_periodo));
            exit;
        }
    }
    
    if (isset($_POST['add_lancamento'])) {
        $data_inserida = $_POST['data'];
        $tipo = $_POST['tipo'];
        $observacao = $_POST['observacao']; 
        $valor = (float)str_replace(',', '.', $_POST['valor']);
        $id_editar = $_POST['id_editar'];
        
        if (strtolower(trim(remover_acentos($observacao))) === 'entrega' && $valor > 0) { $tipo = 'Entrega'; }
        
        if ($tipo === 'Gasto') {
            $valor_automatico = 0;
            $observacao_segura = mb_strtolower($observacao, 'UTF-8');
            if (preg_match_all('/\(([^)]+)\)/u', $observacao_segura, $matches_parenteses)) {
                foreach ($matches_parenteses[1] as $conteudo_dentro) {
                    $conteudo_limpo = remover_acentos($conteudo_dentro);
                    foreach ($dados['transportes'] as $nome_transporte => $valor_transporte) {
                        $nome_limpo = remover_acentos(mb_strtolower(trim($nome_transporte), 'UTF-8'));
                        $pattern = '/' . preg_quote($nome_limpo, '/') . '/i';
                        $qtd = preg_match_all($pattern, $conteudo_limpo, $matches_transporte);
                        if ($qtd > 0) { $valor_automatico += ($qtd * (float)$valor_transporte); }
                    }
                }
            }
            if ($valor_automatico > 0) { $valor = $valor_automatico; }
        }
        
        if (!empty($id_editar)) {
            foreach ($dados['periodos'][$periodo_selecionado]['lancamentos'] as $index => $l) {
                if ((string)$l['id'] === (string)$id_editar) {
                    $dados['periodos'][$periodo_selecionado]['lancamentos'][$index]['data'] = $data_inserida;
                    if (!isset($dados['periodos'][$periodo_selecionado]['lancamentos'][$index]['horario'])) {
                        $dados['periodos'][$periodo_selecionado]['lancamentos'][$index]['horario'] = date('H:i');
                    }
                    if (!isset($dados['periodos'][$periodo_selecionado]['lancamentos'][$index]['data_insercao'])) {
                        $dados['periodos'][$periodo_selecionado]['lancamentos'][$index]['data_insercao'] = date('d/m/Y');
                    }
                    $dados['periodos'][$periodo_selecionado]['lancamentos'][$index]['tecnico'] = $_POST['tecnico'];
                    $dados['periodos'][$periodo_selecionado]['lancamentos'][$index]['tipo'] = $tipo;
                    $dados['periodos'][$periodo_selecionado]['lancamentos'][$index]['observacao'] = $observacao;
                    $dados['periodos'][$periodo_selecionado]['lancamentos'][$index]['valor'] = $valor;
                    $dados['periodos'][$periodo_selecionado]['lancamentos'][$index]['editado_por'] = $_SESSION['usuario_logado']; // Audita quem editou
                    break;
                }
            }
        } else {
            $novo_lancamento = array(
                'id' => time() . rand(100, 999),
                'data' => $data_inserida,
                'horario' => date('H:i'),
                'data_insercao' => date('d/m/Y'),
                'tecnico' => $_POST['tecnico'],
                'valor' => $valor,
                'tipo' => $tipo,
                'observacao' => $observacao,
                'criado_por' => $_SESSION['usuario_logado'] // Audita quem criou
            );
            array_unshift($dados['periodos'][$periodo_selecionado]['lancamentos'], $novo_lancamento);
        }
        
        file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX);
        header("Location: index.php?periodo=" . urlencode($periodo_selecionado) . "&last_date=" . urlencode($data_inserida));
        exit;
    }
    
    if (isset($_POST['update_config'])) {
        $dados['periodos'][$periodo_selecionado]['valor_recebido'] = (float)str_replace(',', '.', $_POST['novo_valor_recebido']);
        file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX);
        header("Location: index.php?periodo=" . urlencode($periodo_selecionado));
        exit;
    }

    if (isset($_POST['add_tecnico'])) {
        $novo_tec = trim($_POST['nome_tecnico']);
        if (!empty($novo_tec) && !in_array($novo_tec, $dados['tecnicos'])) {
            $dados['tecnicos'][] = $novo_tec; sort($dados['tecnicos']);
            file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX);
        }
        header("Location: index.php?periodo=" . urlencode($periodo_selecionado));
        exit;
    }

    if (isset($_POST['add_transporte'])) {
        $nome_transporte = strtolower(trim($_POST['nome_transporte']));
        $valor_transporte = (float)str_replace(',', '.', $_POST['valor_transporte']);
        if (!empty($nome_transporte)) {
            $dados['transportes'][$nome_transporte] = $valor_transporte;
            file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX);
        }
        header("Location: index.php?periodo=" . urlencode($periodo_selecionado));
        exit;
    }
}

if (isset($_GET['del_periodo'])) { unset($dados['periodos'][$_GET['del_periodo']]); file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX); header("Location: index.php"); exit; }
if (isset($_GET['del_tecnico'])) { 
    $dados['tecnicos'] = array_values(array_filter($dados['tecnicos'], function($t) { return $t !== $_GET['del_tecnico']; }));
    file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX); header("Location: index.php?periodo=" . urlencode($periodo_selecionado)); exit; 
}
if (isset($_GET['toggle_vis_tecnico'])) {
    $tec_toggle = $_GET['toggle_vis_tecnico'];
    if (in_array($tec_toggle, $dados['tecnicos_ocultos'])) {
        $dados['tecnicos_ocultos'] = array_values(array_filter($dados['tecnicos_ocultos'], function($t) use ($tec_toggle) { return $t !== $tec_toggle; }));
    } else { $dados['tecnicos_ocultos'][] = $tec_toggle; }
    file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX); header("Location: index.php?periodo=" . urlencode($periodo_selecionado)); exit;
}
if (isset($_GET['del_transporte'])) { unset($dados['transportes'][$_GET['del_transporte']]); file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX); header("Location: index.php?periodo=" . urlencode($periodo_selecionado)); exit; }
if (isset($_GET['del_lancamento'])) {
    $dados['periodos'][$periodo_selecionado]['lancamentos'] = array_values(array_filter($dados['periodos'][$periodo_selecionado]['lancamentos'], function($l) { return (string)$l['id'] !== (string)$_GET['del_lancamento']; }));
    file_put_contents($arquivo_dados, json_encode($dados), LOCK_EX); header("Location: index.php?periodo=" . urlencode($periodo_selecionado)); exit;
}

if (isset($_GET['ordem_data'])) {
    $_SESSION['pref_ordem_tipo'] = 'data';
    $_SESSION['pref_ordem_direcao'] = $_GET['ordem_data'];
} elseif (isset($_GET['ordem_horario'])) {
    $_SESSION['pref_ordem_tipo'] = 'horario';
    $_SESSION['pref_ordem_direcao'] = $_GET['ordem_horario'];
}

if (!isset($_SESSION['pref_ordem_tipo'])) {
    $_SESSION['pref_ordem_tipo'] = 'data';
    $_SESSION['pref_ordem_direcao'] = 'desc';
}

$ordem_data    = ($_SESSION['pref_ordem_tipo'] === 'data') ? $_SESSION['pref_ordem_direcao'] : '';
$ordem_horario = ($_SESSION['pref_ordem_tipo'] === 'horario') ? $_SESSION['pref_ordem_direcao'] : '';

$filtro_inicio = isset($_GET['filtro_inicio']) ? $_GET['filtro_inicio'] : '';
$filtro_fim    = isset($_GET['filtro_fim']) ? $_GET['filtro_fim'] : '';
$filtro_tec    = isset($_GET['filtro_tec']) ? $_GET['filtro_tec'] : '';
$filtro_tipo   = isset($_GET['filtro_tipo']) ? $_GET['filtro_tipo'] : '';

$valor_recebido = $dados['periodos'][$periodo_selecionado]['valor_recebido'];
$todos_lancamentos_periodo = $dados['periodos'][$periodo_selecionado]['lancamentos'];
$tecnicos = $dados['tecnicos'];
$tecnicos_ocultos = $dados['tecnicos_ocultos'];
$transportes = $dados['transportes'];

$lancamentos_filtrados = array();
$total_entregue_filtro = 0;
$total_gasto_filtro = 0;
$total_gasto_global = 0;

foreach ($todos_lancamentos_periodo as $l) { if ($l['tipo'] === 'Gasto') { $total_gasto_global += $l['valor']; } }

foreach ($todos_lancamentos_periodo as $l) {
    if (!empty($filtro_inicio) && $l['data'] < $filtro_inicio) { continue; }
    if (!empty($filtro_fim) && $l['data'] > $filtro_fim) { continue; }
    if (!empty($filtro_tec) && $l['tecnico'] !== $filtro_tec) { continue; }
    if (!empty($filtro_tipo) && $l['tipo'] !== $filtro_tipo) { continue; }
    
    $lancamentos_filtrados[] = $l;
    if ($l['tipo'] === 'Entrega') { $total_entregue_filtro += $l['valor']; } else { $total_gasto_filtro += $l['valor']; }
}

function comparar_data_asc($a, $b) { 
    $time_a = strtotime($a['data'] . ' ' . (isset($a['horario']) ? $a['horario'] : '00:00'));
    $time_b = strtotime($b['data'] . ' ' . (isset($b['horario']) ? $b['horario'] : '00:00'));
    return $time_a - $time_b; 
}
function comparar_data_desc($a, $b) { 
    $time_a = strtotime($a['data'] . ' ' . (isset($a['horario']) ? $a['horario'] : '00:00'));
    $time_b = strtotime($b['data'] . ' ' . (isset($b['horario']) ? $b['horario'] : '00:00'));
    return $time_b - $time_a; 
}

function get_time_registro($item) {
    if (isset($item['data_insercao'])) {
        $date_str = str_replace('/', '-', $item['data_insercao']);
    } else {
        $date_str = $item['data'];
    }
    $time_str = isset($item['horario']) ? $item['horario'] : '00:00';
    return strtotime($date_str . ' ' . $time_str);
}

function comparar_horario_asc($a, $b) { return get_time_registro($a) - get_time_registro($b); }
function comparar_horario_desc($a, $b) { return get_time_registro($b) - get_time_registro($a); }

if (!empty($ordem_horario)) {
    if ($ordem_horario === 'asc') usort($lancamentos_filtrados, 'comparar_horario_asc');
    else usort($lancamentos_filtrados, 'comparar_horario_desc');
} else {
    if ($ordem_data === 'asc') usort($lancamentos_filtrados, 'comparar_data_asc'); 
    else usort($lancamentos_filtrados, 'comparar_data_desc');
}

if (isset($_GET['exportar_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Relatorio_Caixinha_' . str_replace('/', '-', $periodo_selecionado) . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, array('Horario', 'Data', 'Tecnico', 'Tipo', 'Valor (R$)', 'Observacao', 'Criado Por', 'Editado Por'), ';');
    foreach ($lancamentos_filtrados as $l) {
        $horario_csv = isset($l['horario']) ? $l['horario'] : '--:--';
        $criado_csv = isset($l['criado_por']) ? $l['criado_por'] : 'Sistema/Antigo';
        $editado_csv = isset($l['editado_por']) ? $l['editado_por'] : '';
        fputcsv($output, array($horario_csv, date('d/m/Y', strtotime($l['data'])), $l['tecnico'], $l['tipo'], number_format($l['valor'], 2, ',', ''), $l['observacao'], $criado_csv, $editado_csv), ';');
    }
    fclose($output); exit;
}

$dados_grafico_tecnicos = array();
$dados_grafico_dias = array();
foreach ($lancamentos_filtrados as $l) {
    if ($l['tipo'] === 'Gasto') {
        if (!in_array($l['tecnico'], $tecnicos_ocultos)) {
            if (!isset($dados_grafico_tecnicos[$l['tecnico']])) $dados_grafico_tecnicos[$l['tecnico']] = 0;
            $dados_grafico_tecnicos[$l['tecnico']] += $l['valor'];
        }
        $dia_ordenavel = $l['data'];
        if (!isset($dados_grafico_dias[$dia_ordenavel])) $dados_grafico_dias[$dia_ordenavel] = 0;
        $dados_grafico_dias[$dia_ordenavel] += $l['valor'];
    }
}
ksort($dados_grafico_dias);
$labels_tec = array_keys($dados_grafico_tecnicos);
$valores_tec = array_values($dados_grafico_tecnicos);
$labels_dias = array(); $valores_dias = array();
foreach($dados_grafico_dias as $d => $v) { $labels_dias[] = date('d/m', strtotime($d)); $valores_dias[] = round($v, 2); }

$historico_labels = array();
$historico_total_gastos = array();
$historico_tecnicos_data = array();

foreach ($tecnicos as $t) { $historico_tecnicos_data[$t] = array(); }

foreach ($dados['periodos'] as $nome_per => $dados_per) {
    $historico_labels[] = $nome_per;
    $gasto_total_per = 0;
    $gastos_tec_per = array();
    foreach ($tecnicos as $t) { $gastos_tec_per[$t] = 0; }

    foreach ($dados_per['lancamentos'] as $l) {
        if ($l['tipo'] === 'Gasto') {
            $gasto_total_per += $l['valor'];
            if (isset($gastos_tec_per[$l['tecnico']])) { $gastos_tec_per[$l['tecnico']] += $l['valor']; }
        }
    }
    
    $historico_total_gastos[] = $gasto_total_per;
    foreach ($tecnicos as $t) { $historico_tecnicos_data[$t][] = $gastos_tec_per[$t]; }
}

$datasets_hist_tecnicos = array();
$cores_tecnicos = ['#0d6efd', '#198754', '#dc3545', '#ffc107', '#0dcaf0', '#6c757d', '#6610f2', '#d63384', '#fd7e14', '#20c997', '#e83e8c', '#17a2b8'];
$cor_index = 0;
foreach ($tecnicos as $t) {
    if (in_array($t, $tecnicos_ocultos)) continue;
    if (array_sum($historico_tecnicos_data[$t]) > 0) {
        $datasets_hist_tecnicos[] = array(
            'label' => $t,
            'data' => $historico_tecnicos_data[$t],
            'backgroundColor' => $cores_tecnicos[$cor_index % count($cores_tecnicos)]
        );
        $cor_index++;
    }
}

$saldos_tecnicos = array();
foreach ($tecnicos as $t) { $saldos_tecnicos[$t] = 0; }
foreach ($todos_lancamentos_periodo as $l) {
    $tec = $l['tecnico'];
    if (!isset($saldos_tecnicos[$tec])) { $saldos_tecnicos[$tec] = 0; }
    if ($l['tipo'] === 'Entrega') { $saldos_tecnicos[$tec] += $l['valor']; } else { $saldos_tecnicos[$tec] -= $l['valor']; }
}

$total_com_tecnicos_global = 0;
foreach ($saldos_tecnicos as $saldo) { if ($saldo > 0) { $total_com_tecnicos_global += $saldo; } }

$total_devolucao = $valor_recebido - $total_gasto_global;
$saldo_caixa_dinheiro_global = $valor_recebido - $total_gasto_global - $total_com_tecnicos_global;
$data_padrao = isset($_GET['last_date']) ? $_GET['last_date'] : date('Y-m-d');

$url_base_filtros = "?periodo=" . urlencode($periodo_selecionado) . "&filtro_inicio=" . urlencode($filtro_inicio) . "&filtro_fim=" . urlencode($filtro_fim) . "&filtro_tec=" . urlencode($filtro_tec) . "&filtro_tipo=" . urlencode($filtro_tipo);

$nova_ordem_data = ($ordem_data === 'desc') ? 'asc' : 'desc';
$nova_ordem_horario = ($ordem_horario === 'desc') ? 'asc' : 'desc';

$icone_ordem_data = '↕️';
if (!empty($ordem_data)) $icone_ordem_data = ($ordem_data === 'desc') ? '⬇️' : '⬆️';

$icone_ordem_horario = '↕️';
if (!empty($ordem_horario)) $icone_ordem_horario = ($ordem_horario === 'desc') ? '⬇️' : '⬆️';
?>

<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Caixinha por Períodos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>const savedTheme = localStorage.getItem('theme') || 'light'; document.documentElement.setAttribute('data-bs-theme', savedTheme);</script>
    
    <style>
        [data-bs-theme="dark"] body { background-color: #121212; }
        [data-bs-theme="dark"] .bg-white { background-color: #1e1e1e !important; }
        [data-bs-theme="dark"] .card { border-color: #333; }
        
        .card-saldo { border-left: 5px solid #0d6efd; }
        .lista-gerenciavel { max-height: 120px; overflow-y: auto; overflow-x: hidden; list-style: none; padding: 0; padding-right: 12px; }
        .item-gerenciavel { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid var(--bs-border-color); align-items: center; }
        .cabecalho-impressao { display: none; }
        .assinatura-gestor-print { display: none; }
        
        @media print { 
            @page { size: landscape; margin: 10mm; } 
            
            body { background-color: #fff !important; color: #000 !important; font-size: 11pt !important; padding: 0 !important; margin: 0 !important; }
            .container, .container-fluid { min-width: 100% !important; max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
            * { color: #000 !important; }
            .no-print, .btn, .modal, nav, footer { display: none !important; }
            .mt-4, .mb-4, .py-4, .row { margin-top: 0 !important; margin-bottom: 0 !important; padding-top: 0 !important; padding-bottom: 0 !important; }
            
            .primeira-pagina-print {
                display: flex !important;
                flex-direction: column !important;
                height: 18cm !important; 
                page-break-after: always !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .cabecalho-impressao { display: block; text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px !important; }
            .cabecalho-impressao h1 { font-size: 24pt; font-weight: bold; margin: 0; text-transform: uppercase; }
            .cabecalho-impressao h3 { font-size: 16pt; margin: 5px 0; color: #555; }
            
            .print-col-6 { 
                flex: 0 0 50% !important; 
                max-width: 50% !important; 
                padding: 0 10px !important;
                margin-bottom: 15px !important;
            }
            .card { break-inside: avoid; border: 1px solid #ddd !important; border-radius: 0 !important; } 
            
            .assinatura-gestor-print { 
                display: block !important; 
                margin-top: auto !important; 
                text-align: center !important;
                width: 100% !important;
                position: relative !important; 
                padding-top: 60px !important;
            }

            .w-100-print { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .tabela-gastos-print { margin-top: 0 !important; page-break-before: avoid !important; }
            table { width: 100% !important; border-color: #999 !important; margin-bottom: 0 !important; }
            th, td { border-color: #999 !important; }
            
            .coluna-assinatura { display: table-cell !important; width: 25% !important; border-left: 2px solid #000 !important; }
            .tabela-gastos-print tbody tr { height: 50px !important; }
            .tabela-gastos-print td { vertical-align: middle !important; }
            tr.tipo-entrega { display: none !important; }
            .coluna-acoes { display: none !important; }
            
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            canvas { max-width: 100% !important; }
        }
        .coluna-assinatura { display: none; }
    </style>
</head>
<body class="container py-4">

    <div class="row mb-4 no-print align-items-center bg-white p-3 shadow-sm rounded">
        <div class="col-md-5">
            <label class="fw-bold small text-muted">📁 SELECIONAR PERÍODO:</label>
            <div class="d-flex gap-2">
                <select class="form-select" onchange="location = this.value;">
                    <?php 
                    $periodos_reversos = array_reverse(array_keys($dados['periodos']));
                    foreach($periodos_reversos as $nome_p): 
                    ?>
                        <option value="index.php?periodo=<?php echo urlencode($nome_p); ?>" <?php echo $periodo_selecionado === $nome_p ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nome_p); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if($is_admin): ?>
                <button type="button" class="btn btn-warning px-2" data-bs-toggle="modal" data-bs-target="#modalRenomearPeriodo" title="Renomear Período" onclick="saveScrollPosition()">✏️</button>
                <a href="?del_periodo=<?php echo urlencode($periodo_selecionado); ?>" class="btn btn-danger" data-original-text="Apagar" data-original-class="btn-danger" onclick="return confirmDelete(this);">Apagar</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-7 border-start">
            <?php if($is_admin): ?>
            <form method="post" class="row g-2 align-items-end">
                <div class="col-7">
                    <label class="fw-bold small text-success">➕ GERAR NOVO PERÍODO:</label>
                    <input type="text" name="nome_periodo" class="form-control form-control-sm" placeholder="Ex: 24/06 a 01/07/2026" required>
                </div>
                <div class="col-3">
                    <label class="fw-bold small text-muted">Caixa Inicial</label>
                    <input type="number" step="0.01" name="valor_inicial_periodo" class="form-control form-control-sm" value="1200.00" required>
                </div>
                <div class="col-2">
                    <button type="submit" name="criar_periodo" class="btn btn-sm btn-success w-100" style="height: 31px;" onclick="saveScrollPosition()">Criar</button>
                </div>
            </form>
            <?php else: ?>
            <div class="d-flex align-items-center h-100 justify-content-end">
                <span class="text-muted fw-bold">Modo Visualização Ativo</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TÍTULO DA PÁGINA E BOTÕES -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div class="d-flex align-items-center gap-3">
            <h2 class="mb-0">📊 Período: <span class="text-primary"><?php echo htmlspecialchars($periodo_selecionado); ?></span></h2>
            <button id="btnThemeToggle" class="btn btn-outline-secondary fw-bold" onclick="toggleTheme()" title="Alternar Modo Escuro">🌙 Escuro</button>
        </div>
        <div class="d-flex flex-column align-items-end gap-2">
            <button onclick="window.print()" class="btn btn-dark fw-bold w-100">🖨️ Imprimir Resumo</button>
            <div class="d-flex gap-2 w-100">
                <button onclick="imprimirComGraficos()" class="btn btn-outline-dark btn-sm fw-bold w-100">📈 Imprimir com Gráficos</button>
                <button type="button" class="btn btn-info btn-sm fw-bold w-100 text-white" data-bs-toggle="modal" data-bs-target="#modalComparativo">📊 Gerar Comparativos</button>
            </div>
        </div>
    </div>

    <div class="primeira-pagina-print">
        <div class="cabecalho-impressao">
            <h1>Prestação de Contas</h1>
            <h3>Entrega do Relatório do Caixa do Acesso</h3>
            <p><strong>Período de Referência:</strong> <?php echo htmlspecialchars($periodo_selecionado); ?></p>
            <p>Impresso em: <?php echo date('d/m/Y \à\s H:i'); ?></p>
            <p>Responsável: <?php echo htmlspecialchars(ucfirst($_SESSION['usuario_logado'])); ?></p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg print-col-6 mb-3">
                <div class="card card-saldo shadow-sm p-3 h-100 bg-primary-subtle">
                    <small class="text-muted text-uppercase fw-bold">Caixa do Período</small>
                    <h4 class="text-primary">R$ <?php echo number_format($valor_recebido, 2, ',', '.'); ?></h4>
                    <?php if($is_admin): ?>
                    <form method="post" class="mt-2 d-flex no-print">
                        <input type="number" step="0.01" name="novo_valor_recebido" class="form-control form-control-sm me-2" value="<?php echo number_format($valor_recebido, 2, '.', ''); ?>" required>
                        <button type="submit" name="update_config" class="btn btn-sm btn-outline-primary" onclick="saveScrollPosition()">Alterar</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-lg print-col-6 mb-3">
                <div class="card shadow-sm p-3 h-100 bg-danger-subtle" style="border-left: 5px solid #dc3545;">
                    <small class="text-muted text-uppercase fw-bold">Total Gasto</small>
                    <h4 class="text-danger">R$ <?php echo number_format($total_gasto_global, 2, ',', '.'); ?></h4>
                </div>
            </div>
            
            <div class="col-lg mb-3 no-print">
                <div class="card shadow-sm p-3 h-100 bg-info-subtle" style="border-left: 5px solid #17a2b8;">
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.8rem;">Total Entregue aos Técnicos</small>
                    <h4 class="text-info">R$ <?php echo number_format($total_entregue_filtro, 2, ',', '.'); ?></h4>
                </div>
            </div>
            
            <div class="col-lg print-col-6 mb-3">
                <div class="card shadow-sm p-3 h-100 bg-warning-subtle" style="border-left: 5px solid #ffc107;">
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.8rem;">Total Atual com Técnicos</small>
                    <h4 class="text-warning text-dark">R$ <?php echo number_format($total_com_tecnicos_global, 2, ',', '.'); ?></h4>
                </div>
            </div>
            
            <div class="col-lg print-col-6 mb-3 no-print">
                <div class="card shadow-sm p-3 h-100 bg-success-subtle" style="border-left: 5px solid #198754;">
                    <small class="text-muted text-uppercase fw-bold">Total Devolução</small>
                    <h4 class="text-success">R$ <?php echo number_format($total_devolucao, 2, ',', '.'); ?></h4>
                </div>
            </div>
            
            <div class="col-lg print-col-6 mb-3">
                <div class="card shadow-sm p-3 h-100 bg-secondary-subtle" style="border-left: 5px solid #6c757d;">
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Total no Caixinha Atual</small>
                    <h4 class="text-secondary">R$ <?php echo number_format($saldo_caixa_dinheiro_global, 2, ',', '.'); ?></h4>
                </div>
            </div>
        </div>

        <div class="assinatura-gestor-print">
            <div style="width: 400px; margin: 0 auto; border-top: 2px solid #000; padding-top: 10px;">
                <strong>Assinatura do Gestor</strong><br>
            </div>
        </div>
    </div> <!-- Fim da 1ª Página -->

    <div id="local-graficos-original">
        <div id="secao-graficos" class="row mb-4 no-print">
            <div class="col-md-6 mb-3">
                <div class="card shadow-sm p-3 h-100">
                    <h6 class="text-muted fw-bold border-bottom pb-2">Distribuição de Gastos por Técnico</h6>
                    <div style="height: 250px;"><canvas id="chartTecnicos"></canvas></div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card shadow-sm p-3 h-100">
                    <h6 class="text-muted fw-bold border-bottom pb-2">Evolução de Gastos (Dias)</h6>
                    <div style="height: 250px;"><canvas id="chartDias"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4 no-print"> 
        <div class="col-12">
            <div class="card shadow-sm p-4">
                <h5 class="mb-3">📊 Balanço por Colaborador (Período em Foco)</h5>
                <table class="table table-bordered mt-2">
                    <thead>
                        <tr class="table-light"><th>Técnico / Colaborador</th><th class="text-end">Saldo Atual em Mãos</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($saldos_tecnicos as $tec => $saldo): ?>
                            <?php if (in_array($tec, $tecnicos_ocultos)) continue; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tec); ?></td>
                                <td class="text-end fw-bold <?php echo $saldo < 0 ? 'text-danger' : 'text-success'; ?>">
                                    R$ <?php echo number_format($saldo, 2, ',', '.'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm p-4 mb-4 no-print mt-4 bg-secondary-subtle">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="m-0">🔍 Filtrar Dados Deste Período</h5>
            <a href="<?php echo $url_base_filtros . '&exportar_csv=1'; ?>" class="btn btn-sm btn-success fw-bold">📥 Exportar para Excel (.csv)</a>
        </div>
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="periodo" value="<?php echo htmlspecialchars($periodo_selecionado); ?>">
            <div class="col-md-2">
                <label class="small fw-bold">Data Início</label>
                <input type="date" name="filtro_inicio" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filtro_inicio); ?>">
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">Data Fim</label>
                <input type="date" name="filtro_fim" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filtro_fim); ?>">
            </div>
            <div class="col-md-3">
                <label class="small fw-bold">Técnico</label>
                <select name="filtro_tec" class="form-select form-select-sm">
                    <option value="">-- Todos os Técnicos --</option>
                    <?php foreach($tecnicos as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $filtro_tec === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold">Tipo</label>
                <select name="filtro_tipo" class="form-select form-select-sm">
                    <option value="">-- Todos --</option>
                    <option value="Gasto" <?php echo $filtro_tipo === 'Gasto' ? 'selected' : ''; ?>>Gasto</option>
                    <option value="Entrega" <?php echo $filtro_tipo === 'Entrega' ? 'selected' : ''; ?>>Entrega</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button>
                <a href="index.php?periodo=<?php echo urlencode($periodo_selecionado); ?>" class="btn btn-sm btn-secondary w-100">Limpar</a>
            </div>
        </form>
    </div>

    <div class="row mt-4">
        
        <?php if($is_admin): ?>
        <!-- SIDEBAR DE REGISTRO (VISÍVEL APENAS PARA ADMIN) -->
        <div class="col-md-4 no-print">
            <div class="card shadow-sm p-4 mb-4" style="<?php echo $edit_item ? 'border: 2px solid #ffc107;' : ''; ?>">
                <h5><?php echo $edit_item ? '⚠️ Editar Registro' : 'Novo Lançamento'; ?></h5>
                <form method="post">
                    <input type="hidden" name="id_editar" value="<?php echo $edit_item ? htmlspecialchars($edit_item['id']) : ''; ?>">
                    
                    <div class="mb-3">
                        <label>Data do Gasto</label>
                        <input type="date" name="data" class="form-control" value="<?php echo $edit_item ? htmlspecialchars($edit_item['data']) : htmlspecialchars($data_padrao); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Técnico / Equipe</label>
                        <select name="tecnico" class="form-select" required>
                            <option value="">-- Selecione o Técnico --</option>
                            <?php foreach($tecnicos as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($edit_item && $edit_item['tecnico'] === $t) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="Gasto" <?php echo ($edit_item && $edit_item['tipo'] === 'Gasto') ? 'selected' : ''; ?>>Gasto (Técnico gastou)</option>
                            <option value="Entrega" <?php echo ($edit_item && $edit_item['tipo'] === 'Entrega') ? 'selected' : ''; ?>>Entrega (Caixa deu dinheiro)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Observação / Rota</label>
                        <textarea name="observacao" class="form-control" placeholder="Ex: Mls > Viva lapa (onibus) > Mls (metro)" required><?php echo $edit_item ? htmlspecialchars($edit_item['observacao']) : ''; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Valor Manual (R$)</label>
                        <input type="number" step="0.01" name="valor" class="form-control" placeholder="Opcional se usar rota" value="<?php echo $edit_item ? htmlspecialchars($edit_item['valor']) : ''; ?>">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="add_lancamento" class="btn <?php echo $edit_item ? 'btn-warning' : 'btn-primary'; ?> w-100" onclick="saveScrollPosition()">
                            <?php echo $edit_item ? 'Salvar Alteração' : 'Registrar'; ?>
                        </button>
                        <?php if($edit_item): ?>
                            <a href="index.php?periodo=<?php echo urlencode($periodo_selecionado); ?>" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card shadow-sm p-4 mb-4">
                <h5>🚊 Meios de Transporte</h5>
                <form method="post" class="mb-3">
                    <div class="row g-2">
                        <div class="col-7"><input type="text" name="nome_transporte" id="input_nome_transporte" class="form-control form-control-sm" placeholder="Ex: metro" required></div>
                        <div class="col-3"><input type="number" step="0.01" name="valor_transporte" id="input_valor_transporte" class="form-control form-control-sm" placeholder="7.90" required></div>
                        <div class="col-2"><button type="submit" name="add_transporte" class="btn btn-sm btn-success w-100" onclick="saveScrollPosition()">+</button></div>
                    </div>
                </form>
                <ul class="lista-gerenciavel">
                    <?php foreach($transportes as $nome => $preco): ?>
                        <li class="item-gerenciavel small">
                            <span><strong class="text-uppercase"><?php echo htmlspecialchars($nome); ?></strong> - R$ <?php echo number_format($preco, 2, ',', '.'); ?></span>
                            <div>
                                <button type="button" class="btn btn-sm text-primary py-0 px-1 border-0" onclick="editarTransporte('<?php echo htmlspecialchars(addslashes($nome)); ?>', '<?php echo number_format($preco, 2, '.', ''); ?>')" title="Editar">✏️</button>
                                <a href="?periodo=<?php echo urlencode($periodo_selecionado); ?>&del_transporte=<?php echo urlencode($nome); ?>" class="text-danger text-decoration-none fw-bold ms-1" data-original-text="X" data-original-class="text-danger" onclick="return confirmDelete(this);" title="Excluir">X</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="card shadow-sm p-4 mb-4">
                <h5>🔧 Gerenciar Técnicos</h5>
                <form method="post" class="mb-3">
                    <div class="input-group input-group-sm">
                        <input type="text" name="nome_tecnico" class="form-control" placeholder="Nome do Técnico" required>
                        <button type="submit" name="add_tecnico" class="btn btn-secondary" onclick="saveScrollPosition()">+</button>
                    </div>
                </form>
                <ul class="lista-gerenciavel">
                    <?php foreach($tecnicos as $t): ?>
                        <?php $is_hidden = in_array($t, $tecnicos_ocultos); ?>
                        <li class="item-gerenciavel small <?php echo $is_hidden ? 'text-muted' : ''; ?>">
                            <span class="<?php echo $is_hidden ? 'text-decoration-line-through' : ''; ?>"><?php echo htmlspecialchars($t); ?></span>
                            <div>
                                <a href="?periodo=<?php echo urlencode($periodo_selecionado); ?>&toggle_vis_tecnico=<?php echo urlencode($t); ?>" class="text-decoration-none me-2 fs-6" onclick="saveScrollPosition()" title="<?php echo $is_hidden ? 'Mostrar no Balanço' : 'Ocultar do Balanço'; ?>">
                                    <?php echo $is_hidden ? '🙈' : '👁️'; ?>
                                </a>
                                <a href="?periodo=<?php echo urlencode($periodo_selecionado); ?>&del_tecnico=<?php echo urlencode($t); ?>" class="text-danger text-decoration-none fw-bold" data-original-text="X" data-original-class="text-danger" onclick="return confirmDelete(this);">X</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-md-<?php echo $is_admin ? '8' : '12'; ?> w-100-print">
            <div class="card shadow-sm p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="m-0">📋 Histórico de Movimentações</h5>
                </div>
                
                <table class="table table-hover table-bordered table-sm tabela-gastos-print">
                    <thead class="table-light">
                        <tr>
                            <th class="col-horario no-print">
                                Registro
                                <a href="<?php echo $url_base_filtros . '&ordem_horario=' . $nova_ordem_horario; ?>" class="btn btn-sm btn-outline-secondary py-0 px-1 ms-1 text-decoration-none border-0 no-print" title="Ordenar por Horário" onclick="saveScrollPosition()">
                                    <?php echo $icone_ordem_horario; ?>
                                </a>
                            </th> 
                            <th class="col-data">
                                Data Gasto
                                <a href="<?php echo $url_base_filtros . '&ordem_data=' . $nova_ordem_data; ?>" class="btn btn-sm btn-outline-secondary py-0 px-1 ms-1 text-decoration-none border-0 no-print" title="Ordenar por data" onclick="saveScrollPosition()">
                                    <?php echo $icone_ordem_data; ?>
                                </a>
                            </th>
                            <th class="col-tec">Técnico</th>
                            <th class="col-tipo">Tipo</th>
                            <th class="col-valor">Valor</th>
                            <th>Observação / Rota</th>
                            <?php if($is_admin): ?>
                                <th class="coluna-acoes">Ações</th>
                            <?php endif; ?>
                            <th class="coluna-assinatura">Assinatura do Técnico</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($lancamentos_filtrados) === 0): ?>
                            <tr><td colspan="<?php echo $is_admin ? '8' : '7'; ?>" class="text-center text-muted py-4">Nenhum registro encontrado para este filtro.</td></tr>
                        <?php endif; ?>
                        <?php foreach($lancamentos_filtrados as $l): ?>
                        
                        <?php
                            // Prepara a mensagem de auditoria flutuante (Hover)
                            $msg_auditoria = "Lançado por: " . (isset($l['criado_por']) ? ucfirst(htmlspecialchars($l['criado_por'])) : 'Sistema/Antigo');
                            if (isset($l['editado_por'])) {
                                $msg_auditoria .= " | Última edição por: " . ucfirst(htmlspecialchars($l['editado_por']));
                            }
                        ?>
                        
                        <tr class="<?php echo $l['tipo'] === 'Entrega' ? 'tipo-entrega' : ''; ?>" title="<?php echo $msg_auditoria; ?>">
                            <td class="align-middle no-print text-muted fw-bold" style="font-size: 0.85em;">
                                <?php if(isset($l['data_insercao'])): ?>
                                    <span class="fw-normal" style="font-size: 0.9em;"><?php echo htmlspecialchars($l['data_insercao']); ?></span><br>
                                <?php endif; ?>
                                <?php echo isset($l['horario']) ? htmlspecialchars($l['horario']) : '--:--'; ?>
                            </td>
                            <td class="align-middle"><?php echo date('d/m/Y', strtotime($l['data'])); ?></td>
                            <td class="align-middle"><strong><?php echo htmlspecialchars($l['tecnico']); ?></strong></td>
                            <td class="align-middle">
                                <span class="badge <?php echo $l['tipo'] === 'Entrega' ? 'bg-info text-dark' : 'bg-warning text-dark'; ?>">
                                    <?php echo htmlspecialchars($l['tipo']); ?>
                                </span>
                            </td>
                            <td class="align-middle fw-bold <?php echo $l['tipo'] === 'Entrega' ? 'text-primary' : 'text-danger'; ?>">
                                R$ <?php echo number_format($l['valor'], 2, ',', '.'); ?>
                            </td>
                            <td class="align-middle small text-muted"><?php echo htmlspecialchars($l['observacao']); ?></td>
                            
                            <?php if($is_admin): ?>
                            <td class="align-middle coluna-acoes">
                                <a href="?periodo=<?php echo urlencode($periodo_selecionado); ?>&edit_lancamento=<?php echo $l['id']; ?>" class="btn btn-sm btn-outline-warning py-0 px-1 me-1" onclick="saveScrollPosition()">Editar</a>
                                <a href="?periodo=<?php echo urlencode($periodo_selecionado); ?>&del_lancamento=<?php echo $l['id']; ?>" class="btn btn-sm btn-outline-danger py-0 px-1" data-original-text="Apagar" data-original-class="btn-outline-danger" onclick="return confirmDelete(this);">Apagar</a>
                            </td>
                            <?php endif; ?>
                            
                            <td class="coluna-assinatura"></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div id="local-graficos-impressao"></div>

    <div class="row mt-4 no-print mb-5">
        <div class="col-md-12">
            <div class="card shadow-sm p-4 border-top border-4 border-secondary">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="m-0">⚙️ Configurações de Segurança</h5>
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-muted small">Logado como: <strong><?php echo htmlspecialchars(ucfirst($_SESSION['usuario_logado'])); ?></strong> (<?php echo $is_admin ? 'Acesso Total' : 'Visualizador'; ?>)</span>
                        <a href="?logout=1" class="btn btn-sm btn-danger fw-bold px-4">Sair do Sistema</a>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-<?php echo $is_admin ? '4' : '6'; ?>">
                        <form method="post" class="border p-3 rounded bg-secondary-subtle h-100">
                            <label class="fw-bold small mb-2">Alterar Minha Senha</label>
                            <input type="password" name="senha_atual" class="form-control form-control-sm mb-2" placeholder="Senha Atual" required>
                            <input type="password" name="nova_senha" class="form-control form-control-sm mb-2" placeholder="Nova Senha" required>
                            <button type="submit" name="alterar_senha" class="btn btn-sm btn-secondary w-100" onclick="saveScrollPosition()">Atualizar Senha</button>
                            <?php echo $msg_senha; ?>
                        </form>
                    </div>
                    
                    <?php if($is_admin): ?>
                    <div class="col-md-5 border-start px-4">
                        <label class="fw-bold small mb-2">Gerenciar Usuários</label>
                        <form method="post" class="d-flex gap-2 mb-2">
                            <input type="text" name="novo_usuario" class="form-control form-control-sm" placeholder="Nome" required>
                            <input type="password" name="senha_novo_usuario" class="form-control form-control-sm" placeholder="Senha" required>
                            <select name="role_novo_usuario" class="form-select form-select-sm" style="width: auto;">
                                <option value="admin">Acesso Total</option>
                                <option value="viewer">Visualizador</option>
                            </select>
                            <button type="submit" name="add_usuario" class="btn btn-sm btn-primary" onclick="saveScrollPosition()">Criar</button>
                        </form>
                        <?php echo $msg_usuarios; ?>
                        
                        <div class="mt-2" style="max-height: 100px; overflow-y: auto;">
                            <ul class="list-group list-group-flush">
                                <?php foreach($dados['usuarios'] as $u_nome => $u_dados): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2 small bg-transparent">
                                        <span><strong><?php echo htmlspecialchars(ucfirst($u_nome)); ?></strong> <span class="text-muted">(<?php echo $u_dados['role'] === 'admin' ? 'Total' : 'Apenas Ver'; ?>)</span></span>
                                        <?php if($u_nome !== 'admin' && $u_nome !== $_SESSION['usuario_logado']): ?>
                                            <a href="?del_usuario=<?php echo urlencode($u_nome); ?>" class="text-danger fw-bold text-decoration-none" onclick="return confirmDelete(this);">X</a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-<?php echo $is_admin ? '3' : '6'; ?> border-start text-muted small d-flex flex-column justify-content-center px-4">
                        <h6 class="fw-bold small text-dark mb-2">Backup de Dados</h6>
                        <a href="?download_backup=1" class="btn btn-sm btn-info w-100 fw-bold text-white mb-2">📥 Baixar Backups (ZIP)</a>
                        <p class="mb-0" style="font-size: 0.8em;">Lembre-se sempre de clicar no botão vermelho "Sair" após terminar os seus registos.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalComparativo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl"> 
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">📈 Comparativo de Períodos Históricos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body bg-body-tertiary">
                    <div class="card shadow-sm p-3 mb-4">
                        <h6 class="text-muted fw-bold mb-3 border-bottom pb-2">Uso Monetário Geral (Evolução)</h6>
                        <div style="height: 250px;"><canvas id="chartComparativoGeral"></canvas></div>
                    </div>
                    <div class="card shadow-sm p-3">
                        <h6 class="text-muted fw-bold mb-3 border-bottom pb-2">Uso Monetário por Técnicos (Comparativo)</h6>
                        <div style="height: 350px;"><canvas id="chartComparativoTecnicos"></canvas></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if($is_admin): ?>
    <div class="modal fade" id="modalRenomearPeriodo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"> 
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">✏️ Renomear Período</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="nome_periodo_antigo" value="<?php echo htmlspecialchars($periodo_selecionado); ?>">
                        <label class="form-label fw-bold">Novo Nome para o Período:</label>
                        <input type="text" name="novo_nome_periodo" class="form-control" value="<?php echo htmlspecialchars($periodo_selecionado); ?>" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="renomear_periodo" class="btn btn-warning text-dark fw-bold" onclick="saveScrollPosition()">Salvar Alteração</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        let chartTec, chartDias, chartCompGeral, chartCompTec;
        let temaOriginalAntesImpressao = null;

        window.addEventListener('DOMContentLoaded', () => {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme');
            const btnTheme = document.getElementById('btnThemeToggle');
            if(btnTheme) {
                btnTheme.innerHTML = currentTheme === 'dark' ? '☀️ Claro' : '🌙 Escuro';
            }
            
            Chart.defaults.color = currentTheme === 'dark' ? '#adb5bd' : '#666';

            const labelsTec = <?php echo json_encode($labels_tec); ?>;
            const valoresTec = <?php echo json_encode($valores_tec); ?>;
            if (labelsTec.length > 0) {
                chartTec = new Chart(document.getElementById('chartTecnicos'), {
                    type: 'doughnut',
                    data: {
                        labels: labelsTec,
                        datasets: [{
                            data: valoresTec,
                            backgroundColor: ['#0d6efd', '#198754', '#dc3545', '#ffc107', '#0dcaf0', '#6c757d', '#6610f2', '#d63384', '#fd7e14', '#20c997'],
                            borderColor: currentTheme === 'dark' ? '#1e1e1e' : '#fff'
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
                });
            }

            const labelsDias = <?php echo json_encode($labels_dias); ?>;
            const valoresDias = <?php echo json_encode($valores_dias); ?>;
            if (labelsDias.length > 0) {
                chartDias = new Chart(document.getElementById('chartDias'), {
                    type: 'bar',
                    data: {
                        labels: labelsDias,
                        datasets: [{
                            label: 'Total Gasto (R$)',
                            data: valoresDias,
                            backgroundColor: '#0d6efd'
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
                });
            }

            const modalComp = document.getElementById('modalComparativo');
            if (modalComp) {
                modalComp.addEventListener('shown.bs.modal', function () {
                    if (!chartCompGeral) {
                        chartCompGeral = new Chart(document.getElementById('chartComparativoGeral'), {
                            type: 'line',
                            data: {
                                labels: <?php echo json_encode($historico_labels); ?>,
                                datasets: [{
                                    label: 'Total Gasto na Semana (R$)',
                                    data: <?php echo json_encode($historico_total_gastos); ?>,
                                    borderColor: '#dc3545',
                                    backgroundColor: 'rgba(220, 53, 69, 0.2)',
                                    fill: true,
                                    tension: 0.3,
                                    borderWidth: 3,
                                    pointBackgroundColor: '#dc3545',
                                    pointRadius: 5
                                }]
                            },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
                        });
                    }

                    if (!chartCompTec) {
                        chartCompTec = new Chart(document.getElementById('chartComparativoTecnicos'), {
                            type: 'bar',
                            data: {
                                labels: <?php echo json_encode($historico_labels); ?>,
                                datasets: <?php echo json_encode($datasets_hist_tecnicos); ?>
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { position: 'bottom' } },
                                scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
                            }
                        });
                    }
                });
            }
        });

        window.addEventListener('beforeprint', () => {
            temaOriginalAntesImpressao = document.documentElement.getAttribute('data-bs-theme');
            document.documentElement.setAttribute('data-bs-theme', 'light');
            
            Chart.defaults.color = '#666';
            if(chartTec) { chartTec.data.datasets[0].borderColor = '#fff'; chartTec.update(); }
            if(chartDias) { chartDias.update(); }
            if(chartCompGeral) { chartCompGeral.update(); }
            if(chartCompTec) { chartCompTec.update(); }
        });

        window.addEventListener('afterprint', () => {
            if (temaOriginalAntesImpressao) {
                document.documentElement.setAttribute('data-bs-theme', temaOriginalAntesImpressao);
                Chart.defaults.color = temaOriginalAntesImpressao === 'dark' ? '#adb5bd' : '#666';
                if(chartTec) { chartTec.data.datasets[0].borderColor = temaOriginalAntesImpressao === 'dark' ? '#1e1e1e' : '#fff'; chartTec.update(); }
                if(chartDias) { chartDias.update(); }
                if(chartCompGeral) { chartCompGeral.update(); }
                if(chartCompTec) { chartCompTec.update(); }
            }
        });

        function imprimirComGraficos() {
            const secaoGraficos = document.getElementById('secao-graficos');
            const localOriginal = document.getElementById('local-graficos-original');
            const localImpressao = document.getElementById('local-graficos-impressao');
            
            localImpressao.appendChild(secaoGraficos);
            secaoGraficos.classList.remove('no-print');
            secaoGraficos.classList.add('page-break-before-print');
            
            if(chartTec) chartTec.resize();
            if(chartDias) chartDias.resize();

            window.print();
            
            setTimeout(() => {
                localOriginal.appendChild(secaoGraficos);
                secaoGraficos.classList.add('no-print');
                secaoGraficos.classList.remove('page-break-before-print');
                if(chartTec) chartTec.resize();
                if(chartDias) chartDias.resize();
            }, 500);
        }

        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            document.getElementById('btnThemeToggle').innerHTML = newTheme === 'dark' ? '☀️ Claro' : '🌙 Escuro';
            
            Chart.defaults.color = newTheme === 'dark' ? '#adb5bd' : '#666';
            if(chartTec) { chartTec.data.datasets[0].borderColor = newTheme === 'dark' ? '#1e1e1e' : '#fff'; chartTec.update(); }
            if(chartDias) { chartDias.update(); }
            if(chartCompGeral) { chartCompGeral.update(); }
            if(chartCompTec) { chartCompTec.update(); }
        }

        function editarTransporte(nome, valor) {
            document.getElementById('input_nome_transporte').value = nome;
            document.getElementById('input_valor_transporte').value = valor;
            document.getElementById('input_valor_transporte').focus();
        }

        function confirmDelete(btn) {
            if (btn.getAttribute('data-confirmed') === 'true') {
                saveScrollPosition();
                return true;
            } else {
                btn.setAttribute('data-confirmed', 'true');
                btn.innerHTML = '⚠️ Confirmar?';
                btn.classList.add('btn-warning');
                btn.classList.remove(btn.getAttribute('data-original-class'));
                setTimeout(() => {
                    btn.setAttribute('data-confirmed', 'false');
                    btn.innerHTML = btn.getAttribute('data-original-text');
                    btn.classList.remove('btn-warning');
                    btn.classList.add(btn.getAttribute('data-original-class'));
                }, 3000);
                return false;
            }
        }

        function saveScrollPosition() {
            sessionStorage.setItem('scrollPosition', window.scrollY);
        }

        window.addEventListener('load', function() {
            if (sessionStorage.getItem('scrollPosition') !== null) {
                window.scrollTo(0, sessionStorage.getItem('scrollPosition'));
                sessionStorage.removeItem('scrollPosition');
            }
        });
    </script>
</body>
</html>