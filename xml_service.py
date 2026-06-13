import os
import time
import threading
from datetime import datetime
from lxml import etree
import tempfile
from io import BytesIO
from contextlib import nullcontext
try:
    import portalocker
    HAS_PORTALOCKER = True
    from portalocker.exceptions import LockException
except ImportError:
    # Fallback if portalocker is not installed (less safe for concurrent writes)
    portalocker = None
    HAS_PORTALOCKER = False
    # Define a dummy LockException if portalocker is not installed
    # This ensures that `except LockException` doesn't cause a NameError
    class LockException(Exception):
        pass

# Module-level constants for retry mechanism
MAX_READ_LOCK_RETRIES = 3
READ_LOCK_RETRY_DELAY_SECONDS = 0.1

class XMLService:
    def __init__(self):
        # Use absolute paths to ensure the app finds data regardless of where it's launched
        self.base_dir = os.path.dirname(os.path.abspath(__file__))
        self.data_dir = os.path.join(self.base_dir, 'data')
        self.schema_dir = os.path.join(self.base_dir, 'schema')
        self._cache = {}          # Phase 3: Memory Caching
        self._last_load = {}      # Track file mtimes for cache invalidation
        self._schemas = {}        # Pre-compiled schemas
        self._cache_lock = threading.Lock() # Protect internal cache dictionaries
        if not os.path.exists(self.data_dir): os.makedirs(self.data_dir)
        if not os.path.exists(self.schema_dir): os.makedirs(self.schema_dir)
        self._precompile_schemas()
        # Self-heal: Cleanup any locks left over from a previous crash
        self.cleanup_orphaned_locks()

    def cleanup_orphaned_locks(self, expiry_seconds=60):
        """
        Safely removes .lock files that are older than expiry_seconds and 
        not currently held by any process.
        """
        cleaned_count = 0
        if not os.path.exists(self.data_dir):
            return 0

        for filename in os.listdir(self.data_dir):
            if filename.endswith('.xml.lock'):
                lock_path = os.path.join(self.data_dir, filename)
                try:
                    # 1. Check age
                    if (time.time() - os.path.getmtime(lock_path)) > expiry_seconds:
                        # 2. Try to acquire exclusive lock to ensure it's not in use
                        if HAS_PORTALOCKER:
                            try:
                                with portalocker.Lock(lock_path, timeout=0.1, 
                                                     flags=portalocker.LOCK_EX | portalocker.LOCK_NB):
                                    os.remove(lock_path)
                                    cleaned_count += 1
                                    print(f"[XMLService] Cleaned orphaned lock: {filename}")
                            except (LockException, Exception):
                                # File is actually in use by another process
                                continue
                        else:
                            os.remove(lock_path)
                            cleaned_count += 1
                except OSError:
                    pass # File might have been deleted by another thread
        return cleaned_count

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
        
        # Initial mtime check (before any locking attempts)
        initial_mtime = 0
        try:
            if os.path.exists(xml_path):
                initial_mtime = os.path.getmtime(xml_path)
        except OSError:
            pass # mtime remains 0

        # 1. Thread-safe cache check (using initial_mtime)
        with self._cache_lock:
            if filename in self._cache and self._last_load.get(filename) == initial_mtime:
                return self._cache[filename]

        MAX_RETRIES = MAX_READ_LOCK_RETRIES
        RETRY_DELAY_SECONDS = READ_LOCK_RETRY_DELAY_SECONDS
        tree = None
        final_mtime = initial_mtime # Will be updated if file is successfully read

        for attempt in range(MAX_RETRIES + 1): # +1 for the initial attempt
            # 2. Use shared lock for reading to avoid collisions with save_safely
            lock = self._lock_file(xml_path, shared=True) or nullcontext()
            try:
                with lock:
                    if not os.path.exists(xml_path) or os.path.getsize(xml_path) == 0:
                        root = etree.Element(filename)
                        tree = etree.ElementTree(root)
                        final_mtime = 0 # Empty tree, so mtime is 0
                    else:
                        try:
                            with open(xml_path, 'rb') as f:
                                xml_data = f.read()
                            parser = etree.XMLParser(remove_blank_text=True)
                            tree = etree.parse(BytesIO(xml_data), parser=parser)
                            final_mtime = os.path.getmtime(xml_path) # Update mtime on successful read
                        except Exception as e: # Catch file read errors
                            print(f"[XMLService] Critical: Failed to read {xml_path}: {e}")
                            root = etree.Element(filename)
                            tree = etree.ElementTree(root)
                            final_mtime = 0 # Failed to read, so mtime is 0
                    break # Successfully acquired lock and processed file, exit retry loop
            except LockException:
                if attempt < MAX_RETRIES:
                    print(f"[XMLService] Warning: Read lock for {filename} timed out. Retrying (attempt {attempt + 1}/{MAX_RETRIES})...")
                    time.sleep(RETRY_DELAY_SECONDS)
                else:
                    print(f"[XMLService] Error: Read lock for {filename} timed out after {MAX_RETRIES} retries. Returning empty tree.")
                    root = etree.Element(filename)
                    tree = etree.ElementTree(root)
                    final_mtime = 0 # All retries failed, return empty tree, mtime 0
                    break # Exit loop after all retries fail
            except Exception as e: # Catch other unexpected errors during lock acquisition or context management
                print(f"[XMLService] Critical: Unexpected error during lock acquisition or context for {filename}: {e}. Returning empty tree.")
                root = etree.Element(filename)
                tree = etree.ElementTree(root)
                final_mtime = 0 # Unexpected error, return empty tree, mtime 0
                break # Exit loop for other unexpected errors

        if tree is None: # Should not happen with the current logic, but as a safeguard
            root = etree.Element(filename)
            tree = etree.ElementTree(root)
            final_mtime = 0

        # 3. Thread-safe cache update
        with self._cache_lock:
            self._cache[filename] = tree
            self._last_load[filename] = final_mtime
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

        MAX_RETRIES = MAX_READ_LOCK_RETRIES
        RETRY_DELAY_SECONDS = READ_LOCK_RETRY_DELAY_SECONDS

        xml_data = None
        for attempt in range(MAX_RETRIES + 1):
            lock = self._lock_file(xml_path, shared=True) or nullcontext()
            try:
                with lock:
                    with open(xml_path, 'rb') as f:
                        xml_data = f.read()
                    break # Success, break retry loop
            except LockException:
                if attempt < MAX_RETRIES:
                    print(f"[XMLService] Warning: Read lock for {filename} timed out during iteration. Retrying (attempt {attempt + 1}/{MAX_RETRIES})...")
                    time.sleep(RETRY_DELAY_SECONDS)
                else:
                    print(f"[XMLService] Error: Read lock for {filename} timed out after {MAX_RETRIES} retries during iteration. Skipping file.")
                    return # All retries failed, return empty iterator
            except Exception as e:
                print(f"[XMLService] Critical: Unexpected error while reading {filename} for iteration: {e}. Skipping file.")
                return # Other unexpected error, return empty iterator

        if xml_data is None: # If xml_data is still None after retries (e.g., all retries failed)
            return

        try:
            parser = etree.XMLParser(remove_blank_text=True)
            context = etree.iterparse(BytesIO(xml_data), events=('end',), tag=tag_name, parser=parser)
            for event, elem in context:
                yield elem
                elem.clear()
                parent = elem.getparent()
                if parent is not None:
                    while elem.getprevious() is not None:
                        del parent[0]
        except etree.XMLSyntaxError as e:
            print(f"[XMLService] Error parsing {xml_path}: {e}. Returning empty iterator.")
            return

    def _lock_file(self, path, shared=False):
        """Internal helper to lock a file. Shared for reading, Exclusive for writing."""
        if HAS_PORTALOCKER:
            flags = portalocker.LOCK_SH if shared else portalocker.LOCK_EX
            # Lock a sidecar .lock file to avoid Access Denied during os.replace on Windows
            return portalocker.Lock(path + ".lock", timeout=5, flags=flags)
        return None

    def apply_xslt(self, xml_filename, xsl_filename, **params):
        """Applies an XSLT transformation on the server to avoid browser-side deprecation."""
        xml_path, _ = self._get_paths(xml_filename)
        xsl_path = os.path.join(self.schema_dir, f"{xsl_filename}.xsl")
        
        if not os.path.exists(xsl_path):
            # Fallback to base directory if not in schema folder
            xsl_path = os.path.join(self.base_dir, f"{xsl_filename}.xsl")

        if not os.path.exists(xml_path) or not os.path.exists(xsl_path):
            return None

        try:
            tree = self.get_element_tree(xml_filename)
            xslt_root = etree.parse(xsl_path)
            transform = etree.XSLT(xslt_root)
            result = transform(tree, **params)
            return etree.tostring(result, encoding='unicode', method='html')
        except Exception as e:
            print(f"[XMLService] Server-side XSLT failed: {e}")
            return None

    def save_safely(self, filename, tree, entity_id=None):
        xml_path, xsd_path = self._get_paths(filename)
        lock_file_path = xml_path + ".lock"
        # Use portalocker if available, otherwise use a nullcontext as a no-op
        lock = self._lock_file(xml_path) or nullcontext()

        log_id = f" (ID: {entity_id})" if entity_id else ""

        try:
            with lock:
                # Auto-sort tasks if saving tasks to ensure XSD sequence compliance
                order = None
                tag_name = None
                
                if "tasks" in filename or "archive_tasks" in filename:
                    order = ['id', 'user_id', 'assigned_to', 'title', 'description', 'status', 'created_at', 'priority', 'due_date', 'last_updated', 'archived_at']
                    tag_name = './/task'
                elif "users" in filename:
                    order = ['id', 'username', 'password_hash', 'role', 'created_at']
                    tag_name = './/user'

                if order and tag_name:
                    for item in tree.xpath(tag_name):
                        elements = {child.tag: child for child in item}
                        for child in list(item):
                            item.remove(child)
                        for tag in order:
                            if tag in elements:
                                item.append(elements.pop(tag))
                        # Append any remaining fields that weren't in the specific order
                        for remaining in elements.values():
                            item.append(remaining)
                
                xml_string = etree.tostring(tree, encoding='UTF-8', xml_declaration=True, pretty_print=True)
                
                # Map archive_tasks to use the tasks schema for validation
                schema_key = "tasks" if "tasks" in filename else filename
                if schema_key in self._schemas:
                    try:
                        parser = etree.XMLParser(schema=self._schemas[schema_key], remove_blank_text=True)
                        etree.fromstring(xml_string, parser)
                    except etree.XMLSyntaxError as e:
                        return False, f"XML Syntax Error: {str(e)}"
                    except etree.DocumentInvalid as e:
                        # Detailed error reporting for XSD mismatches
                        log = e.error_log.filter_from_errors()
                        error_msg = str(log[0]) if log else "Unknown validation error"
                        print(f"[XMLService] XSD Validation failed for {filename}{log_id}: {error_msg}")
                        return False, f"Schema Validation Error{log_id}: {error_msg}"
                    except Exception as e:
                        return False, f"Validation error: {str(e)}"

                fd, temp_path = tempfile.mkstemp(dir=self.data_dir, suffix='.tmp')
                try:
                    with os.fdopen(fd, 'wb') as f:
                        f.write(xml_string)
                    
                    # Windows-specific fix: Retry replace if a background process (AV/Indexer) 
                    # has a temporary handle on the file (WinError 5 Access Denied)
                    for i in range(5):
                        try:
                            os.replace(temp_path, xml_path)
                            break
                        except OSError as e:
                            if e.errno == 5 and i < 4: # 5 is Access Denied
                                time.sleep(0.1) # Wait 100ms and try again
                                continue
                            raise
                except Exception as e:
                    if os.path.exists(temp_path):
                        os.remove(temp_path)
                    return False, f"Atomic write failed: {str(e)}"
                
                self._cache[filename] = tree
                self._last_load[filename] = os.path.getmtime(xml_path)
                return True, "Success"
        except Exception as e:
            # Catch locking timeouts or any unexpected runtime errors to prevent crashing
            error_msg = f"Save failed for {filename}{log_id}: {str(e)}"
            print(f"[XMLService] Critical: {error_msg}")
            return False, error_msg
        finally:
            # Confirmation: Clean up the lock file from disk once released
            if HAS_PORTALOCKER and os.path.exists(lock_file_path):
                # On Windows, we only attempt to remove the lock if we can acquire 
                # it exclusively, meaning no other thread is currently queued/waiting.
                try:
                    with portalocker.Lock(lock_file_path, timeout=0.1, flags=portalocker.LOCK_EX | portalocker.LOCK_NB):
                        os.remove(lock_file_path)
                        print(f"[XMLService] Cleanup: Removed {os.path.basename(lock_file_path)}")
                except (LockException, OSError):
                    # Another process is waiting or handle is still closing; skip deletion
                    pass

    def get_next_id(self, filename, tag_name):
        """Uses streaming to find the next available ID without loading the whole DOM."""
        max_id = 0
        found = False
        for el in self.iter_all(filename, tag_name):
            found = True
            # Check both child element and attribute for legacy compatibility
            val_el = el.findtext('id')
            val_attr = el.get('id')
            val = val_el or val_attr
            
            if val and val.isdigit():
                max_id = max(max_id, int(val))
        return str(max_id + 1) if found else "1"

    def get_health_status(self, include_all=False):
        """Analyzes storage health, identifying lingering locks or storage issues."""
        health = {
            'status': 'Healthy',
            'files': {},
            'locks': 0,
            'cache_size': len(self._cache)
        }
        
        files_to_check = ['tasks', 'users', 'archive_tasks', 'activity_logs'] if include_all else ['tasks']
        
        for filename in files_to_check:
            xml_path, _ = self._get_paths(filename)
            lock_path = xml_path + ".lock"
            
            exists = os.path.exists(xml_path)
            file_info = {'exists': exists}
            if exists:
                file_info['size_kb'] = round(os.path.getsize(xml_path) / 1024, 2)
                file_info['last_modified'] = datetime.fromtimestamp(os.path.getmtime(xml_path)).isoformat()
            
            if os.path.exists(lock_path):
                health['locks'] += 1
                # Check if lock is likely orphaned (older than 30 seconds)
                if (time.time() - os.path.getmtime(lock_path)) > 30:
                    health['status'] = 'Warning: Lingering locks detected'
            
            health['files'][filename] = file_info
            
        return health