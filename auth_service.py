from flask_login import UserMixin
from werkzeug.security import generate_password_hash, check_password_hash
from lxml import etree
from datetime import datetime

class User(UserMixin):
    def __init__(self, id, username, role):
        self.id = id
        self.username = username
        self.role = role

    @classmethod
    def from_element(cls, el):
        """Factory method to create a User object from an lxml element."""
        if el is None:
            return None
        return cls(
            id=el.get('id'),
            username=el.findtext('username', 'Unknown'),
            role=el.findtext('role', 'user')
        )

class AuthService:
    def __init__(self, xml_service):
        self.xml = xml_service
        self.filename = "users"

    def authenticate(self, username, password):
        if not username or not password:
            return None
            
        # Normalize username to lowercase for the search to prevent case-sensitivity issues
        users = self.xml.find_all(self.filename, "//user[username=$u]", u=username.lower())
        if not users:
            print(f"[AuthService] Login failed: User '{username}' not found in XML.")
            return None

        user_el = users[0]
        # 1. Fetch the hash from the correct tag
        stored_hash = user_el.findtext('password_hash')
        if stored_hash is None:
            stored_hash = user_el.findtext('password')

        if stored_hash is None or not str(stored_hash).strip():
            print(f"[AuthService] Login failed: No password hash found for user '{username}'.")
            return None

        stored_hash = str(stored_hash).strip()

        # 2. Normalize PHP-style BCrypt hashes ($2y$ -> $2b$)
        # Python's bcrypt library often rejects the $2y$ prefix used by PHP.
        if stored_hash.startswith('$2y$'):
            stored_hash = stored_hash.replace('$2y$', '$2b$', 1)
            print(f"[AuthService] Normalized legacy PHP hash for user '{username}'.")

        # Werkzeug's check_password_hash might expect an explicit 'bcrypt$' prefix
        # for hashes starting with $2b$, depending on its version.
        hash_to_check = f"bcrypt${stored_hash}" if stored_hash.startswith('$2b$') else stored_hash

        try:
            if check_password_hash(hash_to_check, password):
                return User.from_element(user_el)
            else:
                print(f"[AuthService] Login failed: Incorrect password for user '{username}'.")
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
            return User.from_element(users[0])
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
        
        return self.xml.save_safely(self.filename, tree, user_id)