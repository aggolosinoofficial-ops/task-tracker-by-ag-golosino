import os
from lxml import etree  # type: ignore
from pathlib import Path

def check_spaces_in_xml():
    """
    Scans data XML files for hidden leading/trailing whitespace in element text.
    Extra spaces in <id> or <user_id> tags are a common cause of 'Access Denied' 
    logic errors despite successful login.
    """
    base_dir = Path(r'c:\Users\Atheena\Desktop\task-tracker-by-ag-golosino')
    data_dir = base_dir / 'data'
    
    files_to_check = ['tasks.xml', 'users.xml', 'archive_tasks.xml']
    
    print(f"{'FILE':<18} | {'TAG':<12} | {'RAW VALUE':<20} | {'CONTEXT ID'}")
    print("-" * 75)
    
    found_any = False
    for filename in files_to_check:
        file_path = data_dir / filename
        if not file_path.exists():
            continue
            
        try:
            tree = etree.parse(str(file_path))
            for el in tree.xpath("//*"):
                if el.text and el.text != el.text.strip():
                    # Attempt to find an ID for context
                    parent = el.getparent()
                    ctx_id = el.findtext('id') or el.get('id') or (parent.findtext('id') if parent is not None else "N/A")
                    
                    print(f"{filename:<18} | {el.tag:<12} | '{el.text}'{ ' ' * (max(0, 18-len(el.text)))} | {ctx_id}")
                    found_any = True
        except Exception as e:
            print(f"Error parsing {filename}: {e}")

    if not found_any:
        print("\nNo whitespace issues found. All IDs and values are clean!")

if __name__ == "__main__":
    check_spaces_in_xml()