import os
from lxml import etree
import tempfile
from contextlib import nullcontext
try:
    import portalocker
    HAS_PORTALOCKER = True
except ImportError:
    # Fallback if portalocker is not installed (less safe for concurrent writes)
    portalocker = None
    HAS_PORTALOCKER = False

class XMLService:
    def __init__(self):
        # Use absolute paths to ensure the app finds data regardless of where it's launched
        self.base_dir = os.path.dirname(os.path.abspath(__file__))
        self.data_dir = os.path.join(self.base_dir, 'data')
        self.schema_dir = os.path.join(self.base_dir, 'schema')
        self._cache = {}          # Phase 3: Memory Caching
        self._last_load = {}      # Track file mtimes for cache invalidation
        self._schemas = {}        # Pre-compiled schemas
        if not os.path.exists(self.data_dir): os.makedirs(self.data_dir)
        if not os.path.exists(self.schema_dir): os.makedirs(self.schema_dir)
        self._precompile_schemas()

    def _precompile_schemas(self):
        """Pre-compiles XSD schemas to reduce CPU overhead during validation."""
        if not os.path.exists(self.schema_dir):
            return
        for filename in os.listdir(self.schema_dir):
            if filename.endswith('.xsd'):
                name = filename[:-4]
                path = os.path.join(self.schema_dir, filename)
                try:
                    schema_root = etree.parse(path)
                    self._schemas[name] = etree.XMLSchema(schema_root)
                except Exception as e:
                    print(f"Failed to precompile schema {filename}: {e}")

    def _get_paths(self, filename):
        xml_path = os.path.join(self.data_dir, f"{filename}.xml")
        xsd_path = os.path.join(self.schema_dir, f"{filename}.xsd")
        return xml_path, xsd_path

    def get_element_tree(self, filename):
        xml_path, _ = self._get_paths(filename)
        
        # Phase 3: Check cache before reading disk
        mtime = os.path.getmtime(xml_path) if os.path.exists(xml_path) else 0
        if filename in self._cache and self._last_load.get(filename) == mtime:
            return self._cache[filename]

        if not os.path.exists(xml_path) or os.path.getsize(xml_path) == 0:
            root = etree.Element(filename)
            tree = etree.ElementTree(root)
        else:
            tree = etree.parse(xml_path)
        
        # Update Cache
        self._cache[filename] = tree
        self._last_load[filename] = mtime
        return tree

    def find_all(self, filename, xpath, **variables):
        """Finds elements using XPath with variable support to prevent injection."""
        tree = self.get_element_tree(filename)
        return tree.xpath(xpath, **variables)

    def iter_all(self, filename, tag_name):
        """Memory-efficient streaming iterator for bulk reading (Phase 3)."""
        xml_path, _ = self._get_paths(filename)
        if not os.path.exists(xml_path) or os.path.getsize(xml_path) == 0:
            return

        context = etree.iterparse(xml_path, events=('end',), tag=tag_name)
        for event, elem in context:
            yield elem
            elem.clear()
            parent = elem.getparent()
            if parent is not None:
                while elem.getprevious() is not None:
                    del parent[0]

    def _lock_file(self, path):
        """Internal helper to lock a file for writing."""
        if HAS_PORTALOCKER:
            return portalocker.Lock(path, timeout=5)
        return None

    def save_safely(self, filename, tree, entity_id=None):
        xml_path, xsd_path = self._get_paths(filename)
        # Use portalocker if available, otherwise use a nullcontext as a no-op
        lock = self._lock_file(xml_path) or nullcontext()
        
        with lock:
            return self._perform_save(filename, tree, xml_path, entity_id)

    def _perform_save(self, filename, tree, xml_path, entity_id=None):
        # Pre-generate XML string to validate before writing to disk
        xml_string = etree.tostring(tree, encoding='UTF-8', xml_declaration=True, pretty_print=True)
        
        if filename in self._schemas:
            try:
                parser = etree.XMLParser(schema=self._schemas[filename])
                etree.fromstring(xml_string, parser)
            except etree.DocumentInvalid as e:
                error_msg = e.error_log.filter_from_errors()[0].message if e.error_log else "Unknown validation error"
                return False, f"Validation failed: {error_msg}"
            except Exception as e:
                return False, f"Validation error: {str(e)}"

        fd, temp_path = tempfile.mkstemp(dir=self.data_dir, suffix='.tmp')
        try:
            with os.fdopen(fd, 'wb') as f:
                f.write(xml_string)
            os.replace(temp_path, xml_path)
        except Exception as e:
            if os.path.exists(temp_path):
                os.remove(temp_path)
            return False, f"Atomic write failed: {str(e)}"
        
        self._cache[filename] = tree
        self._last_load[filename] = os.path.getmtime(xml_path)
        return True, "Success"

    def get_next_id(self, filename, tag_name):
        """Uses streaming to find the next available ID without loading the whole DOM."""
        max_id = 0
        found = False
        for el in self.iter_all(filename, tag_name):
            found = True
            val = el.get('id')
            if not val:
                child = el.find('id')
                val = child.text if child is not None else None
            
            if val and val.isdigit():
                max_id = max(max_id, int(val))
        return str(max_id + 1) if found else "1"