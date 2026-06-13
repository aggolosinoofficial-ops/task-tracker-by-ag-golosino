#!/usr/bin/env python3
"""
Simple XML and XSD Display Tool
Connects to tasks.xml and tasks.xsd, displays and validates content
"""

import xml.etree.ElementTree as ET
import os
from pathlib import Path
from xml.dom import minidom
from datetime import datetime

class XMLDisplay:
    """Simple XML display and validation"""
    
    def __init__(self, xml_file="tasks.xml", xsd_file="tasks.xsd"):
        """Initialize with XML and XSD file paths"""
        self.base_path = Path(__file__).parent
        # Use absolute paths based on where the script is located
        self.data_dir = self.base_path.absolute() / 'data'
        self.schema_dir = self.base_path.absolute() / 'schema'

        self.xml_file = xml_file
        self.xsd_file = xsd_file

        self.xml_path = self.data_dir / xml_file
        self.xsd_path = self.schema_dir / xsd_file
        
        self.tree = None
        self.root = None
        
    def file_exists(self):
        """Check if XML and XSD files exist"""
        print(f"\n{'='*60}")
        print("FILE STATUS")
        print(f"{'='*60}")
        
        xml_exists = self.xml_path.exists()
        xsd_exists = self.xsd_path.exists()
        
        print(f"✓ XML File: {self.xml_file} - {'EXISTS' if xml_exists else 'NOT FOUND'}")
        print(f"  Path: {self.xml_path}")
        
        print(f"✓ XSD File: {self.xsd_file} - {'EXISTS' if xsd_exists else 'NOT FOUND'}")
        print(f"  Path: {self.xsd_path}")
        
        return xml_exists and xsd_exists
    
    def load_xml(self):
        """Load XML file"""
        if not self.xml_path.exists():
            print(f"\n❌ ERROR: XML file not found at {self.xml_path}")
            return False
        
        try:
            self.tree = ET.parse(str(self.xml_path))
            self.root = self.tree.getroot()
            print(f"\n✓ XML loaded successfully")
            return True
        except ET.ParseError as e:
            print(f"\n❌ ERROR: Failed to parse XML: {e}")
            return False
    
    def validate_xsd(self):
        """Validate XML against XSD schema"""
        if not self.xsd_path.exists():
            print(f"\n⚠ WARNING: XSD file not found at {self.xsd_path}")
            print("  Manual validation skipped")
            return None
        
        try:
            # Try using lxml for validation
            try:
                from lxml import etree  # type: ignore
                
                print(f"\n✓ Validating XML against XSD...")
                
                xsd_doc = etree.parse(str(self.xsd_path))
                xsd_schema = etree.XMLSchema(xsd_doc)
                
                xml_doc = etree.parse(str(self.xml_path))
                
                if xsd_schema.validate(xml_doc):
                    print("✓ XML is VALID according to XSD schema")
                    return True
                else:
                    print("❌ XML is INVALID according to XSD schema")
                    print("Validation Errors:")
                    for error in xsd_schema.error_log:
                        print(f"  - Line {error.line}: {error.message}")
                    return False
                    
            except ImportError:
                print("\n⚠ lxml not available, skipping XSD validation")
                print("  Install with: pip install lxml")
                return None
                
        except Exception as e:
            print(f"\n⚠ ERROR during validation: {e}")
            return None
    
    def display_xml_structure(self):
        """Display XML structure and metadata"""
        if self.root is None:
            return
        
        print(f"\n{'='*60}")
        print("XML STRUCTURE")
        print(f"{'='*60}")
        
        print(f"Root Element: <{self.root.tag}>")
        print(f"Number of tasks: {len(self.root)}")
        
        # Display attributes
        if self.root.attrib:
            print(f"Root Attributes:")
            for key, value in self.root.attrib.items():
                print(f"  {key}: {value}")
    
    def display_tasks_table(self):
        """Display tasks in table format"""
        if self.root is None:
            return
        
        tasks = self.root.findall('task')
        
        if not tasks:
            print(f"\n{'='*60}")
            print("TASKS")
            print(f"{'='*60}")
            print("No tasks found in XML file")
            return
        
        print(f"\n{'='*60}")
        print(f"TASKS ({len(tasks)} total)")
        print(f"{'='*60}\n")
        
        # Display as table
        headers = ['ID', 'User ID', 'Title', 'Description', 'Status', 'Created At']
        col_widths = [4, 8, 25, 30, 12, 20]
        
        # Print header
        header_str = " | ".join(h.ljust(w) for h, w in zip(headers, col_widths))
        print(header_str)
        print("-" * len(header_str))
        
        # Print tasks
        for task in tasks:
            task_id = task.findtext('id', 'N/A')
            user_id = task.findtext('user_id', 'N/A')
            title = task.findtext('title', 'N/A')[:25]
            desc = task.findtext('description', '')[:30]
            status = task.findtext('status', 'N/A')
            created = task.findtext('created_at', 'N/A')[-20:]
            
            row_str = " | ".join([
                str(task_id).ljust(col_widths[0]),
                str(user_id).ljust(col_widths[1]),
                title.ljust(col_widths[2]),
                desc.ljust(col_widths[3]),
                status.ljust(col_widths[4]),
                created.ljust(col_widths[5])
            ])
            print(row_str)
    
    def display_raw_xml(self):
        """Display formatted raw XML"""
        if not self.xml_path.exists():
            return
        
        print(f"\n{'='*60}")
        print("RAW XML CONTENT")
        print(f"{'='*60}\n")
        
        try:
            # Parse and pretty print
            dom = minidom.parse(str(self.xml_path))
            pretty_xml = dom.toprettyxml(indent="  ")
            
            # Skip the XML declaration line and print rest
            lines = pretty_xml.split('\n')
            for line in lines:
                if line.strip():
                    print(line)
                    
        except Exception as e:
            print(f"ERROR displaying raw XML: {e}")
    
    def display_xsd_schema(self):
        """Display XSD schema information"""
        if not self.xsd_path.exists():
            return
        
        print(f"\n{'='*60}")
        print("XSD SCHEMA INFORMATION")
        print(f"{'='*60}\n")
        
        try:
            tree = ET.parse(str(self.xsd_path))
            root = tree.getroot()
            
            # Extract namespace
            ns = {'xs': 'http://www.w3.org/2001/XMLSchema'}
            
            # Find elements
            elements = root.findall('.//xs:element', ns)
            complex_types = root.findall('.//xs:complexType', ns)
            simple_types = root.findall('.//xs:simpleType', ns)
            
            print(f"Elements defined: {len(elements)}")
            for elem in elements[:10]:  # Show first 10
                name = elem.get('name', 'unnamed')
                elem_type = elem.get('type', 'inline')
                print(f"  - {name}: {elem_type}")
            
            print(f"\nComplex Types: {len(complex_types)}")
            for ctype in complex_types:
                name = ctype.get('name', 'unnamed')
                print(f"  - {name}")
            
            print(f"\nSimple Types (Enums): {len(simple_types)}")
            for stype in simple_types:
                name = stype.get('name', 'unnamed')
                print(f"  - {name}")
                
                # Show enum values
                enums = stype.findall('.//xs:enumeration', ns)
                for enum in enums:
                    value = enum.get('value', '')
                    print(f"      • {value}")
                    
        except Exception as e:
            print(f"ERROR reading XSD: {e}")
    
    def get_task_statistics(self):
        """Display task statistics"""
        if self.root is None:
            return
        
        tasks = self.root.findall('task')
        
        if not tasks:
            return
        
        print(f"\n{'='*60}")
        print("TASK STATISTICS")
        print(f"{'='*60}")
        
        total = len(tasks)
        status_count = {}
        user_ids = set()
        
        for task in tasks:
            status = task.findtext('status', 'unknown')
            user_id = task.findtext('user_id', 'unknown')
            
            status_count[status] = status_count.get(status, 0) + 1
            user_ids.add(user_id)
        
        print(f"\nTotal Tasks: {total}")
        print(f"Unique Users: {len(user_ids)}")
        
        print(f"\nStatus Breakdown:")
        for status, count in sorted(status_count.items()):
            percentage = (count / total * 100) if total > 0 else 0
            print(f"  {status}: {count} ({percentage:.1f}%)")
    
    def run(self):
        """Run complete display routine"""
        print(f"\n{'#'*60}")
        print("# XML AND XSD DISPLAY TOOL")
        print(f"{'#'*60}")
        
        # Check files
        if not self.file_exists():
            print("\n⚠ WARNING: Some files are missing")
        
        # Load and validate XML
        if self.load_xml():
            self.validate_xsd()
            self.display_xml_structure()
            self.display_tasks_table()
            self.get_task_statistics()
            self.display_xsd_schema()
            self.display_raw_xml()
        
        print(f"\n{'#'*60}")
        print("# DISPLAY COMPLETE")
        print(f"{'#'*60}\n")


def main():
    """Main entry point"""
    display = XMLDisplay()
    display.run()


if __name__ == "__main__":
    main()
