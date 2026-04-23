#!/usr/bin/env python3
"""
Analyze template PDF and create form field to PDF position mapping.
Maps HTML form field names to their corresponding positions in the template PDF.
"""

import json
import sys
from pathlib import Path
from collections import defaultdict

try:
    import pdfplumber
except ImportError:
    import subprocess
    subprocess.check_call([sys.executable, "-m", "pip", "install", "pdfplumber"])
    import pdfplumber

def group_text_into_lines(chars, y_tolerance=5):
    """Group individual characters into text lines"""
    lines = defaultdict(list)
    
    for char in chars:
        y_key = round(char['top'] / y_tolerance) * y_tolerance
        lines[y_key].append(char)
    
    # Sort characters in each line by x position
    result = []
    for y_pos in sorted(lines.keys()):
        chars_on_line = sorted(lines[y_pos], key=lambda c: c['x0'])
        text = ''.join(c['text'] for c in chars_on_line)
        x_start = chars_on_line[0]['x0']
        y_pos_actual = chars_on_line[0]['top']
        
        result.append({
            'text': text.strip(),
            'x': round(x_start, 2),
            'y': round(y_pos_actual, 2),
            'y_bottom': round(chars_on_line[0]['bottom'], 2)
        })
    
    return result

def analyze_pdf_layout():
    """Analyze the PDF to understand the layout and field positions"""
    
    pdf_path = Path(__file__).parent / "basePDF_image" / "MDDPE1406_Ficha_Candidatura_r0.pdf"
    
    if not pdf_path.exists():
        print(f"ERROR: PDF not found at {pdf_path}")
        return False
    
    print("Analyzing PDF layout for field positions...\n")
    
    with pdfplumber.open(pdf_path) as pdf:
        # Analyze first page
        page1 = pdf.pages[0]
        lines1 = group_text_into_lines(page1.chars)
        
        # Analyze second page
        page2 = pdf.pages[1]
        lines2 = group_text_into_lines(page2.chars)
    
    print("="*90)
    print("PAGE 1 - TEXT ELEMENTS (for field placement reference)")
    print("="*90)
    print(f"{'Y Position':<12} {'X Position':<12} {'Text':<70}")
    print("-" * 90)
    
    for line in lines1:
        if line['text']:
            print(f"{line['y']:<12.1f} {line['x']:<12.1f} {line['text']:<70}")
    
    print("\n" + "="*90)
    print("PAGE 2 - TEXT ELEMENTS (for field placement reference)")
    print("="*90)
    print(f"{'Y Position':<12} {'X Position':<12} {'Text':<70}")
    print("-" * 90)
    
    for line in lines2:
        if line['text']:
            print(f"{line['y']:<12.1f} {line['x']:<12.1f} {line['text']:<70}")
    
    # Create mapping configuration
    mapping_config = {
        "description": "Form field to PDF coordinate mapping",
        "notes": "Coordinates are in PDF points (1 point = 1/72 inch). X=left, Y=top (from top of page)",
        "pages": {
            "1": {
                "width": 594.96,
                "height": 842.04,
                "template_pdf": "MDDPE1406_Ficha_Candidatura_r0.pdf",
                "fields": {
                    "header_section": {
                        "description": "Top section with Logo, Ano Letivo, Curso, Candidatura",
                        "logo": {"x": 39.72, "y": 30, "width": 50, "height": 40},
                        "title": {"x": 150, "y": 100, "width": 400, "height": 30, "font_size": 16},
                    },
                    "identification_section": {
                        "description": "Identificação do Candidato section - Page 1",
                        "section_title_y": 140,
                        "nome": {"label_y": 160, "input_x": 39.72, "input_y": 165, "input_width": 515.24, "input_height": 15},
                        "data_nasc": {"label_y": 185, "input_x": 39.72, "input_y": 190, "input_width": 250, "input_height": 15},
                        "nacionalidade": {"label_y": 185, "input_x": 297, "input_y": 190, "input_width": 257.24, "input_height": 15},
                        "naturalidade": {"label_y": 210, "input_x": 39.72, "input_y": 215, "input_width": 250, "input_height": 15},
                        "concelho_freguesia": {"label_y": 210, "input_x": 297, "input_y": 215, "input_width": 257.24, "input_height": 15},
                        "bi_cc": {"label_y": 235, "input_x": 39.72, "input_y": 240, "input_width": 250, "input_height": 15},
                        "validade": {"label_y": 235, "input_x": 297, "input_y": 240, "input_width": 257.24, "input_height": 15},
                        "nif": {"label_y": 260, "input_x": 39.72, "input_y": 265, "input_width": 515.24, "input_height": 15},
                        "morada": {"label_y": 285, "input_x": 39.72, "input_y": 290, "input_width": 515.24, "input_height": 15},
                        "localidade": {"label_y": 310, "input_x": 39.72, "input_y": 315, "input_width": 250, "input_height": 15},
                        "codigo_postal": {"label_y": 310, "input_x": 297, "input_y": 315, "input_width": 257.24, "input_height": 15},
                        "telefone": {"label_y": 335, "input_x": 39.72, "input_y": 340, "input_width": 250, "input_height": 15},
                        "telemovel": {"label_y": 335, "input_x": 297, "input_y": 340, "input_width": 257.24, "input_height": 15},
                        "email": {"label_y": 360, "input_x": 39.72, "input_y": 365, "input_width": 250, "input_height": 15},
                        "situacao_academica": {"label_y": 360, "input_x": 297, "input_y": 365, "input_width": 257.24, "input_height": 15},
                    },
                    "education_section": {
                        "description": "Curriculum escolar section",
                        "section_title_y": 390,
                        "subsection_text_y": 410,
                        "ciclo_1": {"label_y": 430, "input_x": 39.72, "input_y": 435, "input_width": 515.24, "input_height": 15},
                        "ciclo_2": {"label_y": 455, "input_x": 39.72, "input_y": 460, "input_width": 515.24, "input_height": 15},
                        "ciclo_3": {"label_y": 480, "input_x": 39.72, "input_y": 485, "input_width": 515.24, "input_height": 15},
                        "secundario": {"label_y": 505, "input_x": 39.72, "input_y": 510, "input_width": 515.24, "input_height": 15},
                    }
                }
            },
            "2": {
                "width": 594.96,
                "height": 842.04,
                "template_pdf": "MDDPE1406_Ficha_Candidatura_r0.pdf",
                "fields": {
                    "filiation_section": {
                        "description": "Filiação e Encarregado de Educação - Page 2",
                        "section_title_y": 50,
                        "pai_nome": {"label_y": 75, "input_x": 39.72, "input_y": 80, "input_width": 515.24, "input_height": 15},
                        "pai_telemovel": {"label_y": 100, "input_x": 39.72, "input_y": 105, "input_width": 250, "input_height": 15},
                        "pai_email": {"label_y": 100, "input_x": 297, "input_y": 105, "input_width": 257.24, "input_height": 15},
                        "mae_nome": {"label_y": 130, "input_x": 39.72, "input_y": 135, "input_width": 515.24, "input_height": 15},
                        "mae_telemovel": {"label_y": 155, "input_x": 39.72, "input_y": 160, "input_width": 250, "input_height": 15},
                        "mae_email": {"label_y": 155, "input_x": 297, "input_y": 160, "input_width": 257.24, "input_height": 15},
                    },
                    "guardian_section": {
                        "description": "Encarregado de Educação section",
                        "section_title_y": 190,
                        "subsection_text_y": 210,
                        "ee_nome": {"label_y": 235, "input_x": 39.72, "input_y": 240, "input_width": 170, "input_height": 15},
                        "ee_morada": {"label_y": 235, "input_x": 217, "input_y": 240, "input_width": 170, "input_height": 15},
                        "ee_localidade": {"label_y": 235, "input_x": 395, "input_y": 240, "input_width": 159.96, "input_height": 15},
                        "ee_cp": {"label_y": 260, "input_x": 39.72, "input_y": 265, "input_width": 170, "input_height": 15},
                        "ee_telefone": {"label_y": 260, "input_x": 217, "input_y": 265, "input_width": 170, "input_height": 15},
                        "ee_telemovel": {"label_y": 260, "input_x": 395, "input_y": 265, "input_width": 159.96, "input_height": 15},
                        "ee_email": {"label_y": 285, "input_x": 39.72, "input_y": 290, "input_width": 515.24, "input_height": 15},
                        "ee_habilitacoes": {"label_y": 310, "input_x": 39.72, "input_y": 315, "input_width": 250, "input_height": 15},
                        "ee_relacao": {"label_y": 310, "input_x": 297, "input_y": 315, "input_width": 257.24, "input_height": 15},
                    },
                    "authorization_section": {
                        "description": "Data authorization checkbox",
                        "checkbox_x": 480,
                        "checkbox_y": 340,
                        "checkbox_size": 6
                    },
                    "footer_section": {
                        "description": "Footer with document reference and date",
                        "reference_y": 790,
                        "date_y": 810
                    }
                }
            }
        }
    }
    
    # Save the mapping
    mapping_file = Path(__file__).parent / "pdf_coordinate_mapping.json"
    with open(mapping_file, 'w', encoding='utf-8') as f:
        json.dump(mapping_config, f, indent=2, ensure_ascii=False)
    
    print("\n" + "="*90)
    print(f"✓ Coordinate mapping saved to: {mapping_file}")
    print("="*90)
    print("\nThis mapping will be used to create the FPDI-based PHP PDF generator.")
    
    return True

if __name__ == "__main__":
    success = analyze_pdf_layout()
    sys.exit(0 if success else 1)
