import os
import sys
from lxml import etree  # type: ignore
from werkzeug.security import generate_password_hash
from xml_service import XMLService
from datetime import datetime

def reset_admin(password="admin123"):
    """Repairs or creates the admin account in users.xml."""
    service = XMLService()
    filename = "users"
    
    tree = service.get_element_tree(filename)
    root = tree.getroot()
    
    # Target namespace logic
    ns = root.nsmap.get(None)
    tag = lambda t: f"{{{ns}}}{t}" if ns else t
    
    # Find existing admin using namespace-agnostic XPath
    admin_nodes = root.xpath("//*[local-name()='user'][normalize-space(*[local-name()='username'])='admin']")
    
    hashed_pw = generate_password_hash(password, method='pbkdf2:sha256')
    
    if admin_nodes:
        admin = admin_nodes[0]
        # Update password hash
        pw_el = admin.xpath("*[local-name()='password_hash'] | *[local-name()='password']")
        if pw_el:
            pw_el[0].text = hashed_pw
        else:
            etree.SubElement(admin, tag("password_hash")).text = hashed_pw
            
        # Ensure role is admin
        role_el = admin.xpath("*[local-name()='role']")
        if role_el: role_el[0].text = "admin"
        else: etree.SubElement(admin, tag("role")).text = "admin"
        
        user_id = admin.get('id') or admin.xpath("string(*[local-name()='id'])")
        print(f"[*] Repaired existing 'admin' (ID: {user_id})")
    else:
        user_id = service.get_next_id(filename, "user")
        admin = etree.SubElement(root, tag("user"))
        admin.set("id", str(user_id))
        etree.SubElement(admin, tag("username")).text = "admin"
        etree.SubElement(admin, tag("password_hash")).text = hashed_pw
        etree.SubElement(admin, tag("role")).text = "admin"
        etree.SubElement(admin, tag("created_at")).text = datetime.now().replace(microsecond=0).isoformat()
        print(f"[+] Created new 'admin' account (ID: {user_id})")

    success, msg = service.save_safely(filename, tree, user_id)
    if success:
        print(f"[✓] Admin password reset to: {password}")

if __name__ == "__main__":
    reset_admin(sys.argv[1] if len(sys.argv) > 1 else "admin123")