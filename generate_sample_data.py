#!/usr/bin/env python3
"""
Sample Data Generator for Tasks XML
Populates tasks.xml with realistic test data
"""

import xml.etree.ElementTree as ET
from pathlib import Path
from datetime import datetime, timedelta
import random

class SampleDataGenerator:
    """Generate sample task data"""
    
    def __init__(self, xml_file="tasks.xml"):
        """Initialize generator"""
        self.xml_file = xml_file
        self.base_path = Path(__file__).parent
        self.xml_path = self.base_path / xml_file
    
    def generate_tasks(self, num_tasks=15):
        """Generate sample tasks"""
        
        # Sample data
        sample_titles = [
            "Buy groceries",
            "Complete project report",
            "Fix bug in authentication",
            "Write unit tests",
            "Update documentation",
            "Review pull requests",
            "Attend team meeting",
            "Refactor database queries",
            "Implement new feature",
            "Deploy to production",
            "Fix responsive design",
            "Optimize images",
            "Create API endpoints",
            "Setup CI/CD pipeline",
            "Security audit"
        ]
        
        sample_descriptions = [
            "Get milk, eggs, bread, and vegetables",
            "Complete quarterly report for management review",
            "Fix login issue preventing users from accessing",
            "Add tests for new authentication methods",
            "Update API documentation with new endpoints",
            "Review 5 pending pull requests from team",
            "Sync with product team on roadmap",
            "Improve slow database queries in dashboard",
            "Build user profile customization feature",
            "Release v2.0 to production servers",
            "Make layout work on mobile devices",
            "Compress images to reduce page load time",
            "Create REST endpoints for task management",
            "Setup GitHub Actions for automated testing",
            "Run security scan on codebase"
        ]
        
        statuses = ["pending", "completed", "in_progress", "cancelled"]
        user_ids = [1, 2, 3, 4, 5]
        
        # Create root element
        root = ET.Element('tasks')
        root.set('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance')
        root.set('xsi:noNamespaceSchemaLocation', 'tasks.xsd')
        
        # Generate tasks
        base_date = datetime.now() - timedelta(days=30)
        
        for i in range(min(num_tasks, len(sample_titles))):
            task = ET.SubElement(root, 'task')
            
            ET.SubElement(task, 'id').text = str(i + 1)
            ET.SubElement(task, 'user_id').text = str(random.choice(user_ids))
            ET.SubElement(task, 'title').text = sample_titles[i]
            ET.SubElement(task, 'description').text = sample_descriptions[i]
            ET.SubElement(task, 'status').text = random.choice(statuses)
            
            # Vary creation dates
            task_date = base_date + timedelta(days=random.randint(0, 30))
            ET.SubElement(task, 'created_at').text = task_date.isoformat()
        
        return root
    
    def save_sample_data(self, num_tasks=15):
        """Generate and save sample data"""
        try:
            print(f"Generating {num_tasks} sample tasks...")
            root = self.generate_tasks(num_tasks)
            
            # Pretty print
            tree = ET.ElementTree(root)
            self._indent(root)
            
            tree.write(str(self.xml_path), encoding='utf-8', xml_declaration=True)
            print(f"✓ Sample data saved to {self.xml_path}")
            return True
            
        except Exception as e:
            print(f"❌ Error saving sample data: {e}")
            return False
    
    @staticmethod
    def _indent(elem, level=0):
        """Pretty print XML"""
        indent = "\n" + level * "  "
        
        if len(elem):
            if not elem.text or not elem.text.strip():
                elem.text = indent + "  "
            if not elem.tail or not elem.tail.strip():
                elem.tail = indent
            
            for child in elem:
                SampleDataGenerator._indent(child, level + 1)
            
            if not child.tail or not child.tail.strip():
                child.tail = indent
        else:
            if level and (not elem.tail or not elem.tail.strip()):
                elem.tail = indent


def main():
    """Main entry point"""
    print("\n" + "="*60)
    print("SAMPLE DATA GENERATOR FOR TASKS.XML")
    print("="*60 + "\n")
    
    generator = SampleDataGenerator()
    
    # Generate 15 sample tasks
    generator.save_sample_data(num_tasks=15)
    
    print("\n✓ Ready to use with xml_display.py and xml_advanced.py")
    print("  Run: python xml_display.py")
    print("  Run: python xml_advanced.py\n")


if __name__ == "__main__":
    main()
