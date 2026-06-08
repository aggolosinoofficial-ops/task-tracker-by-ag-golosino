from flask_login import UserMixin
from werkzeug.security import generate_password_hash, check_password_hash
from lxml import etree
from datetime import datetime

class User(UserMixin):
    def __init__(self, id, username, role):
        self.id = id
        self.username = username
        self.role = role

class AuthService:
    def __init__(self, xml_service):
        self.xml = xml_service
        self.filename = "users"

    def authenticate(self, username, password):
        # Normalize username to lowercase for the search to prevent case-sensitivity issues
        users = self.xml.find_all(self.filename, "//user[username=$u]", u=username.lower())
        if users:
            user_el = users[0]
            stored_hash = user_el.findtext('password_hash')
            if stored_hash:
                try:
                    if check_password_hash(stored_hash, password):
                        return User(
                            id=user_el.get('id'),
                            username=user_el.findtext('username', 'Unknown'),
                            role=user_el.findtext('role', 'user')
                        )
                except (ValueError, Exception) as e:
                    # Catch incompatible hash formats (like PHP $2y$) or unexpected errors
                    # This prevents the server from crashing and returning HTML instead of JSON
                    print(f"[AuthService] Hashing error during login: {e}")
                    return None
        return None

    def get_user_by_id(self, user_id):
        users = self.xml.find_all(self.filename, "//user[@id=$id]", id=user_id)
        if users:
            user_el = users[0]
            return User(
                id=user_el.get('id'),
                username=user_el.findtext('username', 'Unknown'),
                role=user_el.findtext('role', 'user')
            )
        return None

    def username_exists(self, username):
        return bool(self.xml.find_all(self.filename, "//user[username=$u]", u=username.lower()))

    def create_user(self, username, password, role='user'):
        # Normalize to lowercase for consistency
        username = username.lower()
        
        if self.username_exists(username):
            return False, "Username already exists."
        
        tree = self.xml.get_element_tree(self.filename)
        root = tree.getroot()
        
        user_id = self.xml.get_next_id(self.filename, "user")
        new_user = etree.SubElement(root, "user", id=user_id)
        
        # Explicitly setting method to 'scrypt' or 'pbkdf2:sha256' avoids the '' method error
        # and ensures compatibility with modern Werkzeug
        hashed_password = generate_password_hash(password, method='scrypt')
        
        etree.SubElement(new_user, "username").text = username
        etree.SubElement(new_user, "password_hash").text = hashed_password
        etree.SubElement(new_user, "role").text = role
        etree.SubElement(new_user, "created_at").text = datetime.now().isoformat()
        
        return self.xml.save_safely(self.filename, tree)