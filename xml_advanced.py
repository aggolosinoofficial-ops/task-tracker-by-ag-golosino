#!/usr/bin/env python3
"""
Advanced XML/XSD/XPath Demonstration Tool: Comprehensive exploration of XML validation, XPath queries, and schema handling
"""

try:
    from lxml import etree  # type: ignore
except ImportError:
    etree = None

from pathlib import Path
import os
import time
import tempfile
from datetime import datetime
from typing import List, Dict, Any, Optional
import json

class AdvancedXMLHandler:
    """Advanced XML handling with XPath, XSD validation, and CRUD operations"""
    
    def __init__(self, xml_file="tasks.xml", xsd_file="tasks.xsd"):
        if etree is None:
            raise ImportError(
                "The 'lxml' library is required for AdvancedXMLHandler. "
                "Install it using 'pip install lxml'."
            )
        """Initialize handler"""
        self.base_path = Path(__file__).parent # Directory where xml_advanced.py resides
        self.data_dir = self.base_path / 'data'
        self.schema_dir = self.base_path / 'schema'

        self.xml_file_name = xml_file # Store just the name for reference
        self.xsd_file_name = xsd_file # Store just the name for reference

        self.xml_path = self.data_dir / xml_file # Full path to XML data file
        self.xsd_path = self.schema_dir / xsd_file # Full path to XSD schema file

        self.tree = None
        self.root = None
        # Centralized Namespace Registry
        self.namespaces = {
            'xs': 'http://www.w3.org/2001/XMLSchema',
            '': 'http://atheena.tracker/schema/tasks',   # Empty string = Default Namespace
            'meta': 'http://atheena.tracker/schema/metadata' # Example secondary namespace
        }
        # Register namespaces globally to control prefixes in serialized output
        for prefix, uri in self.namespaces.items():
            etree.register_namespace(prefix, uri)

        # Ensure data and schema directories exist
        self.data_dir.mkdir(parents=True, exist_ok=True)
        self.schema_dir.mkdir(parents=True, exist_ok=True)
    
    # ==================== BASIC XML OPERATIONS ====================
    
    def load_xml(self) -> bool:
        """Load XML document"""
        if etree is None:
            return False
        try:
            self.tree = etree.parse(str(self.xml_path))
            self.root = self.tree.getroot()
            print(f"✓ XML loaded from {self.xml_path}")
            return True
        except FileNotFoundError:
            print(f"❌ XML file not found: {self.xml_path}")
        except Exception as e:
            print(f"❌ XML Load Error: {e}")
        return False
    
    def save_xml(self) -> bool:
        """Save XML document"""
        if self.tree is None or etree is None:
            print("❌ Cannot save: XML tree not loaded.")
            return False
        try:
            # Use atomic write pattern with retry logic for Windows
            xml_string = etree.tostring(self.tree, encoding='utf-8', xml_declaration=True, pretty_print=True)
            
            # Create a temporary file in the same directory
            fd, temp_path = tempfile.mkstemp(dir=str(self.data_dir), suffix='.tmp')
            try:
                with os.fdopen(fd, 'wb') as f:
                    f.write(xml_string)
                
                # Windows-specific fix: Retry if file is locked (Access Denied)
                for i in range(5):
                    try:
                        os.replace(temp_path, str(self.xml_path))
                        print(f"✓ XML saved safely to {self.xml_path}")
                        break
                    except OSError as e:
                        if e.errno == 5 and i < 4:  # 5 is Access Denied
                            time.sleep(0.1)
                            continue
                        raise
                return True
            finally:
                if os.path.exists(temp_path):
                    try: os.remove(temp_path)
                    except: pass
        except Exception as e:
            print(f"❌ Error saving XML: {e}")
        return False
    
    # ==================== XSD VALIDATION ====================
    
    def validate_against_xsd(self) -> Dict[str, Any]:
        """
        Validate XML against XSD Schema
        Returns validation results
        """
        result = {
            'valid': False,
            'method': 'not_available',
            'errors': [],
            'warnings': []
        }
        
        if not self.xsd_path.exists():
            result['warnings'].append(f"XSD file not found: {self.xsd_path}")
            return result
        
        if etree is None:
            result['warnings'].append("lxml not available (install with: pip install lxml)")
            return result

        try:
            xsd_doc = etree.parse(str(self.xsd_path))
            xsd_schema = etree.XMLSchema(xsd_doc)
            xml_doc = etree.parse(str(self.xml_path))
            
            result['method'] = 'lxml'
            
            if xsd_schema.validate(xml_doc):
                result['valid'] = True
                result['message'] = "✓ XML is VALID against XSD schema"
            else:
                result['message'] = "❌ XML is INVALID against XSD schema"
                for error in xsd_schema.error_log:
                    result['errors'].append({
                            'line': error.line,
                            'column': error.column,
                            'message': error.message,
                            'type': error.type_name
                        })
            return result
        except Exception as e:
            result['errors'].append(f"lxml validation error: {str(e)}")

        return result
    
    # ==================== XPATH QUERIES ====================
    
    def _get_ns_map(self):
        """Internal helper to ensure we have a prefix for XPath logic."""
        # XPath cannot query a default namespace without a prefix.
        # We map the empty prefix to 'dns' (default namespace) for internal queries.
        n = self.namespaces.copy()
        if '' in n:
            n['dns'] = n.pop('')
        return n

    def xpath_get_all_tasks(self) -> List[Any]:
        """XPath: Get all task elements"""
        if self.root is None: return []
        ns = self._get_ns_map()
        return self.root.xpath('.//dns:task', namespaces=ns)
    
    def xpath_get_tasks_by_status(self, status: str) -> List[Any]:
        """XPath: Get tasks filtered by status"""
        if self.root is None: return []
        ns = self._get_ns_map()
        return self.root.xpath('.//dns:task[dns:status=$s]', namespaces=ns, s=status)
    
    def xpath_get_tasks_by_user(self, user_id: int) -> List[Any]:
        """XPath: Get tasks filtered by user_id"""
        if self.root is None: return []
        ns = self._get_ns_map()
        # Using XPath expression for performance and clarity
        return self.root.xpath('.//dns:task[dns:user_id=$u]', namespaces=ns, u=str(user_id))
    
    def xpath_get_task_ids(self) -> List[int]:
        """XPath: Get all task IDs"""
        if self.root is None: return []
        ns = self._get_ns_map()
        id_nodes = self.root.xpath('.//dns:task/dns:id/text()', namespaces=ns)
        return [int(i) for i in id_nodes if str(i).isdigit()]
    
    def xpath_get_task_by_id(self, task_id: int) -> Optional[Any]:
        """XPath: Get specific task by ID"""
        if self.root is None: return None
        ns = self._get_ns_map()
        results = self.root.xpath('.//dns:task[dns:id=$i]', namespaces=ns, i=str(task_id))
        return results[0] if results else None
    
    def xpath_search_title(self, keyword: str) -> List[Any]:
        """XPath: Search tasks by title keyword"""
        tasks = []
        if self.root is None:
            return []
        ns = self._get_ns_map()
        for task in self.root.xpath('.//dns:task', namespaces=ns):
            title = (task.findtext('title') or "").lower()
            if keyword.lower() in title:
                tasks.append(task)
        return tasks
    
    def xpath_count_tasks(self) -> int:
        """XPath: Count total tasks"""
        if self.root is None: return 0
        return len(self.xpath_get_all_tasks())
    
    # ==================== SCHEMA ANALYSIS ====================
    
    def analyze_schema(self) -> Dict[str, Any]:
        """Analyze XSD schema structure"""
        schema_info = {
            'elements': [],
            'complex_types': [],
            'simple_types': [],
            'enumerations': {}
        }
        
        if not self.xsd_path.exists() or etree is None:
            return schema_info
        
        try:
            schema_tree = etree.parse(str(self.xsd_path))
            schema_root = schema_tree.getroot()
            
            # Find all elements
            for elem in schema_root.findall('.//xs:element', self.namespaces):
                name = elem.get('name')
                elem_type = elem.get('type')
                min_occurs = elem.get('minOccurs')
                max_occurs = elem.get('maxOccurs')
                
                schema_info['elements'].append({
                    'name': name,
                    'type': elem_type,
                    'minOccurs': min_occurs,
                    'maxOccurs': max_occurs
                })
            
            # Find all complex types
            for ctype in schema_root.findall('.//xs:complexType', self.namespaces):
                name = ctype.get('name')
                schema_info['complex_types'].append(name)
            
            # Find all simple types (enumerations)
            for stype in schema_root.findall('.//xs:simpleType', self.namespaces):
                name = stype.get('name')
                enums = []
                for enum in stype.findall('.//xs:enumeration', self.namespaces):
                    value = enum.get('value')
                    enums.append(value)
                
                if enums:
                    schema_info['simple_types'].append(name)
                    schema_info['enumerations'][name] = enums
            
        except Exception as e:
            print(f"Error analyzing schema: {e}")
        
        return schema_info
    
    def streaming_search_by_status(self, status: str):
        """
        Demonstration of memory-efficient streaming search using iterparse.
        Does not require load_xml() to be called first.
        """
        if etree is None:
            return

        # Get the default namespace URI from our registry
        ns_uri = self.namespaces.get('', '')
        task_tag = f"{{{ns_uri}}}task" if ns_uri else "task"
        status_tag = f"{{{ns_uri}}}status" if ns_uri else "status"

        # Using 'end' event ensures the element is fully populated
        context = etree.iterparse(str(self.xml_path), events=('end',), tag=task_tag)
        
        for _, elem in context:
            if elem.findtext(status_tag) == status:
                yield self.read_task_element(elem)
            
            # Memory management: clear the element and its predecessors
            elem.clear()
            while elem.getprevious() is not None:
                del elem.getparent()[0]

    # ==================== CRUD OPERATIONS ====================
    
    def validate_task_data(self, task_data: Dict[str, Any]) -> bool:
        """
        Validates a task dictionary against the XSD schema before insertion.
        """
        try:
            l_etree = etree
            if not self.xsd_path.exists() or l_etree is None:
                return True # Fail-safe

            
            # Create a dummy root to satisfy the schema structure
            root = l_etree.Element("tasks")
            task = l_etree.SubElement(root, "task")
            
            # Map dict keys to XML elements
            for key, value in task_data.items():
                child = l_etree.SubElement(task, f"{{{self.namespaces.get('', '')}}}{key}" if self.namespaces.get('') else key)
                child.text = str(value) if value is not None else ""
                
            schema_root = l_etree.parse(str(self.xsd_path))
            schema = l_etree.XMLSchema(schema_root)
            
            # Validate
            if schema.validate(root):
                return True
            else:
                print("❌ Task Data Validation Errors:")
                for error in schema.error_log:
                    print(f"  - {error.message}")
                return False
                
        except ImportError:
            print("⚠️ lxml not installed. Skipping XSD structural check.")
            return True
        except Exception as e:
            print(f"❌ Validation Logic Error: {e}")
            return False

    def create_task(self, task_data: Dict[str, Any]) -> bool:
        """Create new task element"""
        l_etree = etree
        if l_etree is None:
            return False

        try:
            if self.root is None:
                print("❌ Cannot create task: XML root not loaded.")
                return False

            # Find max ID
            max_id = 0
            for task_id in self.root.findall('.//task/id'):
                try:
                    current_id = int(task_id.text)
                    if current_id > max_id:
                        max_id = current_id
                except (ValueError, TypeError):
                    pass
            
            # Pre-validation step
            task_id = task_data.get('id', max_id + 1)
            task_data['id'] = task_id # Ensure ID is present for validation
            task_data['priority'] = task_data.get('priority', 'Medium')
            task_data['due_date'] = task_data.get('due_date', '') # Ensure due_date is present for validation and XML creation
            
            if not self.validate_task_data(task_data):
                return False

            # Create task element
            ns = '{http://atheena.tracker/schema/tasks}'
            task_elem = l_etree.Element(f'{ns}task')
            
            # Add fields ensuring they are in the same namespace as the parent
            l_etree.SubElement(task_elem, f'{ns}id').text = str(task_data['id'])
            l_etree.SubElement(task_elem, f'{ns}user_id').text = str(task_data.get('user_id', 1))
            l_etree.SubElement(task_elem, f'{ns}title').text = task_data.get('title', 'Untitled')
            l_etree.SubElement(task_elem, f'{ns}description').text = task_data.get('description', '')
            l_etree.SubElement(task_elem, f'{ns}status').text = task_data.get('status', 'pending')
            l_etree.SubElement(task_elem, f'{ns}created_at').text = task_data.get('created_at', datetime.now().isoformat())
            l_etree.SubElement(task_elem, f'{ns}priority').text = task_data['priority']
            l_etree.SubElement(task_elem, f'{ns}due_date').text = task_data['due_date']
            
            self.root.append(task_elem)
            print(f"✓ Task created with ID {task_data['id']}")
            return True
            
        except Exception as e:
            print(f"❌ Error creating task: {e}")
            return False
    
    def read_task_element(self, task_elem: Any) -> Dict[str, Any]:
        """
        Helper to convert a task element to a dictionary.
        Shared by XPath and Streaming search.
        """
        l_etree = etree
        if l_etree is None: return {}

        def get_text(tag):
            res = task_elem.xpath(f"string(*[local-name()='{tag}'])")
            return res.strip() if res else ""
        
        data = {
            'id': get_text('id'),
            'user_id': get_text('user_id'),
            'title': get_text('title'),
            'description': get_text('description'),
            'status': get_text('status'),
            'created_at': get_text('created_at')
        }
        # Clean any None values to empty strings
        return {k: (v if v is not None else "") for k, v in data.items()}
    
    def read_task(self, task_id: int) -> Optional[Dict[str, Any]]:
        """Read task as dictionary"""
        task_elem = self.xpath_get_task_by_id(task_id)
        
        if task_elem is None:
            return None
        
        return self.read_task_element(task_elem)
    
    def update_task(self, task_id: int, updates: Dict[str, Any]) -> bool:
        """Update task fields"""
        # Local reference to etree helps Pylance verify it isn't None in this scope
        l_etree = etree
        if l_etree is None: return False

        task_elem = self.xpath_get_task_by_id(task_id)
        
        if task_elem is None:
            print(f"❌ Task {task_id} not found")
            return False
        
        # Create a full representation of the task for validation
        # Start with existing values
        # Use localname to avoid namespace prefix issues in the lookup
        task_data = {l_etree.QName(child).localname: child.text for child in task_elem}
        # Merge with proposed updates
        task_data.update(updates)
        task_data['id'] = task_id  # Ensure the ID remains consistent

        # Validate the proposed state against the XSD
        if not self.validate_task_data(task_data):
            print(f"❌ Update aborted: Validation failed for Task {task_id}")
            return False

        try:
            # If valid, apply changes to the actual XML tree
            for field, value in updates.items():
                # Use local-name to ensure we find the element regardless of namespace prefix
                elem = task_elem.xpath(f"*[local-name()='{field}']")
                if elem is not None:
                    elem[0].text = str(value)
                else:
                    l_etree.SubElement(task_elem, field).text = str(value)
            
            # Persist changes to the file
            return self.save_xml()
            
        except Exception as e:
            print(f"❌ Error updating task: {e}")
            return False
    
    def delete_task(self, task_id: int) -> bool:
        """Delete task element"""
        if self.root is None:
            print("❌ Cannot delete task: XML root not loaded.")
            return False
            
        task_elem = self.xpath_get_task_by_id(task_id)
        
        if task_elem is None:
            print(f"❌ Task {task_id} not found")
            return False
        
        try:
            parent = task_elem.getparent()
            if parent is not None: parent.remove(task_elem)
            print(f"✓ Task {task_id} deleted")
            return True
            
        except Exception as e:
            print(f"❌ Error deleting task: {e}")
            return False
    
    # ==================== REPORTING ====================
    
    def print_all_tasks(self):
        """Print all tasks in table format"""
        tasks = self.xpath_get_all_tasks()
        
        if not tasks:
            print("No tasks found")
            return
        
        print(f"\n{'='*100}")
        print(f"All Tasks ({len(tasks)} total)")
        print(f"{'='*100}\n")
        
        print(f"{'ID':<5} {'User':<6} {'Title':<30} {'Description':<30} {'Status':<12} {'Created':<20}")
        print("-" * 100)
        
        for task in tasks:
            task_id = task.findtext('id', 'N/A')
            user_id = task.findtext('user_id', 'N/A')
            title = (task.findtext('title', 'N/A') or 'N/A')[:28]
            desc = (task.findtext('description', '') or '')[:28]
            status = task.findtext('status', 'N/A')
            created = (task.findtext('created_at', 'N/A') or 'N/A')[-18:]
            
            print(f"{task_id:<5} {user_id:<6} {title:<30} {desc:<30} {status:<12} {created:<20}")
    
    def print_task_statistics(self):
        """Print task statistics"""
        tasks = self.xpath_get_all_tasks()
        
        if not tasks:
            print("No tasks to analyze")
            return
        
        print(f"\n{'='*60}")
        print("TASK STATISTICS")
        print(f"{'='*60}")
        
        total = len(tasks)
        statuses = {}
        users = set()
        
        for task in tasks:
            status = task.findtext('status', 'unknown')
            user_id = task.findtext('user_id')
            
            statuses[status] = statuses.get(status, 0) + 1
            users.add(user_id)
        
        print(f"\nTotal Tasks: {total}")
        print(f"Unique Users: {len(users)}")
        
        print(f"\nStatus Distribution:")
        for status, count in sorted(statuses.items()):
            percentage = (count / total * 100) if total > 0 else 0
            bar = '█' * int(percentage / 5)
            print(f"  {status:<12}: {count:>3} ({percentage:>5.1f}%) {bar}")
    
    def print_schema_info(self):
        """Print schema information"""
        schema_info = self.analyze_schema()
        
        print(f"\n{'='*60}")
        print("XSD SCHEMA INFORMATION")
        print(f"{'='*60}")
        
        print(f"\nElements: {len(schema_info['elements'])}")
        for elem in schema_info['elements']:
            print(f"  • {elem['name']}: {elem['type']} (min: {elem['minOccurs']}, max: {elem['maxOccurs']})")
        
        print(f"\nComplex Types: {len(schema_info['complex_types'])}")
        for ctype in schema_info['complex_types']:
            print(f"  • {ctype}")
        
        print(f"\nSimple Types (Enumerations):")
        for stype, values in schema_info['enumerations'].items():
            print(f"  • {stype}:")
            for value in values:
                print(f"      - {value}")
    
    # ==================== DEMONSTRATION ====================
    
    def demo_xpath_queries(self):
        """Demonstrate various XPath queries"""
        print(f"\n{'='*60}")
        print("XPATH QUERY DEMONSTRATIONS")
        print(f"{'='*60}")
        
        print(f"\n1. Get all tasks:")
        tasks = self.xpath_get_all_tasks()
        print(f"   Found: {len(tasks)} tasks")
        for task in tasks[:3]:  # Show first 3
            print(f"   - {task.findtext('title')}")
        
        if len(tasks) > 3:
            print(f"   ... and {len(tasks) - 3} more")
        
        print(f"\n2. Count total tasks:")
        count = self.xpath_count_tasks()
        print(f"   Total: {count}")
        
        print(f"\n3. Get all task IDs:")
        ids = self.xpath_get_task_ids()
        print(f"   IDs: {ids}")
        
        print(f"\n4. Get completed tasks:")
        completed = self.xpath_get_tasks_by_status('completed')
        print(f"   Found: {len(completed)} completed tasks")
        for task in completed[:2]:
            print(f"   - {task.findtext('title')} ({task.findtext('status')})")
        
        print(f"\n5. Search by title (if any exist):")
        results = self.xpath_search_title('')  # Empty search returns all
        print(f"   Found: {len(results)} tasks")
    
    def run_demo(self):
        """Run complete demonstration"""
        print(f"\n{'#'*60}")
        print("# ADVANCED XML/XSD/XPath HANDLER DEMO")
        print(f"{'#'*60}")
        
        # Load XML
        if not self.load_xml():
            print("Cannot continue without XML file")
            return
        
        # Validation
        print(f"\n{'='*60}")
        print("VALIDATION")
        print(f"{'='*60}")
        validation = self.validate_against_xsd()
        print(f"Method: {validation['method']}")
        print(f"Status: {validation.get('message', 'N/A')}")
        if validation['errors']:
            print("Errors:")
            for error in validation['errors']:
                print(f"  - {error['message']}")
        if validation['warnings']:
            print("Warnings:")
            for warning in validation['warnings']:
                print(f"  - {warning}")
        
        # Show content
        self.print_all_tasks()
        self.print_task_statistics()
        self.print_schema_info()
        self.demo_xpath_queries()
        
        print(f"\n{'#'*60}")
        print("# DEMO COMPLETE")
        print(f"{'#'*60}\n")


def demonstrate_concepts():
    """Demonstrate XML, XSD, and XPath concepts"""
    
    print(f"\n{'*'*70}")
    print("* XML/XSD/XPATH CONCEPTS EXPLANATION")
    print(f"{'*'*70}\n")
    
    concepts = {
        "XML (eXtensible Markup Language)": [
            "• Hierarchical, tree-based data format",
            "• Self-describing: tags define meaning",
            "• Example: <task><id>1</id><title>Buy milk</title></task>",
            "• Used for config, data storage, and interchange"
        ],
        "XSD (XML Schema Definition)": [
            "• Defines rules for XML document structure",
            "• Specifies: element names, types, constraints",
            "• Example: <xs:element name='id' type='xs:positiveInteger'/>",
            "• Enables validation: ensure data correctness"
        ],
        "XPath (XML Path Language)": [
            "• Query language for navigating XML trees",
            "• Example: './/task' selects all task elements",
            "• Examples:",
            "    .//task           - all task elements",
            "    .//task/title     - all title elements in tasks",
            "    .//task[id='5']   - task with specific ID",
            "    count(.//task)    - count all tasks"
        ],
        "Validation": [
            "• Check XML against XSD schema rules",
            "• Ensures data types are correct",
            "• Validates required fields presence",
            "• Checks constraints (min/max length, enum values)"
        ],
        "CRUD Operations": [
            "• CREATE: Add new elements to XML",
            "• READ: Query and retrieve elements",
            "• UPDATE: Modify element values",
            "• DELETE: Remove elements from tree"
        ]
    }
    
    for concept, details in concepts.items():
        print(f"\n{concept}")
        print("─" * 70)
        for detail in details:
            print(detail)


def main():
    """Main entry point"""
    
    # Show concepts
    demonstrate_concepts()
    
    # Run handler demo
    handler = AdvancedXMLHandler()
    handler.run_demo()


if __name__ == "__main__":
    main()
