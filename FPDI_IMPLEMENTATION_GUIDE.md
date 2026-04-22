# FPDI PDF Generation - Implementation Guide

## ✅ COMPLETED STEPS

1. ✅ Analyzed template PDF structure
2. ✅ Extracted text positions and field locations
3. ✅ Created coordinate mapping
4. ✅ Developed FPDI-based PDF generation system
5. ✅ Created calibration guides

## 📋 NEXT STEPS

### Step 1: Measure Exact Coordinates (TODAY)

**Time needed**: 30-45 minutes

1. **Open the template PDF**:
   - Location: `basePDF_image/MDDPE1406_Ficha_Candidatura_r0.pdf`
   - Use Adobe Reader or any PDF viewer with measurement capabilities

2. **For each field, measure and record**:
   - Position your cursor at the top-left corner of the input box
   - Note the X and Y coordinates (in PDF points)
   - Estimate the width needed for text

3. **Create your coordinate mapping**:
   ```
   Example: Name field
   - X position: 50 (distance from left edge)
   - Y position: 165 (distance from top)
   - Width: 500 (space available for text)
   ```

4. **Fill in the Calibration Guide**:
   - Edit: `PDF_CALIBRATION_GUIDE.md`
   - Replace all "TBD" values with your measurements
   - This documents your exact measurements for future reference

### Step 2: Create Coordinate Configuration File

1. **Create file**: `pdf_coordinates_actual.php` (at project root)

2. **Copy this template**:
```php
<?php
/**
 * Actual measured coordinates for PDF field overlay
 * Measured from template: MDDPE1406_Ficha_Candidatura_r0.pdf
 * Date measured: [DATE]
 */

return [
    'page_1' => [
        'nome' => [
            'x' => 50,           // Distance from left edge in points
            'y' => 165,          // Distance from top edge in points
            'width' => 500,      // Width available for text
            'font_size' => 10,   // Text size in points
            'form_field' => 'Primeiro Nome + Último Nome',
        ],
        'data_nasc' => [
            'x' => 50,
            'y' => 190,
            'width' => 200,
            'font_size' => 10,
            'form_field' => 'Data de Nascimento',
        ],
        // ... Add all other fields here
    ],
    'page_2' => [
        // ... Page 2 fields
    ],
];
?>
```

### Step 3: Test with Sample Data

1. **Generate a test PDF**:
   - Open your form at: `http://localhost/valdoRio-formulario-v1.4/public/index.html`
   - Fill with test data (especially test names with special characters)
   - Submit the form

2. **Compare visually**:
   - Print both PDFs (template + generated)
   - Check if text aligns inside blue boxes
   - Verify no text is cut off or overflowing

3. **Iterate if needed**:
   - If alignment is off, adjust coordinates
   - Update `pdf_coordinates_actual.php`
   - Generate another test PDF
   - Repeat until satisfied

### Step 4: Update submit_fpdi.php with Final Coordinates

Once you have verified coordinates, update the `buildPdfFromFormData()` function in `submit_fpdi.php`:

```php
function buildPdfFromFormData(array $formData, array $coordinateMapping): string|false
{
    // ... existing code ...
    
    // Example: Adding Nome field with measured coordinates
    $fullName = safeValue($formData, 'Primeiro Nome') . ' ' . safeValue($formData, 'Último Nome');
    addTextToPdf($pdf, $fullName, 50, 165, 500, 'L', 10);
    
    // Add all other fields with their measured coordinates
    addTextToPdf($pdf, safeValue($formData, 'Data de Nascimento'), 50, 190, 200);
    // ... etc for each field ...
}
```

### Step 5: Replace Original submit.php

When confident with the FPDI version:

```bash
# Backup original
cp public/submit.php public/submit_original.php

# Use new FPDI version
cp public/submit_fpdi.php public/submit.php
```

Or update the form action in `index.html` to call `submit_fpdi.php` first.

## 🔍 Current Setup Summary

### New Files Created:
- ✅ `submit_fpdi.php` - FPDI-based PDF generation
- ✅ `pdf_coordinate_mapping.json` - Automated coordinate extraction
- ✅ `extract_pdf_fields.py` - PDF analysis tool
- ✅ `create_pdf_mapping.py` - Mapping generator
- ✅ `analyze_pdf_layout.py` - Layout analyzer
- ✅ `PDF_CALIBRATION_GUIDE.md` - Measurement guide

### Key Differences from Original:

| Aspect | Original | New (FPDI) |
|--------|----------|-----------|
| **Approach** | Generate PDF from scratch | Import template, overlay text |
| **Visual Match** | Approximate layout | Exact template match |
| **Layout Changes** | Requires code editing | Just update coordinates |
| **Future-Proof** | Add new functions | Add coordinate entries |
| **File Size** | Larger | Smaller (template-based) |
| **Appearance** | Generated look | Exact template appearance |

## 📦 Dependencies (Already Installed)

- ✅ `setasign/fpdi` (^2.6) - Template PDF import
- ✅ `setasign/fpdf` (^1.8) - PDF manipulation
- ✅ `phpmailer/phpmailer` - Email functionality
- ✅ Python 3.10 - For PDF analysis tools

## ⚠️ Important Notes

1. **Python Scripts Are Optional**:
   - Only needed for initial analysis
   - Not needed for production (use PHP only)

2. **Coordinate System**:
   - Uses PDF points (1 point = 1/72 inch)
   - Different from HTML/CSS pixel coordinates
   - Requires manual measurement from PDF viewer

3. **Form Field Mapping**:
   - Current code maps form fields to approximate positions
   - Needs fine-tuning based on your measurements
   - See PDF_CALIBRATION_GUIDE.md for all fields

4. **Testing**:
   - Always test with actual form data
   - Include Portuguese characters (ã, ç, é, ñ)
   - Test with very long names to check width limits

## 🎯 Success Criteria

Your implementation is complete when:

✅ Generated PDF matches template visually  
✅ Text aligns inside blue boxes  
✅ No text is cut off or truncated  
✅ Special characters display correctly  
✅ All form fields are populated correctly  
✅ Two-page layout is correct  
✅ Checkbox shows when authorized  

## 🚀 Quick Start Command Reference

```bash
# Run to extract all PDF field data
python extract_pdf_fields.py

# Run to analyze layout and create mapping
python analyze_pdf_layout.py

# Run to generate coordinate mapping
python create_pdf_mapping.py

# View generated mapping
cat pdf_field_mapping.json
cat pdf_coordinate_mapping.json
```

## 💡 Pro Tips

1. **Use Adobe Reader** for most accurate coordinate measurement
2. **Screenshot the template PDF** and annotate field positions
3. **Keep a changelog** of coordinate adjustments
4. **Test with form data containing**:
   - Long names (to test width)
   - Special characters (ã, ç, é, ñ, etc.)
   - Accented names (common in Portugal)

5. **Validate alignment** by overlaying generated PDF on template in image editor

## 📞 Troubleshooting

**Q: Text is not showing in PDF**
- A: Check if coordinates are within page bounds
- Check font size (may be too small)
- Check if text encoding is correct

**Q: Text is in wrong position**
- A: Verify X,Y coordinates are measured from correct origin (top-left)
- Check if you're using right page (page 1 or 2)
- Ensure coordinates are in PDF points, not mm or inches

**Q: Special characters not displaying**
- A: `toPdfText()` function handles encoding
- Ensure form data is UTF-8 encoded
- Test with Portuguese characters

**Q: PDF won't generate**
- A: Check if template PDF exists at correct path
- Verify FPDI library is properly installed
- Check PHP error logs for details

## 📅 Estimated Timeline

- **Step 1 (Measurement)**: 30-45 minutes
- **Step 2 (Configuration)**: 15-20 minutes  
- **Step 3 (Testing)**: 20-30 minutes
- **Step 4 (Updates)**: 10-15 minutes
- **Step 5 (Finalization)**: 5 minutes

**Total estimated time: 1.5 - 2 hours**

## ✅ Next Action

👉 **Start with Step 1**: Open the template PDF and begin measuring field coordinates.

Document your measurements in `PDF_CALIBRATION_GUIDE.md` as you go.

Once you have coordinates, share them and I'll help you update the PHP code for perfect alignment!
