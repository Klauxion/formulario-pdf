with open('auto_extract_pdf.py', 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Fix the multi-line f-string with backslash in expression
# Find the line with label.replace that's in an f-string
for i, line in enumerate(lines):
    if "label.replace" in line and "php_code +=" in lines[i-1] if i > 0 else False:
        # This line has: 'label' => '{label.replace("'", "\\'")}',
        # We need to extract the label.replace outside the f-string
        # Insert a new line before the f-string
        lines.insert(i, '            escaped_label = label.replace("'"'"'", "\\\\\'")\n')
        # Modify the original line to use escaped_label
        lines[i+1] = lines[i+1].replace('label.replace("'"'"'", "\\\\\'")', 'escaped_label')
        break

with open('auto_extract_pdf.py', 'w', encoding='utf-8') as f:
    f.writelines(lines)

print('Fixed the multi-line f-string!')
