#!/usr/bin/env python3
"""
Create a detailed field mapping from the template PDF.
This generates a JSON mapping file with all field coordinates.
"""

import json
import sys
from pathlib import Path

try:
    import pdfplumber
except ImportError:
    print("Installing pdfplumber...")
    import subprocess
    subprocess.check_call([sys.executable, "-m", "pip", "install", "pdfplumber"])
    import pdfplumber

def create_field_mapping():
    """Extract and create a detailed field mapping"""
    
    pdf_path = Path(__file__).parent / "basePDF_image" / "MDDPE1406_Ficha_Candidatura_r0.pdf"
    
    if not pdf_path.exists():
        print(f"ERROR: PDF not found at {pdf_path}")
        return False
    
    mapping = {
        "pdf_file": str(pdf_path),
        "pages": []
    }
    
    with pdfplumber.open(pdf_path) as pdf:
        for page_num, page in enumerate(pdf.pages, 1):
            page_data = {
                "page": page_num,
                "width": page.width,
                "height": page.height,
                "fields": []
            }
            
            # Extract all text elements with positions
            for obj in page.chars:
                text = obj['text']
                x0 = obj['x0']
                y0 = obj['top']
                x1 = obj['x1']
                y1 = obj['bottom']
                size = obj['size']
                font = obj.get('fontname', 'Unknown')
                
                if text.strip():
                    page_data["fields"].append({
                        "text": text,
                        "x": round(x0, 2),
                        "y": round(y0, 2),
                        "width": round(x1 - x0, 2),
                        "height": round(y1 - y0, 2),
                        "size": round(size, 1),
                        "font": font
                    })
            
            mapping["pages"].append(page_data)
    
    # Save to JSON file
    output_file = Path(__file__).parent / "pdf_field_mapping.json"
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(mapping, f, indent=2, ensure_ascii=False)
    
    print(f"✓ Field mapping saved to: {output_file}\n")
    
    # Print summary
    print("="*80)
    print("PDF FIELD MAPPING SUMMARY")
    print("="*80)
    
    for page_data in mapping["pages"]:
        print(f"\nPAGE {page_data['page']}: {page_data['width']:.1f} x {page_data['height']:.1f} points")
        print("-" * 80)
        
        # Group fields by approximate Y position (lines)
        lines = {}
        for field in page_data["fields"]:
            y_key = round(field['y'] / 5) * 5  # Group by ~5pt intervals
            if y_key not in lines:
                lines[y_key] = []
            lines[y_key].append(field)
        
        # Print fields organized by line
        for y_pos in sorted(lines.keys()):
            fields_on_line = sorted(lines[y_pos], key=lambda f: f['x'])
            text_content = ''.join(f['text'] for f in fields_on_line)
            
            if text_content.strip():
                print(f"Y={y_pos:6.1f}  X={fields_on_line[0]['x']:6.2f}  Text: {text_content.strip()}")
    
    return True

if __name__ == "__main__":
    success = create_field_mapping()
    sys.exit(0 if success else 1)
