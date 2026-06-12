import os
from lxml import etree

def run_migration():
    # Absolute path to your users.xml file
    file_path = r'c:\Users\Atheena\Desktop\task-tracker-by-ag-golosino\data\users.xml'
    
    if not os.path.exists(file_path):
        print(f"File not found: {file_path}")
        return

    try:
        # Use a parser that preserves formatting but allows modification
        parser = etree.XMLParser(remove_blank_text=True)
        tree = etree.parse(file_path, parser)
        root = tree.getroot()

        count = 0
        for user in root.xpath("//user"):
            id_el = user.find('id')
            if id_el is not None:
                # Move ID from child element to attribute
                user.set('id', id_el.text)
                user.remove(id_el)
                count += 1

        if count > 0:
            tree.write(file_path, encoding='utf-8', xml_declaration=True, pretty_print=True)
            print(f"Successfully migrated {count} users from <id> elements to 'id' attributes.")
        else:
            print("No users required migration (already using attributes).")

    except Exception as e:
        print(f"Migration failed: {e}")

if __name__ == "__main__":
    run_migration()