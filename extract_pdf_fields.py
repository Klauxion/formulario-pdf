#!/usr/bin/env python3
"""
Extract field coordinates from the template PDF.
This script analyzes the PDF structure and provides a mapping of all text positions.
"""

import sys
from pathlib import Path

try:
    import PyPDF2
except ImportError:
    print("PyPDF2 not installed. Installing...")
    import subprocess
    subprocess.check_call([sys.executable, "-m", "pip", "install", "PyPDF2", "pdfplumber"])
    import PyPDF2

try:
    import pdfplumber
except ImportError:
    pass

def extract_with_pdfplumber(pdf_path):
    """Extract text and positions using pdfplumber (more accurate)"""
    try:
        import pdfplumber
        
        results = {}
        with pdfplumber.open(pdf_path) as pdf:
            for page_num, page in enumerate(pdf.pages, 1):
                print(f"\n{'='*70}")
                print(f"PAGE {page_num}")
                print(f"{'='*70}")
                print(f"Page Width: {page.width:.2f}mm, Height: {page.height:.2f}mm")
                print(f"(in points: {page.width:.2f} x {page.height:.2f})")
                print()
                
                results[f'page_{page_num}'] = []
                
                # Extract text with positions
                for obj in page.chars:
                    text = obj['text']
                    x0 = obj['x0']
                    y0 = obj['top']  # pdfplumber uses 'top' for y
                    size = obj['size']
                    
                    # Look for labels/field names (usually followed by input areas)
                    if text.strip() and len(text) > 1:
                        results[f'page_{page_num}'].append({
                            'text': text,
                            'x': round(x0, 2),
                            'y': round(y0, 2),
                            'size': round(size, 1)
                        })
                
                # Group nearby text (field labels)
                print("EXTRACTED TEXT POSITIONS (sorted by Y, then X):")
                print(f"{'Y Position':<12} {'X Position':<12} {'Size':<8} {'Text':<50}")
                print("-" * 85)
                
                sorted_chars = sorted(page.chars, key=lambda c: (c['top'], c['x0']))
                
                current_line = []
                current_y = None
                
                for obj in sorted_chars:
                    if current_y is None:
                        current_y = round(obj['top'], 1)
                    
                    # If y position changed significantly, print the line
                    if abs(obj['top'] - current_y) > 3 and current_line:
                        text = ''.join(c['text'] for c in current_line)
                        if text.strip():
                            x = min(c['x0'] for c in current_line)
                            print(f"{current_y:<12.1f} {x:<12.2f} {current_line[0]['size']:<8.1f} {text.strip():<50}")
                        current_line = []
                        current_y = round(obj['top'], 1)
                    
                    current_line.append(obj)
                
                # Print last line
                if current_line:
                    text = ''.join(c['text'] for c in current_line)
                    if text.strip():
                        x = min(c['x0'] for c in current_line)
                        print(f"{current_y:<12.1f} {x:<12.2f} {current_line[0]['size']:<8.1f} {text.strip():<50}")
        
        return results
    except Exception as e:
        print(f"pdfplumber error: {e}")
        return None

def extract_with_pypdf(pdf_path):
    """Fallback: Extract basic info with PyPDF2"""
    try:
        with open(pdf_path, 'rb') as f:
            reader = PyPDF2.PdfReader(f)
            print(f"PDF has {len(reader.pages)} pages")
            
            for page_num, page in enumerate(reader.pages, 1):
                print(f"\nPage {page_num}:")
                print(f"  MediaBox (dimensions): {page.mediabox}")
                
                if "/Annots" in page:
                    print(f"  Form fields found: {len(page['/Annots'])}")
                    for annot in page["/Annots"]:
                        obj = annot.get_object()
                        if obj["/Subtype"] == "/Widget":
                            rect = obj["/Rect"]
                            name = obj.get("/T", "Unknown")
                            print(f"    Field: {name} at {rect}")
    except Exception as e:
        print(f"PyPDF2 error: {e}")

def main():
    pdf_path = Path(__file__).parent / "basePDF_image" / "MDDPE1406_Ficha_Candidatura_r0.pdf"
    
    if not pdf_path.exists():
        print(f"ERROR: PDF not found at {pdf_path}")
        print("Please ensure the template PDF is in the basePDF_image folder")
        return False
    
    print(f"Analyzing PDF: {pdf_path}\n")
    
    # Try pdfplumber first (more detailed)
    result = extract_with_pdfplumber(pdf_path)
    
    if result is None:
        print("\nFalling back to PyPDF2...\n")
        extract_with_pypdf(pdf_path)
    
    print("\n" + "="*70)
    print("NEXT STEPS:")
    print("="*70)
    print("""
1. Review the text positions above
2. Use a PDF viewer (Adobe Reader, Preview, etc.) to verify exact positions
3. Create a mapping file with coordinates for each form field
4. The coordinates are in PDF points (1 point = 1/72 inch)

To get more precise coordinates in your PDF viewer:
- Use the inspect/measure tool in Adobe Reader
- Note the exact X and Y positions for the top-left corner of each input box
- Record the width and height of input areas
""")
    return True

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)
