#!/usr/bin/env python3
"""
Automatic PDF field extractor using pdfplumber.
Extracts all text with bounding boxes and generates a structured mapping.
"""

import json
import sys
from pathlib import Path

try:
    import pdfplumber
except ImportError:
    print("Installing pdfplumber...")
    import subprocess
    subprocess.check_call([sys.executable, "-m", "pip", "install", "pdfplumber", "-q"])
    import pdfplumber


def extract_pdf_structure(pdf_path: str) -> dict:
    """Extract all text with bounding boxes from PDF."""
    
    with pdfplumber.open(pdf_path) as pdf:
        structure = {
            "pdf_file": str(pdf_path),
            "total_pages": len(pdf.pages),
            "pages": []
        }
        
        for page_num, page in enumerate(pdf.pages, 1):
            page_data = {
                "page": page_num,
                "width": round(page.width, 2),
                "height": round(page.height, 2),
                "text_elements": []
            }
            
            # Extract all text with positions
            for element in page.chars:
                page_data["text_elements"].append({
                    "text": element["text"],
                    "x0": round(element["x0"], 2),
                    "top": round(element["top"], 2),
                    "x1": round(element["x1"], 2),
                    "bottom": round(element["bottom"], 2),
                    "width": round(element["x1"] - element["x0"], 2),
                    "height": round(element["bottom"] - element["top"], 2),
                    "size": round(element["size"], 1),
                })
            
            structure["pages"].append(page_data)
        
        return structure


def group_text_into_fields(page_data: dict) -> list:
    """Group individual characters into text fields/labels."""
    
    if not page_data["text_elements"]:
        return []
    
    # Sort by Y position (top), then X position (left)
    chars = page_data["text_elements"]
    chars_sorted = sorted(chars, key=lambda c: (round(c["top"] / 3) * 3, c["x0"]))
    
    fields = []
    current_line = []
    current_y = None
    
    for char in chars_sorted:
        # Check if char is on a new line (Y changed by more than 2 points)
        if current_y is not None and abs(char["top"] - current_y) > 2:
            if current_line:
                # Save the accumulated line
                text = "".join(c["text"] for c in current_line)
                if text.strip():
                    x0 = min(c["x0"] for c in current_line)
                    y0 = min(c["top"] for c in current_line)
                    x1 = max(c["x1"] for c in current_line)
                    y1 = max(c["bottom"] for c in current_line)
                    
                    fields.append({
                        "text": text.strip(),
                        "x": round(x0, 2),
                        "y": round(y0, 2),
                        "width": round(x1 - x0, 2),
                        "height": round(y1 - y0, 2),
                    })
                current_line = []
        
        current_y = char["top"]
        current_line.append(char)
    
    # Don't forget the last line
    if current_line:
        text = "".join(c["text"] for c in current_line)
        if text.strip():
            x0 = min(c["x0"] for c in current_line)
            y0 = min(c["top"] for c in current_line)
            x1 = max(c["x1"] for c in current_line)
            y1 = max(c["bottom"] for c in current_line)
            
            fields.append({
                "text": text.strip(),
                "x": round(x0, 2),
                "y": round(y0, 2),
                "width": round(x1 - x0, 2),
                "height": round(y1 - y0, 2),
            })
    
    return fields


def main():
    pdf_path = Path(__file__).parent / "basePDF_image" / "MDDPE1406_Ficha_Candidatura_r0.pdf"
    
    if not pdf_path.exists():
        print(f"❌ PDF not found: {pdf_path}")
        return False
    
    print(f"📖 Extracting from: {pdf_path.name}")
    print()
    
    # Extract raw structure
    structure = extract_pdf_structure(str(pdf_path))
    
    # Save raw data
    raw_output = Path(__file__).parent / "pdf_raw_extraction.json"
    with open(raw_output, 'w', encoding='utf-8') as f:
        json.dump(structure, f, indent=2)
    print(f"✅ Raw extraction saved: pdf_raw_extraction.json ({len(structure['pages'])} pages)")
    
    # Generate field mapping (grouped text)
    field_mapping = {
        "pdf_file": str(pdf_path),
        "pages": []
    }
    
    for page_data in structure["pages"]:
        fields = group_text_into_fields(page_data)
        field_mapping["pages"].append({
            "page": page_data["page"],
            "width": page_data["width"],
            "height": page_data["height"],
            "fields": fields
        })
        
        print(f"\n📄 PAGE {page_data['page']} ({page_data['width']:.0f} × {page_data['height']:.0f} pts)")
        print("─" * 90)
        print(f"{'Y':<8} {'X':<8} {'Width':<8} {'Height':<8} {'Text':<60}")
        print("─" * 90)
        
        for field in fields:
            text = field["text"][:55] + "..." if len(field["text"]) > 55 else field["text"]
            print(f"{field['y']:<8.1f} {field['x']:<8.1f} {field['width']:<8.1f} {field['height']:<8.1f} {text:<60}")
    
    # Save field mapping
    field_output = Path(__file__).parent / "pdf_auto_mapping.json"
    with open(field_output, 'w', encoding='utf-8') as f:
        json.dump(field_mapping, f, indent=2)
    print(f"\n\n✅ Field mapping saved: pdf_auto_mapping.json")
    
    # Generate a simple coordinate config for PHP
    php_config = generate_php_config(field_mapping)
    php_output = Path(__file__).parent / "pdf_coordinates.php"
    with open(php_output, 'w', encoding='utf-8') as f:
        f.write(php_config)
    print(f"✅ PHP config generated: pdf_coordinates.php")
    
    print(f"\n💡 TIP: Edit pdf_auto_mapping.json to add 'form_field' names to each coordinate")
    print(f"    Then use pdf_auto_to_php.py to generate the final submit_fpdi.php")
    
    return True


def generate_php_config(field_mapping: dict) -> str:
    """Generate a PHP config file with coordinates."""
    
    php_code = """<?php
/**
 * Auto-extracted PDF field coordinates
 * Generated from: pdf_auto_mapping.json
 * 
 * This is a starting point. Add 'form_field' mapping for each coordinate.
 */

return [
"""
    
    for page in field_mapping["pages"]:
        page_num = page["page"]
        php_code += f"\n    'page_{page_num}' => [\n"
        
        for i, field in enumerate(page["fields"]):
            # Try to guess form field name from text
            label = field["text"]
            
            php_code += f"""        [
            'label' => '{label.replace("'", "\\'")}',
            'form_field' => '',  // TODO: Map to form field name
            'x' => {field['x']},
            'y' => {field['y']},
            'width' => {field['width']},
            'height' => {field['height']},
        ],
"""
        
        php_code += "    ],\n"
    
    php_code += """];
?>
"""
    return php_code


if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)
