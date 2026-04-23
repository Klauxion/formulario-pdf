#!/usr/bin/env python3
import json
import sys
from pathlib import Path

from pypdf import PdfReader, PdfWriter


def norm(value: object) -> str:
    if value is None:
        return ""
    return str(value).strip()


def main() -> int:
    if len(sys.argv) != 4:
        return 2

    template_path = Path(sys.argv[1])
    input_json_path = Path(sys.argv[2])
    output_pdf_path = Path(sys.argv[3])

    if not template_path.is_file() or not input_json_path.is_file():
        return 3

    try:
        payload = json.loads(input_json_path.read_text(encoding="utf-8-sig"))
    except Exception:
        return 4

    if not isinstance(payload, dict):
        return 5

    first_name = norm(payload.get("Primeiro Nome"))
    last_name = norm(payload.get("Último Nome"))
    full_name = f"{first_name} {last_name}".strip()

    consent = norm(payload.get("autoriza_dados")).lower()
    consent_value = "/Sim" if consent == "sim" else "/Off"

    fields = {
        "NoCandidato": norm(payload.get("Candidatura n.º")),
        "Curso": norm(payload.get("Curso Pretendido")),
        "Nacionalidade": norm(payload.get("Nacionalidade")),
        "Doc_Identifica": norm(payload.get("BI-CC")),
        "NIF": norm(payload.get("NIF")),
        "Nome": full_name,
        "Outro_Val_Doc": norm(payload.get("Data de validade do Documento")),
        "Ano1": "2025",
        "Ano2": "2026",
        "Morada_Rua": norm(payload.get("Rua")),
        "Morada_CPostal": norm(payload.get("Código Postal")),
        "Telemovel": norm(payload.get("Telemóvel da Mãe")),
        "email": norm(payload.get("Email")),
        "Situa_Academica": norm(payload.get("Último Ano de Frequência")),
        "Morada_Localidade": norm(payload.get("Cidade")),
        "Telefone": norm(payload.get("Telemóvel do Pai")),
        "CPostal_Localidade": norm(payload.get("Código Postal")),
        "EE_Relaciona_Candidato": norm(payload.get("Relação do Candidato")),
        "Nome_EE": norm(payload.get("Nome do Encarregado")),
        "Morada_Rua_EE": norm(payload.get("Morada do Encarregado")),
        "Morada_Localidade_EE": norm(payload.get("Localidade do Encarregado")),
        "Morada_CPostal_EE": norm(payload.get("Código Postal do Encarregado")),
        "CPostal_Localidade_EE": norm(payload.get("Código Postal do Encarregado")),
        "Telefone_EE": norm(payload.get("Telefone do Encarregado")),
        "email_EE": norm(payload.get("Email do Encarregado")),
        "Telemovel_EE": norm(payload.get("Telemóvel do Encarregado")),
        "Nacionalidade_PAIS": norm(payload.get("Naturalidade(País)")),
        "SIM_TDP": consent_value,
        "Nacionalidade_FREG": norm(payload.get("Freguesia")),
        "DATA_Nascimento": norm(payload.get("Data de Nascimento")),
        "DATA_Validade": norm(payload.get("Data de validade do Documento")),
        "Hab_Acad_EE": norm(payload.get("Habilitações do Encarregado")),
        "Nome_Pai": norm(payload.get("Nome do Encarregado")),
        "Telemovel_Pai": norm(payload.get("Telemóvel do Pai")),
        "email_Pai": norm(payload.get("Email do Pai")),
        "Nome_Mae": "-",
        "Telemovel_Mae": norm(payload.get("Telemóvel da Mãe")),
        "email_Mae": norm(payload.get("Email da Mãe")),
        "Escola_Secund": "-",
        "Escola_3EB": "-",
        "Escola_2EB": "-",
        "Escola_1EB": norm(payload.get("Escola Anterior")),
    }

    reader = PdfReader(str(template_path))
    writer = PdfWriter()
    writer.clone_document_from_reader(reader)
    writer.set_need_appearances_writer(True)

    for page in writer.pages:
        writer.update_page_form_field_values(page, fields, auto_regenerate=True)

    output_pdf_path.parent.mkdir(parents=True, exist_ok=True)
    with output_pdf_path.open("wb") as handle:
        writer.write(handle)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())

