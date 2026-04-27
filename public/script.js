const $ = (id) => document.getElementById(id);

const formElement = $("formulario");
const submitButton = formElement?.querySelector("button[type='submit']");
const emailInput = $("email");
const loadingOverlay = $("loading-overlay");
const feedbackWindow = $("feedback-window");
const feedbackCard = $("feedback-card");
const feedbackTitle = $("feedback-title");
const feedbackMessage = $("feedback-message");
const feedbackOk = $("feedback-ok");
const testToolbar = $("test-toolbar");
const fillBtn = $("fillBtn");
const fillSubmitBtn = $("fillSubmitBtn");

const TEST_SAMPLE_DATA = {
  primeiro_nome: "Ana",
  ultimo_nome: "Silva",
  email: "keenansb07@gmail.com",
  data_nasc: "2008-03-15",
  nif: "245678901",
  acompanhante: "Maria Silva",
  nacionalidade: "Portuguesa",
  tipo_doc: "CC",
  bi_cc: "12345678",
  validade_doc: "2030-12-31",
  rua: "Rua das Flores 123",
  cidade: "Porto",
  concelho: "Porto",
  freguesia: "Cedofeita",
  cod_postal: "4000-123",
  escola_anterior: "Escola Basica do Centro",
  ultimo_ano: "9º Ano",
  curso_pretendido: "Tecnico de Multimedia",
  tel_pai: "912345678",
  tel_mae: "913456789",
  email_pai: "pai.teste@example.com",
  email_mae: "mae.teste@example.com",
  nome_enc: "Carlos Silva",
  tel_enc: "914567890",
  email_enc: "encarregado.teste@example.com",
  telefone_enc: "225551234",
  morada_enc: "Rua das Acacias 45",
  localidade_enc: "Vila Nova de Gaia",
  cp_enc: "4400-210",
  hab_enc: "12º Ano",
  relacao: "Pai",
};

function hideFeedback() {
  if (!feedbackWindow) return;
  feedbackWindow.classList.add("hidden");
  document.body.style.overflow = "";
}

function showFeedback(type, title, message) {
  if (!feedbackWindow || !feedbackCard || !feedbackTitle || !feedbackMessage) return;
  feedbackWindow.className = "feedback-window";
  feedbackCard.className = `feedback-card feedback-${type}`;
  feedbackTitle.textContent = title;
  feedbackMessage.textContent = message;
  document.body.style.overflow = "hidden";
}

function setLoading(isLoading) {
  if (!loadingOverlay || !submitButton) return;
  loadingOverlay.classList.toggle("hidden", !isLoading);
  submitButton.disabled = isLoading;
}

function isValidEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

function getFormDataObject(form) {
  const data = {};
  new FormData(form).forEach((value, key) => {
    data[key] = value;
  });
  return data;
}

async function postJson(url, payload) {
  const response = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });

  const rawText = await response.text();
  let result;
  try {
    result = JSON.parse(rawText);
  } catch {
    throw new Error("Resposta invalida do servidor: " + rawText);
  }

  if (!response.ok || result.ok === false) {
    const details = result.email_error ? " Detalhe SMTP: " + result.email_error : "";
    throw new Error((result.message || "Falha ao guardar os dados.") + details);
  }

  return result;
}

function fillFormWithSample() {
  Object.entries(TEST_SAMPLE_DATA).forEach(([id, value]) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = value;
    el.dispatchEvent(new Event("input", { bubbles: true }));
    el.dispatchEvent(new Event("change", { bubbles: true }));
  });

  const consent = document.querySelector('input[type="checkbox"][name="autoriza_dados"]');
  if (consent) consent.checked = true;
}

feedbackOk?.addEventListener("click", hideFeedback);

const isTestMode = new URLSearchParams(window.location.search).get("teste") === "1";
if (isTestMode) {
  testToolbar?.classList.remove("hidden");
}

fillBtn?.addEventListener("click", () => fillFormWithSample());
fillSubmitBtn?.addEventListener("click", () => {
  fillFormWithSample();
  formElement?.requestSubmit();
});

formElement?.addEventListener("submit", async function (e) {
  e.preventDefault();

  if (window.location.protocol === "file:") {
    showFeedback("error", "Erro", "Abre o formulario via servidor PHP (http://127.0.0.1:8080), nao via ficheiro local.");
    return;
  }

  const formData = getFormDataObject(this);

  const emailValue = (emailInput?.value || "").trim();
  if (!isValidEmail(emailValue)) {
    showFeedback("error", "Failed", "Email is not valid. Please enter a valid email before sending.");
    return;
  }

  setLoading(true);

  try {
    const result = await postJson("submit.php", { form_data: formData });

    if (result.email_sent === false) {
      const details = result.email_error ? " Detalhe: " + result.email_error : "";
      showFeedback("error", "Erro", "PDF gerado no servidor, mas o envio de email falhou." + details);
      setLoading(false);
      return;
    }

    showFeedback("success", "Success!", "PDF gerado e enviado por email.");
  } catch (error) {
    const backendMessage = (error.message || "").trim();
    const normalized = backendMessage.toLowerCase();
    const invalidCandidateEmail = normalized.includes("email do candidato invalido")
      || normalized.includes("email is not valid");

    if (invalidCandidateEmail) {
      showFeedback("error", "Failed", "Email is not valid. Please enter a valid email before sending.");
    } else {
      showFeedback("error", "Erro", "Nao foi possivel enviar os dados. " + (backendMessage || "Erro desconhecido."));
    }
  } finally {
    setLoading(false);
  }
});
