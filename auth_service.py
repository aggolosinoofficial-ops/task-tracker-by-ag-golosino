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
        if not users:
            return None

        user_el = users[0]
        stored_hash = user_el.findtext('password_hash')

        # Prevent Werkzeug from raising when the stored hash is missing/empty/malformed.
        if stored_hash is None or not str(stored_hash).strip():
            print("[AuthService] Empty password_hash encountered during login")
            return None

        stored_hash = str(stored_hash).strip()

        try:
            if check_password_hash(stored_hash, password):
                return User(
                    id=user_el.get('id'),
                    username=user_el.findtext('username', 'Unknown'),
                    role=user_el.findtext('role', 'user')
                )
        except ValueError as e:
            # Incompatible hash format (e.g., legacy/foreign hash string) or parse failure.
            # Werkzeug sometimes throws this for invalid hash strings.
            print(f"[AuthService] Invalid/unsupported password hash during login: {e}")
            return None
        except Exception as e:
            # Any other unexpected hashing error.
            print(f"[AuthService] Unexpected hashing error during login: {e}")
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