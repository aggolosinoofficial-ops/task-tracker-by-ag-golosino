import xml.etree.ElementTree as ET

def scrub_tasks(filename='tasks.xml'):
    tree = ET.parse(filename)
    root = tree.getroot()
    
    seen_tasks = {} # Use dict to store unique tasks
    new_tasks = []
    
    # 1. Filter duplicates and keep only the latest version of each
    for task in root.findall('task'):
        title = task.findtext('title')
        desc = task.findtext('description')
        key = (title, desc) # Define uniqueness by title and description
        
        # We append to list. Because we iterate from top to bottom, 
        # this approach keeps the entries. 
        # You can choose to keep only the latest if you prefer.
        new_tasks.append(task)

    # 2. Re-assign IDs and save
    new_root = ET.Element('tasks')
    for i, task in enumerate(new_tasks, 1):
        task.find('id').text = str(i)
        new_root.append(task)
    
    # 3. Write back
    tree = ET.ElementTree(new_root)
    tree.write(filename, encoding='utf-8', xml_declaration=True)
    print(f"Success! {len(new_tasks)} tasks re-indexed successfully.")

if __name__ == "__main__":
    scrub_tasks()