import zipfile
import xml.etree.ElementTree as ET

def inspect_xlsx(path):
    with zipfile.ZipFile(path, 'r') as zip_ref:
        # Get shared strings
        shared_strings = []
        if 'xl/sharedStrings.xml' in zip_ref.namelist():
            with zip_ref.open('xl/sharedStrings.xml') as f:
                tree = ET.parse(f)
                for si in tree.findall('{http://schemas.openxmlformats.org/spreadsheetml/2006/main}si'):
                    t = si.find('{http://schemas.openxmlformats.org/spreadsheetml/2006/main}t')
                    if t is not None:
                        shared_strings.append(t.text)
                    else:
                        # Handle cases where <t> might be nested in <r> (rich text)
                        texts = [node.text for node in si.findall('.//{http://schemas.openxmlformats.org/spreadsheetml/2006/main}t') if node.text]
                        shared_strings.append("".join(texts))

        # Get sheet1 data
        with zip_ref.open('xl/worksheets/sheet1.xml') as f:
            tree = ET.parse(f)
            root = tree.getroot()
            rows = []
            for row in root.findall('.//{http://schemas.openxmlformats.org/spreadsheetml/2006/main}row'):
                cols = []
                for cell in row.findall('{http://schemas.openxmlformats.org/spreadsheetml/2006/main}c'):
                    val_node = cell.find('{http://schemas.openxmlformats.org/spreadsheetml/2006/main}v')
                    val = val_node.text if val_node is not None else ""
                    if cell.get('t') == 's' and val:
                        val = shared_strings[int(val)]
                    cols.append(val)
                rows.append(cols)
                if len(rows) >= 5: break # Just first 5 rows
            
            for r in rows:
                print("| " + " | ".join([str(c) for c in r]) + " |")

inspect_xlsx('/Users/hyuncao/Onext Digital/GitHub_Projects/ahtkpi/modules/hrm/data/sys4386-candidates-01032023-01042023.report.15.16.08.05.26.xlsx')
