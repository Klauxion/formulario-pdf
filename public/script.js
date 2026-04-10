document.getElementById("formulario").addEventListener("submit", async function (e) {
    e.preventDefault();

    const dados = {};
    new FormData(this).forEach((valor, campo) => {
        dados[campo] = valor;
    });

    const resposta = await fetch("/gerar-pdf", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(dados)
    });

    if (resposta.ok) {
        alert("✅ PDF criado e guardado na pasta local!");
        this.reset();
    } else {
        alert("❌ Erro ao gerar PDF");
    }
});
