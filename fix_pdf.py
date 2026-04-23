import pikepdf
import os
import shutil

input_path = 'basePDF_image/MDDPE1406_Ficha_Candidatura_r0.pdf'
temp_path = 'basePDF_image/temp_fixed.pdf'

# Open, save to temp, then replace
with pikepdf.Pdf.open(input_path) as pdf:
    pdf.save(temp_path, object_stream_mode=pikepdf.ObjectStreamMode.disable)

# Now safely replace
shutil.move(temp_path, input_path)
print("PDF compressed object streams disabled successfully.")
