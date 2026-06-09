from werkzeug.security import generate_password_hash, check_password_hash
from lxml import etree
import os

def test_credential_logic(username, password):
    print(f"--- Testing Auth for: {username} ---")
    # Updated path to match your project structure
    xml_path = os.path.join('data', 'users.xml')
    
    if not os.path.exists(xml_path):
        print(f"Error: {xml_path} not found")
        return

    tree = etree.parse(xml_path)
    # Match app logic: lowercasing the search
    search_name = username.lower()
    user_element = tree.xpath(f"//user[username='{search_name}']")
    
    if not user_element:
        print(f"Result: User '{search_name}' NOT found in XML.")
    else:
        print(f"Result: User '{search_name}' found.")
        # Match app logic: search both tag names and clean the string
        stored_hash = user_element[0].findtext('password_hash')
        if stored_hash is None:
            stored_hash = user_element[0].findtext('password')
        
        if not stored_hash or not str(stored_hash).strip():
            print("Result: No password hash found for this user.")
            return

        stored_hash = str(stored_hash).strip()

        # Match app logic: Normalize PHP-style BCrypt hashes ($2y$ -> $2b$)
        if stored_hash.startswith('$2y$'):
            print("Detected legacy PHP hash ($2y$). Normalizing to $2b$...")
            stored_hash = stored_hash.replace('$2y$', '$2b$', 1)
        
        # Werkzeug's check_password_hash might expect an explicit 'bcrypt$' prefix
        # for hashes starting with $2b$, depending on its version.
        if stored_hash.startswith('$2b$'):
            stored_hash_for_check = f"bcrypt${stored_hash}"
        else:
            stored_hash_for_check = stored_hash

        print(f"Final Hash used for check: {stored_hash_for_check}")
        is_valid = check_password_hash(stored_hash_for_check, password) if stored_hash_for_check else False
        print(f"Original Stored Hash: {stored_hash}") # Keep original for reference
        print(f"Result: Password Valid? {is_valid}")

if __name__ == "__main__":
    # Replace with a username/password you know exists in your XML
    # Testing with 'admin123' as found in your data/users.xml
    test_credential_logic('admin123', 'Admin_123')
