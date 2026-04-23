# PDF Field Coordinate Calibration Guide

## Overview
This guide will help you measure and adjust the exact coordinates for each form field in the template PDF to ensure perfect alignment when overlaying form data.

## Understanding PDF Coordinates
- **X-axis**: Horizontal position from left edge (0 = left margin)
- **Y-axis**: Vertical position from top edge (0 = top of page)
- **Units**: PDF points (1 point = 1/72 inch ≈ 0.352 mm)
- **Page size**: A4 = 594.96 × 842.04 points (210 × 297 mm)

## How to Measure Coordinates

### Using Adobe Reader (Recommended)
1. Open the template PDF: `basePDF_image/MDDPE1406_Ficha_Candidatura_r0.pdf`
2. Enable the measuring tool: **Tools → Measure**
3. For each form field:
   - Click the top-left corner of the input box
   - Note the X,Y coordinates shown in the tool
   - These are your exact overlay coordinates

### Using PDF Viewer Measure Tool
- Many PDF viewers show coordinates when you hover over the document
- Position your cursor at the exact location where text should start
- Record the X and Y coordinates displayed

### Manual Measurement Method
1. Print the template PDF at 100% scale (no scaling)
2. Use a ruler to measure distances from top-left corner
3. Convert to PDF points using: `mm × 2.834646 = PDF points`

## Field Coordinate Mapping

### PAGE 1: Candidate Identification

| Field Name | Form Field | X Coord | Y Coord | Width | Notes |
|---|---|---|---|---|---|
| Full Name | Primeiro Nome + Último Nome | TBD | TBD | TBD | Horizontal center of input box |
| Date of Birth | Data de Nascimento | TBD | TBD | TBD | Left side of two-column layout |
| Nationality | Nacionalidade | TBD | TBD | TBD | Right side of two-column layout |
| Country of Origin | Naturalidade(País) | TBD | TBD | TBD | Left side |
| Municipality/Parish | Concelho/Freguesia | TBD | TBD | TBD | Right side |
| ID Document | BI-CC | TBD | TBD | TBD | Left side |
| Document Validity | Data de validade | TBD | TBD | TBD | Right side |
| NIF | NIF | TBD | TBD | TBD | Full width |
| Address | Rua | TBD | TBD | TBD | Full width |
| City | Cidade | TBD | TBD | TBD | Left side |
| Postal Code | Código Postal | TBD | TBD | TBD | Right side |
| Phone | Telemóvel do Pai | TBD | TBD | TBD | Left side |
| Mobile | Telemóvel da Mãe | TBD | TBD | TBD | Right side |
| Email | Email | TBD | TBD | TBD | Left side |
| Academic Status | Último Ano de Frequência | TBD | TBD | TBD | Right side |
| Education Level 1 | Escola Anterior | TBD | TBD | TBD | Full width |
| Education Level 2 | - | TBD | TBD | TBD | Full width |
| Education Level 3 | - | TBD | TBD | TBD | Full width |
| Secondary | - | TBD | TBD | TBD | Full width |

### PAGE 2: Guardians and Authorization

| Field Name | Form Field | X Coord | Y Coord | Width | Notes |
|---|---|---|---|---|---|
| Father Name | Nome do Encarregado | TBD | TBD | TBD | Full width |
| Father Phone | Telemóvel do Pai | TBD | TBD | TBD | Left side |
| Father Email | Email do Pai | TBD | TBD | TBD | Right side |
| Mother Name | - | TBD | TBD | TBD | Full width |
| Mother Phone | Telemóvel da Mãe | TBD | TBD | TBD | Left side |
| Mother Email | Email da Mãe | TBD | TBD | TBD | Right side |
| Guardian Name | Nome do Encarregado | TBD | TBD | TBD | 1/3 width |
| Guardian Address | Morada do Encarregado | TBD | TBD | TBD | 1/3 width |
| Guardian City | Localidade do Encarregado | TBD | TBD | TBD | 1/3 width |
| Guardian Postal Code | Código Postal do Encarregado | TBD | TBD | TBD | 1/3 width |
| Guardian Phone | Telefone do Encarregado | TBD | TBD | TBD | 1/3 width |
| Guardian Mobile | Telemóvel do Encarregado | TBD | TBD | TBD | 1/3 width |
| Guardian Email | Email do Encarregado | TBD | TBD | TBD | Full width |
| Guardian Qualifications | Habilitações do Encarregado | TBD | TBD | TBD | Left side |
| Relationship | Relação do Candidato | TBD | TBD | TBD | Right side |
| Authorization Checkbox | autoriza_dados | TBD | TBD | TBD | Checkbox position |

## Steps to Calibrate

1. **Create Test Data**: Fill the form with test data including:
   - Long names to test width limits
   - All special characters used in Portuguese (ã, ç, é, etc.)

2. **Generate Sample PDF**: Submit the form to generate a PDF

3. **Compare Visually**:
   - Print both the template PDF and generated PDF
   - Check if text aligns inside the blue boxes
   - Check if text is truncated or goes outside boxes

4. **Measure and Record**:
   - For each misaligned field, measure the exact coordinates
   - Update the coordinates in the mapping table above
   - Enter coordinates in the appropriate section of the PHP code

5. **Iterate**: 
   - Update the PHP code with corrected coordinates
   - Generate new test PDF
   - Repeat until perfect alignment

## Adjustment Tips

- **Text too high?** Increase Y coordinate
- **Text too low?** Decrease Y coordinate
- **Text too far left?** Increase X coordinate
- **Text too far right?** Decrease X coordinate
- **Text width issues?** Adjust the width parameter

## Implementation Files

The FPDI-based PDF generation uses these files:
- **`submit_fpdi.php`**: Main PDF generation engine
- **`pdf_coordinate_mapping.json`**: Stores field coordinates (can be updated without PHP changes)

## Testing Locally

To test without sending emails:
1. Use the browser console to submit form data
2. Check the generated PDF in `/storage/submissions/`
3. Open and compare with template
4. Adjust coordinates as needed
5. Regenerate test PDF

## Notes

- Always keep backups of working coordinates
- Test with multiple form data patterns (short names, long names, special characters)
- Ensure checkbox alignment is precise for legal compliance
- Footer section (document reference, date) is usually auto-generated in PHP

## Questions?

If coordinates need adjustment after initial mapping, refer to the calibration procedure above or consult the PDF extraction analysis at:
- `pdf_field_mapping.json` - All text positions in template
- `analyze_pdf_layout.py` - Python script that extracted coordinates
