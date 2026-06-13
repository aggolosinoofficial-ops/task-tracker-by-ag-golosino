#!/usr/bin/env python3
"""
Advanced XML/XSD/XPath Demonstration Tool: Comprehensive exploration of XML validation, XPath queries, and schema handling
"""

from lxml import etree  # type: ignore
from pathlib import Path
from datetime import datetime
from typing import List, Dict, Any, Optional
import json

class AdvancedXMLHandler:
    """Advanced XML handling with XPath, XSD validation, and CRUD operations"""
    
    def __init__(self, xml_file="tasks.xml", xsd_file="tasks.xsd"):
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
        try:
            self.tree = etree.parse(str(self.xml_path))
            self.root = self.tree.getroot()
            print(f"✓ XML loaded from {self.xml_path}")
            return True
        except FileNotFoundError:
            print(f"❌ XML file not found: {self.xml_path}")
            return False
        except etree.XMLSyntaxError as e:
            print(f"❌ XML Parse Error: {e}")
            return False
    
    def save_xml(self) -> bool:
        """Save XML document"""
        try:
            if self.tree is not None:
                self.tree.write(str(self.xml_path), encoding='utf-8', xml_declaration=True, pretty_print=True)
            print(f"✓ XML saved to {self.xml_path}")
            return True
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
        
        # Try lxml (preferred)
        try:
            from lxml import etree  # type: ignore
            
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
                
        except ImportError:
            result['warnings'].append("lxml not available (install with: pip install lxml)")
        
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

    def xpath_get_all_tasks(self) -> List[etree._Element]:
        """XPath: Get all task elements"""
        if self.root is None: return []
        ns = self._get_ns_map()
        return self.root.xpath('.//dns:task', namespaces=ns)
    
    def xpath_get_tasks_by_status(self, status: str) -> List[etree._Element]:
        """XPath: Get tasks filtered by status"""
        if self.root is None: return []
        ns = self._get_ns_map()
        return self.root.xpath('.//dns:task[dns:status=$s]', namespaces=ns, s=status)
    
    def xpath_get_tasks_by_user(self, user_id: int) -> List[etree._Element]:
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
    
    def xpath_get_task_by_id(self, task_id: int) -> Optional[etree._Element]:
        """XPath: Get specific task by ID"""
        if self.root is None: return None
        ns = self._get_ns_map()
        results = self.root.xpath('.//dns:task[dns:id=$i]', namespaces=ns, i=str(task_id))
        return results[0] if results else None
    
    def xpath_search_title(self, keyword: str) -> List[etree._Element]:
        """XPath: Search tasks by title keyword"""
        tasks = []
        if self.root is None: return []
        ns = self._get_ns_map()
        # Use XPath to filter directly for efficiency and namespace awareness
        query = f".//dns:task[contains(translate(dns:title, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), $k)]"
        return self.root.xpath(query, namespaces=ns, k=keyword.lower())
    
    def xpath_count_tasks(self) -> int:
        """XPath: Count total tasks"""
        if self.root is None: return 0
        ns = self._get_ns_map()
        return len(self.root.xpath('.//dns:task', namespaces=ns))
    
    # ==================== SCHEMA ANALYSIS ====================
    
    def analyze_schema(self) -> Dict[str, Any]:
        """Analyze XSD schema structure"""
        schema_info = {
            'elements': [],
            'complex_types': [],
            'simple_types': [],
            'enumerations': {}
        }
        
        if not self.xsd_path.exists():
            return schema_info
        
        try:
            schema_tree = etree.parse(str(self.xsd_path))
            schema_root = schema_tree.getroot()
            
            # Find all elements
            for elem in schema_root.findall(etree.QName(self.namespaces['xs'], 'element')):
                name = elem.get('name')
                elem_type = elem.get('type')
                min_occurs = elem.get('minOccurs')
                max_occurs = elem.get('maxOccurs')
                
                schema_info['elements'].append({
                    'name': name, 'type': elem_type,
                    'minOccurs': min_occurs, 'maxOccurs': max_occurs
                })
            
            # Find all complex types
            for ctype in schema_root.findall(etree.QName(self.namespaces['xs'], 'complexType')):
                name = ctype.get('name')
                schema_info['complex_types'].append(name)
            
            # Find all simple types (enumerations)
            for stype in schema_root.findall(etree.QName(self.namespaces['xs'], 'simpleType')):
                name = stype.get('name')
                enums = []
                # Use namespace-aware findall for enumerations within simpleType
                for enum in stype.findall(etree.QName(self.namespaces['xs'], 'enumeration')):
                    value = enum.get('value')
                    enums.append(value)
                
                if enums:
                    schema_info['simple_types'].append(name)
                    schema_info['enumerations'][name] = enums
            
        except Exception as e:
            print(f"Error analyzing schema: {e}")
        
        return schema_info
    
    # ==================== CRUD OPERATIONS ====================
    
    def validate_task_data(self, task_data: Dict[str, Any]) -> bool:
        """
        Validates a task dictionary against the XSD schema before insertion.
        """
        try:
            from lxml import etree  # type: ignore
            
            # Create a dummy root to satisfy the schema structure
            default_ns_uri = self.namespaces.get('')
            if not default_ns_uri:
                print("❌ Error: Default namespace URI not defined for task validation.")
                return False

            root = etree.Element(etree.QName(default_ns_uri, "tasks"))
            task = etree.SubElement(root, etree.QName(default_ns_uri, "task"))
            
            # Map dict keys to XML elements
            for key, value in task_data.items():
                child = etree.SubElement(task, etree.QName(default_ns_uri, key))
                child.text = str(value) if value is not None else ""

            # Load schema
            if not self.xsd_path.exists():
                print(f"⚠️ Validation skipped: {self.xsd_path} missing.")
                return True # Fail-safe if XSD is missing during dev
                
            schema_root = etree.parse(str(self.xsd_path))
            schema = etree.XMLSchema(schema_root)
            
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
        try:
            # Find max ID
            max_id = 0 # Initialize max_id
            if self.root is None: return False
            ns = self._get_ns_map()
            # Use namespace-aware XPath to find all task IDs
            for task_id_el in self.root.xpath('.//dns:task/dns:id', namespaces=ns):
                try:
                    current_id = int(task_id_el.text)
                    if current_id > max_id:
                        max_id = current_id
                except (ValueError, TypeError):
                    # Handle cases where ID might not be a valid integer
                    pass
            
            # Pre-validation step
            task_id = task_data.get('id', max_id + 1)
            task_data['id'] = task_id # Ensure ID is present for validation
            task_data['priority'] = task_data.get('priority', 'Medium')
            task_data['due_date'] = task_data.get('due_date', '') # Ensure due_date is present for validation and XML creation
            
            if not self.validate_task_data(task_data):
                return False

            # Create task element in the default namespace
            default_ns_uri = self.namespaces.get('')
            if not default_ns_uri:
                print("❌ Error: Default namespace URI not defined for task creation.")
                return False

            task_elem = etree.Element(etree.QName(default_ns_uri, 'task'))
            
            # Add fields using QName for namespace awareness
            etree.SubElement(task_elem, etree.QName(default_ns_uri, 'id')).text = str(task_data['id'])
            etree.SubElement(task_elem, etree.QName(default_ns_uri, 'user_id')).text = str(task_data.get('user_id', 1))
            etree.SubElement(task_elem, etree.QName(default_ns_uri, 'title')).text = task_data.get('title', 'Untitled')
            etree.SubElement(task_elem, etree.QName(default_ns_uri, 'description')).text = task_data.get('description', '')
            etree.SubElement(task_elem, etree.QName(default_ns_uri, 'status')).text = task_data.get('status', 'pending')
            etree.SubElement(task_elem, etree.QName(default_ns_uri, 'created_at')).text = task_data.get('created_at', datetime.now().isoformat())
            etree.SubElement(task_elem, etree.QName(default_ns_uri, 'priority')).text = task_data['priority']
            etree.SubElement(task_elem, etree.QName(default_ns_uri, 'due_date')).text = task_data['due_date']
            
            if self.root is not None:
                self.root.append(task_elem)
            print(f"✓ Task created with ID {task_data['id']}")
            return True
            
        except Exception as e:
            print(f"❌ Error creating task: {e}")
            return False
    
    def read_task(self, task_id: int) -> Optional[Dict[str, Any]]:
        """Read task as dictionary"""
        task_elem = self.xpath_get_task_by_id(task_id)
        
        if task_elem is None:
            return None
        
        # Use namespace-aware findtext for child elements
        default_ns_uri = self.namespaces.get('')
        if not default_ns_uri:
            print("❌ Error: Default namespace URI not defined for reading task.")
            return None

        # Extract data using QName for namespace awareness
        data = {}
        for child in task_elem:
            tag_name = child.tag.localname if isinstance(child.tag, etree.QName) else child.tag
            data[tag_name] = child.text
        return data
    
    def update_task(self, task_id: int, updates: Dict[str, Any]) -> bool:
        """Update task fields"""
        task_elem = self.xpath_get_task_by_id(task_id)
        
        if task_elem is None:
            print(f"❌ Task {task_id} not found")
            return False
        
        # Create a full representation of the task for validation
        # Start with existing values
        default_ns_uri = self.namespaces.get('')
        if not default_ns_uri:
            print("❌ Error: Default namespace URI not defined for updating task.")
            return False

        task_data = {}
        for child in task_elem:
            tag_name = child.tag.localname if isinstance(child.tag, etree.QName) else child.tag
            task_data[tag_name] = child.text
        # Merge with proposed updates
        task_data.update(updates)
        task_data['id'] = task_id  # Ensure the ID remains consistent

        # Validate the proposed state against the XSD
        if not self.validate_task_data(task_data):
            print(f"❌ Update aborted: Validation failed for Task {task_id}")
            return False

        try:
            # If valid, apply changes to the actual XML tree
            # Use namespace-aware find and SubElement
            for field, value in updates.items():
                node_qname = etree.QName(default_ns_uri, field)
                elem = task_elem.find(node_qname)
                if elem is not None:
                    elem.text = str(value)
                else:
                    etree.SubElement(task_elem, node_qname).text = str(value)
            
            # Persist changes to the file
            return self.save_xml()
            
        except Exception as e:
            print(f"❌ Error updating task: {e}")
            return False
    
    def delete_task(self, task_id: int) -> bool:
        """Delete task element"""
        task_elem = self.xpath_get_task_by_id(task_id)
        
        if task_elem is None:
            print(f"❌ Task {task_id} not found")
            return False
        
        try:
            if self.root is not None:
                self.root.remove(task_elem)
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
        
        default_ns_uri = self.namespaces.get('')
        if not default_ns_uri:
            print("❌ Error: Default namespace URI not defined for printing tasks.")
            return

        for task in tasks:
            # Use namespace-aware findtext for child elements
            task_id = task.findtext(etree.QName(default_ns_uri, 'id'), 'N/A')
            user_id = task.findtext(etree.QName(default_ns_uri, 'user_id'), 'N/A')
            title = (task.findtext(etree.QName(default_ns_uri, 'title'), 'N/A') or 'N/A')[:28]
            desc = (task.findtext(etree.QName(default_ns_uri, 'description'), '') or '')[:28]
            status = task.findtext(etree.QName(default_ns_uri, 'status'), 'N/A')
            created = (task.findtext(etree.QName(default_ns_uri, 'created_at'), 'N/A') or 'N/A')[-18:]
            
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
        
        default_ns_uri = self.namespaces.get('')
        if not default_ns_uri:
            print("❌ Error: Default namespace URI not defined for task statistics.")
            return

        for task in tasks:
            # Use namespace-aware findtext for child elements
            status = task.findtext(etree.QName(default_ns_uri, 'status'), 'unknown')
            user_id = task.findtext(etree.QName(default_ns_uri, 'user_id'))
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
