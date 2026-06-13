from flask_login import UserMixin
from werkzeug.security import generate_password_hash, check_password_hash
from lxml import etree
from datetime import datetime
import bcrypt

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
            
        # Prioritize attribute ID per users.xsd, fallback to element for legacy compatibility
        user_id = el.get('id') or el.findtext('id')
            
        return cls(
            id=str(user_id).strip() if user_id else None,
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
        # Use normalize-space to handle any stray tabs or newlines in the XML file
        users = self.xml.find_all(self.filename, "//user[normalize-space(username)=$u]", u=username.lower().strip())
        
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

        try:
            # For legacy BCrypt hashes ($2b$), use the bcrypt library directly.
            # For new hashes (scrypt/pbkdf2), use Werkzeug's check_password_hash.
            is_valid = False
            if stored_hash.startswith('$2b$'):
                is_valid = bcrypt.checkpw(password.encode('utf-8'), stored_hash.encode('utf-8'))
            else:
                is_valid = check_password_hash(stored_hash, password)

            if is_valid:
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
        # Match id as an attribute (@id) to align with users.xsd
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
        new_user = etree.SubElement(root, "user")
        
        # Using pbkdf2:sha256 for maximum compatibility across environments
        hashed_password = generate_password_hash(password, method='pbkdf2:sha256')
        
        # Set ID as an attribute to match users.xsd requirement strictly
        new_user.set("id", str(user_id))
        etree.SubElement(new_user, "username").text = username
        etree.SubElement(new_user, "password_hash").text = hashed_password
        etree.SubElement(new_user, "role").text = role
        # Use a timestamp format compatible with strict xs:dateTime (no microseconds)
        etree.SubElement(new_user, "created_at").text = datetime.now().replace(microsecond=0).isoformat()
        
        return self.xml.save_safely(self.filename, tree, user_id)