#!/usr/bin/env python3
"""
Advanced XML/XSD/XPath Demonstration Tool
Comprehensive exploration of XML validation, XPath queries, and schema handling
"""

import xml.etree.ElementTree as ET
from pathlib import Path
from datetime import datetime
from typing import List, Dict, Any, Optional
import json

class AdvancedXMLHandler:
    """Advanced XML handling with XPath, XSD validation, and CRUD operations"""
    
    def __init__(self, xml_file="tasks.xml", xsd_file="tasks.xsd"):
        """Initialize handler"""
        self.xml_file = xml_file
        self.xsd_file = xsd_file
        self.base_path = Path(__file__).parent
        self.xml_path = self.base_path / xml_file
        self.xsd_path = self.base_path / xsd_file
        self.tree = None
        self.root = None
        self.namespaces = {
            'xs': 'http://www.w3.org/2001/XMLSchema'
        }
    
    # ==================== BASIC XML OPERATIONS ====================
    
    def load_xml(self) -> bool:
        """Load XML document"""
        try:
            self.tree = ET.parse(str(self.xml_path))
            self.root = self.tree.getroot()
            print(f"✓ XML loaded from {self.xml_path}")
            return True
        except FileNotFoundError:
            print(f"❌ XML file not found: {self.xml_path}")
            return False
        except ET.ParseError as e:
            print(f"❌ XML Parse Error: {e}")
            return False
    
    def save_xml(self) -> bool:
        """Save XML document"""
        try:
            self.tree.write(str(self.xml_path), encoding='utf-8', xml_declaration=True)
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
            from lxml import etree
            
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
    
    def xpath_get_all_tasks(self) -> List[ET.Element]:
        """XPath: Get all task elements"""
        return self.root.findall('.//task')
    
    def xpath_get_tasks_by_status(self, status: str) -> List[ET.Element]:
        """XPath: Get tasks filtered by status"""
        tasks = []
        for task in self.root.findall('.//task'):
            if task.findtext('status') == status:
                tasks.append(task)
        return tasks
    
    def xpath_get_tasks_by_user(self, user_id: int) -> List[ET.Element]:
        """XPath: Get tasks filtered by user_id"""
        tasks = []
        for task in self.root.findall('.//task'):
            if int(task.findtext('user_id', 0)) == user_id:
                tasks.append(task)
        return tasks
    
    def xpath_get_task_ids(self) -> List[int]:
        """XPath: Get all task IDs"""
        ids = []
        for task_id in self.root.findall('.//task/id'):
            try:
                ids.append(int(task_id.text))
            except (ValueError, TypeError):
                pass
        return ids
    
    def xpath_get_task_by_id(self, task_id: int) -> Optional[ET.Element]:
        """XPath: Get specific task by ID"""
        for task in self.root.findall('.//task'):
            if int(task.findtext('id', 0)) == task_id:
                return task
        return None
    
    def xpath_search_title(self, keyword: str) -> List[ET.Element]:
        """XPath: Search tasks by title keyword"""
        tasks = []
        for task in self.root.findall('.//task'):
            title = task.findtext('title', '').lower()
            if keyword.lower() in title:
                tasks.append(task)
        return tasks
    
    def xpath_count_tasks(self) -> int:
        """XPath: Count total tasks"""
        return len(self.root.findall('.//task'))
    
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
            schema_tree = ET.parse(str(self.xsd_path))
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
    
    # ==================== CRUD OPERATIONS ====================
    
    def create_task(self, task_data: Dict[str, Any]) -> bool:
        """Create new task element"""
        try:
            # Find max ID
            max_id = 0
            for task_id in self.root.findall('.//task/id'):
                try:
                    current_id = int(task_id.text)
                    if current_id > max_id:
                        max_id = current_id
                except (ValueError, TypeError):
                    pass
            
            # Create task element
            task_elem = ET.Element('task')
            
            # Add fields
            ET.SubElement(task_elem, 'id').text = str(task_data.get('id', max_id + 1))
            ET.SubElement(task_elem, 'user_id').text = str(task_data.get('user_id', 1))
            ET.SubElement(task_elem, 'title').text = task_data.get('title', 'Untitled')
            ET.SubElement(task_elem, 'description').text = task_data.get('description', '')
            ET.SubElement(task_elem, 'status').text = task_data.get('status', 'pending')
            ET.SubElement(task_elem, 'created_at').text = task_data.get('created_at', datetime.now().isoformat())
            
            self.root.append(task_elem)
            print(f"✓ Task created with ID {task_data.get('id', max_id + 1)}")
            return True
            
        except Exception as e:
            print(f"❌ Error creating task: {e}")
            return False
    
    def read_task(self, task_id: int) -> Optional[Dict[str, Any]]:
        """Read task as dictionary"""
        task_elem = self.xpath_get_task_by_id(task_id)
        
        if task_elem is None:
            return None
        
        return {
            'id': task_elem.findtext('id'),
            'user_id': task_elem.findtext('user_id'),
            'title': task_elem.findtext('title'),
            'description': task_elem.findtext('description'),
            'status': task_elem.findtext('status'),
            'created_at': task_elem.findtext('created_at')
        }
    
    def update_task(self, task_id: int, updates: Dict[str, Any]) -> bool:
        """Update task fields"""
        task_elem = self.xpath_get_task_by_id(task_id)
        
        if task_elem is None:
            print(f"❌ Task {task_id} not found")
            return False
        
        try:
            for field, value in updates.items():
                elem = task_elem.find(field)
                if elem is not None:
                    elem.text = str(value)
                else:
                    ET.SubElement(task_elem, field).text = str(value)
            
            print(f"✓ Task {task_id} updated")
            return True
            
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
