
const express = require("express");
const bodyParser = require("body-parser");
const fs = require("fs");
const path = require("path");
const PDFDocument = require("pdfkit");

const app = express();
const PORT = 3000;

console.log("✅ server.js foi carregado");

// Middleware
app.use(bodyParser.json());
app.use(express.static("public"));

// Pasta dos PDFs
const PDF_DIR = path.join(__dirname, "pdfs");
if (!fs.existsSync(PDF_DIR)) {
    fs.mkdirSync(PDF_DIR);
    console.log("📁 Pasta pdfs criada");
}

// Rota de teste (MUITO IMPORTANTE)
app.get("/teste", (req, res) => {
    res.send("✅ Servidor Node.js está a funcionar!");
});


function titulo(doc, texto) {
    doc
        .fontSize(14)
        .fillColor("#4CAF50")
        .text(texto);
    doc.moveDown(0.3);
    doc
        .strokeColor("#4CAF50")
        .lineWidth(1)
        .moveTo(40, doc.y)
        .lineTo(555, doc.y)
        .stroke();
    doc.moveDown(0.6);
    doc.fillColor("black");
}

function campoSimples(doc, label, valor) {
    doc
        .fontSize(10)
        .fillColor("#555")
        .text(label);

    doc
        .fontSize(12)
        .fillColor("black")
        .text(valor || "-", { indent: 10 });

    doc.moveDown(0.6);
}


// Rota para gerar PDF
app.post("/gerar-pdf", (req, res) => {
    console.log("REQ.BODY =", req.body);
    console.log("📨 Pedido para gerar PDF recebido");

    const dados = req.body;
    console.log("DADOS RECEBIDOS:", dados);
    const nomePDF = `inscricao_${Date.now()}.pdf`;
    const caminho = path.join(PDF_DIR, nomePDF);

    const doc = new PDFDocument({ margin: 40 });
    doc.pipe(fs.createWriteStream(caminho));

   
// ===== TÍTULO =====
doc.fontSize(18).text("Ficha de Inscrição - Escola Profissional Val do Rio");
doc.moveDown(1);

// Função auxiliar para cabeçalhos
function cabecalho(texto) {
    doc
        .fontSize(14)
        .fillColor("#4CAF50")
        .text(texto);
    doc.moveDown(0.5);
    doc.fillColor("black").fontSize(12);
}

// Função auxiliar para campos
function campo(label, valor) {
    doc.text(`${label}: ${valor || "-"}`, { lineGap: 6 });
}

// ===== DADOS DO ALUNO =====
cabecalho("Dados do Aluno");

campo("Primeiro Nome", dados["Primeiro Nome"]);
campo("Último Nome", dados["Último Nome"]);
campo("Email", dados["Email"]);
campo("Data de Nascimento", dados["Data de Nascimento"]);
campo("NIF", dados["NIF"]);
campo("Acompanhante", dados["Acompanhante"]);
campo("Nacionalidade", dados["Nacionalidade"]);

doc.moveDown(1);

// ===== IDENTIDADE =====
cabecalho("Identidade");

campo("Tipo de Documento", dados["Tipo de Documento"]);
campo("BI-CC", dados["BI-CC"]);
campo("Data de validade do Documento", dados["Data de validade do Documento"]);

doc.moveDown(1);

// ===== MORADA =====
cabecalho("Morada");

campo("Rua", dados["Rua"]);
campo("Cidade", dados["Cidade"]);
campo("Concelho", dados["Concelho"]);
campo("Freguesia", dados["Freguesia"]);
campo("Código Postal", dados["Código Postal"]);

doc.moveDown(1);

// ===== ESCOLARIDADE =====
cabecalho("Escolaridade");

campo("Escola Anterior", dados["Escola Anterior"]);
campo("Último Ano de Frequência", dados["Último Ano de Frequência"]);
campo("Curso Pretendido", dados["Curso Pretendido"]);

doc.moveDown(1);

// ===== FILIAÇÃO / ENCARREGADO =====
cabecalho("Afiliação / Dados do Encarregado de Educação");

campo("Telemóvel do Pai", dados["Telemóvel do Pai"]);
campo("Telemóvel da Mãe", dados["Telemóvel da Mãe"]);
campo("Email do Pai", dados["Email do Pai"]);
campo("Email da Mãe", dados["Email da Mãe"]);

campo("Nome do Encarregado", dados["Nome do Encarregado"]);
campo("Telemóvel do Encarregado", dados["Telemóvel do Encarregado"]);
campo("Email do Encarregado", dados["Email do Encarregado"]);
campo("Telefone do Encarregado", dados["Telefone do Encarregado"]);

campo("Morada do Encarregado", dados["Morada do Encarregado"]);
campo("Localidade do Encarregado", dados["Localidade do Encarregado"]);
campo("Código Postal do Encarregado", dados["Código Postal do Encarregado"]);
campo("Habilitações do Encarregado", dados["Habilitações do Encarregado"]);
campo("Relação do Candidato", dados["Relação do Candidato"]);

doc.moveDown(1);

// ===== AUTORIZAÇÃO =====

cabecalho("Autorizações");

const autorizaDados = dados.autoriza_dados ? "Sim" : "Não";
campo("Autoriza partilhas de dados", autorizaDados);


    doc.end();

    res.json({ sucesso: true });
});

// Iniciar servidor
app.listen(PORT, () => {
    console.log(`🚀 Servidor ativo em http://localhost:${PORT}`);
});
