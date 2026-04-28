<?php
// Front controller - handle AJAX form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    require_once __DIR__ . '/../app/submit.php';
    exit;
}

// Handle asset requests
if (isset($_GET['asset'])) {
    $asset = $_GET['asset'];
    $allowedAssets = ['style.css', 'script.js', 'vr_logo_2026.png'];
    $basePDFAssets = ['MDDPE1406_Ficha_Candidatura_r0_fixed.pdf'];

    if (in_array($asset, $allowedAssets)) {
        $filePath = __DIR__ . '/../assets/' . $asset;
        if (file_exists($filePath)) {
            $mime = match(pathinfo($asset, PATHINFO_EXTENSION)) {
                'css' => 'text/css',
                'js' => 'application/javascript',
                'png' => 'image/png',
                default => 'application/octet-stream'
            };
            header('Content-Type: ' . $mime);
            readfile($filePath);
            exit;
        }
    } elseif (in_array($asset, $basePDFAssets)) {
        $filePath = __DIR__ . '/../assets/basePDF_image/' . $asset;
        if (file_exists($filePath)) {
            header('Content-Type: application/pdf');
            readfile($filePath);
            exit;
        }
    }
    http_response_code(404);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Ficha de InscriÃ§Ã£o - Val do Rio</title>
  <!-- CSS -->
  <link rel="stylesheet" href="?asset=style.css">
</head>

<body>
  <div id="loading-overlay" class="loading-overlay hidden" aria-hidden="true">
    <div class="loading-spinner"></div>
    <p>A enviar formulÃ¡rio...</p>
  </div>
  <div id="feedback-window" class="feedback-window hidden" role="dialog" aria-modal="true" aria-live="polite">
    <div id="feedback-card" class="feedback-card">
      <div id="feedback-title" class="feedback-title"></div>
      <div id="feedback-message" class="feedback-message"></div>
      <button id="feedback-ok" class="feedback-ok" type="button">OK</button>
    </div>
  </div>

  <header class="topo">
    <div>
      <h1>Ficha de InscriÃ§Ã£o</h1>
      <p>Escola Profissional Val do Rio</p>
    </div>
    <img src="?asset=vr_logo_2026.png" alt="LogÃ³tipo Val do Rio">
  </header>

  <form id="formulario" action="" method="POST">
    <div id="test-toolbar" class="test-toolbar hidden">
      <button id="fillBtn" type="button">Preencher tudo</button>
      <button id="fillSubmitBtn" type="button" class="secondary">Preencher e submeter</button>
    </div>

    <!-- DADOS DO ALUNO -->
    <div class="card">
      <h2>Dados do Aluno</h2>

      <div class="grid">
        <div class="field">
          <label for="candidatura_num">Candidatura n.Âº</label>
          <input id="candidatura_num" name="Candidatura n.Âº">
        </div>

        <div class="field">
          <label for="primeiro_nome">Primeiro Nome</label>
          <input id="primeiro_nome" name="Primeiro Nome" required>
        </div>

        <div class="field">
          <label for="ultimo_nome">Ãšltimo Nome</label>
          <input id="ultimo_nome" name="Ãšltimo Nome" required>
        </div>

        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="Email" type="email">
        </div>

        <div class="field">
          <label for="data_nasc">Data de Nascimento</label>
          <input id="data_nasc" name="Data de Nascimento">
        </div>

        <div class="field">
          <label for="nif">NIF</label>
          <input id="nif" name="NIF">
        </div>

        <div class="field">
          <label for="naturalidade">Naturalidade(PaÃ­s)</label>
          <input id="naturalidade" name="Naturalidade(PaÃ­s)">
        </div>

        <div class="field">
          <label for="nacionalidade">Nacionalidade</label>
          <input id="nacionalidade" name="Nacionalidade">
        </div>

        <div class="field">
          <label for="concelho_freguesia">Concelho/Freguesia Nasc.</label>
          <input id="concelho_freguesia" name="Freguesia">
        </div>
      </div>
    </div>

    <!-- IDENTIDADE -->
    <div class="card">
      <h2>Identidade</h2>

      <div class="grid">
        <div class="field">
          <label for="bi_cc">CC|Outro</label>
          <input id="bi_cc" name="BI-CC" required>
        </div>

        <div class="field">
          <label for="validade_doc">Data de validade do Documento</label>
          <input id="validade_doc" name="Data de validade do Documento">
        </div>
      </div>
    </div>

    <!-- MORADA -->
    <div class="card">
      <h2>Morada</h2>

      <div class="grid">
        <div class="field">
          <label for="rua">Rua</label>
          <input id="rua" name="Rua">
        </div>

        <div class="field">
          <label for="cidade">Cidade</label>
          <input id="cidade" name="Cidade">
        </div>

        <div class="field">
          <label for="concelho">Concelho</label>
          <input id="concelho" name="Concelho">
        </div>

        <div class="field">
          <label for="freguesia">Freguesia</label>
          <input id="freguesia" name="Freguesia">
        </div>

        <div class="field">
          <label for="cod_postal">CÃ³digo Postal</label>
          <input id="cod_postal" name="CÃ³digo Postal">
        </div>
      </div>
    </div>

    <!-- ESCOLARIDADE -->
    <div class="card">
      <h2>Escolaridade</h2>

      <div class="grid">
        <div class="field">
          <label for="escola_anterior">Escola Anterior</label>
          <input id="escola_anterior" name="Escola Anterior">
        </div>

        <div class="field">
          <label for="ultimo_ano">Ãšltimo Ano de FrequÃªncia</label>
          <select id="ultimo_ano" name="Ãšltimo Ano de FrequÃªncia" required>
            <option value="">-- Ãšltimo Ano de FrequÃªncia --</option>
            <option>6Âº Ano</option>
            <option>7Âº Ano</option>
            <option>8Âº Ano</option>
            <option>9Âº Ano</option>
            <option>10Âº Ano</option>
            <option>11Âº Ano</option>
            <option>12Âº Ano</option>
          </select>
        </div>

        <div class="field">
          <label for="curso_pretendido">Curso Pretendido</label>
          <select id="curso_pretendido" name="Curso Pretendido" required>
            <option value="">-- Curso Pretendido --</option>
            <option value="Tecnico de Acao Educativa">TÃ©cnico de AÃ§Ã£o Educativa</option>
            <option value="Tecnico de Desenho Digital 3D">TÃ©cnico de Desenho Digital 3D</option>
            <option value="Tecnico de Eletronica e Telecomunicacoes">TÃ©cnico de EletrÃ³nica e TelecomunicaÃ§Ãµes</option>
            <option value="Tecnico de Apoio Psicossocial">TÃ©cnico de Apoio Psicossocial</option>
            <option value="Tecnico de Video">TÃ©cnico de VÃ­deo</option>
            <option value="Tecnico de Design e Comunicacao Grafica">TÃ©cnico de Design e ComunicaÃ§Ã£o GrÃ¡fica</option>
            <option value="Tecnico de Multimedia">TÃ©cnico de MultimÃ©dia</option>
            <option value="Tecnico de Auxiliar de Saude">TÃ©cnico de Auxiliar de SaÃºde</option>
            <option value="Tecnico de Gestao Equipamentos Informaticos">TÃ©cnico de GestÃ£o de Equipamentos InformÃ¡ticos</option>
            <option value="Tecnico Assistente Dentario">TÃ©cnico Assistente DentÃ¡rio</option>
            <option value="Tecnico Auxiliar de Farmacia">TÃ©cnico Auxiliar de FarmÃ¡cia</option>
            <option value="Workshop para Novos Alunos">Workshop para Novos Alunos</option>
            <option value="x Dispositivos moveis e gestao Cloud">x Dispositivos mÃ³veis e gestÃ£o Cloud</option>
            <option value="x Informatica - Nocoes Basicas">x InformÃ¡tica - NoÃ§Ãµes BÃ¡sicas</option>
            <option value="x Criacao de Sites Web - UFCD0768">x CriaÃ§Ã£o de Sites Web - UFCD0768</option>
            <option value="x Processamento de Texto - UFCD0755">x Processamento de Texto - UFCD0755</option>
            <option value="x Folhas de Calculo - UFCD0778">x Folhas de CÃ¡lculo - UFCD0778</option>
            <option value="x IT Essentials - CISCO">x IT Essentials - CISCO</option>
            <option value="x Projecto e Instalacao ITED - Actualizacao">x Projecto e InstalaÃ§Ã£o ITED â€“ ActualizaÃ§Ã£o</option>
            <option value="x Instalador de ITED">x Instalador de ITED</option>
            <option value="Tecnico de Informatica e Sistemas">TÃ©cnico de InformÃ¡tica e Sistemas</option>
            <option value="Tecnico de Audiovisuais">TÃ©cnico de Audiovisuais</option>
            <option value="Tecnico de Eletronica e Comunicacoes">TÃ©cnico de EletrÃ³nica e ComunicaÃ§Ãµes</option>
            <option value="Tecnico de Sistemas de Computacao e Redes">TÃ©cnico de Sistemas de ComputaÃ§Ã£o e Redes</option>
            <option value="Tecnico de Desenvolvimento de Software">TÃ©cnico de Desenvolvimento de Software</option>
          </select>
        </div>
      </div>
    </div>

    <!-- ENCARREGADO -->
    <div class="card">
      <h2>AfiliaÃ§Ã£o/Dados do Encarregado de EducaÃ§Ã£o</h2>

      <div class="grid">
        <div class="field">
          <label for="tel_pai">TelemÃ³vel do Pai</label>
          <input id="tel_pai" name="TelemÃ³vel do Pai">
        </div>

        <div class="field">
          <label for="tel_mae">TelemÃ³vel da MÃ£e</label>
          <input id="tel_mae" name="TelemÃ³vel da MÃ£e">
        </div>

        <div class="field">
          <label for="email_pai">Email do Pai</label>
          <input id="email_pai" name="Email do Pai">
        </div>

        <div class="field">
          <label for="email_mae">Email da MÃ£e</label>
          <input id="email_mae" name="Email da MÃ£e">
        </div>

        <div class="field">
          <label for="nome_enc">Nome do Encarregado</label>
          <input id="nome_enc" name="Nome do Encarregado">
        </div>

        <div class="field">
          <label for="tel_enc">TelemÃ³vel do Encarregado</label>
          <input id="tel_enc" name="TelemÃ³vel do Encarregado">
        </div>

        <div class="field">
          <label for="email_enc">Email do Encarregado</label>
          <input id="email_enc" name="Email do Encarregado">
        </div>

        <div class="field">
          <label for="telefone_enc">Telefone do Encarregado</label>
          <input id="telefone_enc" name="Telefone do Encarregado">
        </div>

        <div class="field">
          <label for="morada_enc">Morada do Encarregado</label>
          <input id="morada_enc" name="Morada do Encarregado">
        </div>

        <div class="field">
          <label for="localidade_enc">Localidade do Encarregado</label>
          <input id="localidade_enc" name="Localidade do Encarregado">
        </div>

        <div class="field">
          <label for="cp_enc">CÃ³digo Postal do Encarregado</label>
          <input id="cp_enc" name="CÃ³digo Postal do Encarregado">
        </div>

        <div class="field">
          <label for="hab_enc">HabilitaÃ§Ãµes do Encarregado</label>
          <input id="hab_enc" name="HabilitaÃ§Ãµes do Encarregado">
        </div>

        <div class="field">
          <label for="relacao">RelaÃ§Ã£o do Candidato</label>
          <select id="relacao" name="RelaÃ§Ã£o do Candidato" required>
            <option value="">-- RelaÃ§Ã£o do Candidato --</option>
            <option>Pai</option>
            <option>MÃ£e</option>
            <option>Tio</option>
            <option>AvÃ´</option>
            <option>Padrinho</option>
            <option>IrmÃ£o</option>
            <option>Tutor</option>
            <option>Outro</option>
          </select>
        </div>
      </div>
    </div>

    <!-- AUTORIZAÃ‡ÃƒO -->
    <div class="card">
      <h2>AutorizaÃ§Ãµes</h2>

      <!-- garante que aparece sempre Sim/NÃ£o no PDF -->
      <input type="hidden" name="autoriza_dados" value="NÃ£o">

      <label class="checkline">
        <input type="checkbox" name="autoriza_dados" value="Sim">
        Autorizo o tratamento dos meus dados pessoais
      </label>
    </div>

    <!-- BOTÃ•ES -->
    <div class="actions">
      <button type="submit">Enviar formulÃ¡rio</button>
    </div>
  </form>

  <script src="?asset=script.js"></script>
</body>
</html>


