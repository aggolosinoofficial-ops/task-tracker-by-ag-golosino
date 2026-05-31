#!/usr/bin/env python3
"""
XML XML Manipulation Utilities - Compact Edition
Purpose: Fast, lightweight scripts for XML CRUD operations
Optimized for 2GB RAM systems - minimal dependencies

Usage:
    python3 xml_handler.py add-task <id> <user_id> <title> <description> <status>
    python3 xml_handler.py edit-task <id> <title> <description> <status>
    python3 xml_handler.py delete-task <id>
    python3 xml_handler.py add-user <id> <username> <password_hash> <role>
    python3 xml_handler.py validate <file>
"""

import sys
import xml.etree.ElementTree as ET
from datetime import datetime
from pathlib import Path

# Configuration
BASE_DIR = Path(__file__).parent
TASKS_XML = BASE_DIR / 'tasks.xml'
TASKS_XSD = BASE_DIR / 'tasks.xsd'
USERS_XML = BASE_DIR / 'users.xml'
USERS_XSD = BASE_DIR / 'users.xsd'
ARCHIVE_XML = BASE_DIR / 'archive_tasks.xml'
ARCHIVE_XSD = BASE_DIR / 'archive_tasks.xsd'

class XMLHandler:
    """Lightweight XML handler for tasks and users - optimized for speed"""

    @staticmethod
    def add_task(task_id, user_id, title, description, status, created_at=None):
        """Add new task to tasks.xml"""
        try:
            tree = ET.parse(TASKS_XML)
            root = tree.getroot()
        except FileNotFoundError:
            root = ET.Element('tasks')
            tree = ET.ElementTree(root)

        # Create task element
        task = ET.Element('task')
        ET.SubElement(task, 'id').text = str(task_id)
        ET.SubElement(task, 'user_id').text = str(user_id)
        ET.SubElement(task, 'title').text = title
        ET.SubElement(task, 'description').text = description
        ET.SubElement(task, 'status').text = status
        ET.SubElement(task, 'created_at').text = created_at or datetime.now().isoformat()

        root.append(task)
        tree.write(TASKS_XML, encoding='UTF-8', xml_declaration=True)
        return True

    @staticmethod
    def edit_task(task_id, title=None, description=None, status=None):
        """Edit existing task in tasks.xml"""
        try:
            tree = ET.parse(TASKS_XML)
            root = tree.getroot()
        except FileNotFoundError:
            return False

        # Find task by ID
        for task in root.findall('task'):
            if task.find('id').text == str(task_id):
                if title:
                    task.find('title').text = title
                if description:
                    task.find('description').text = description
                if status:
                    task.find('status').text = status
                tree.write(TASKS_XML, encoding='UTF-8', xml_declaration=True)
                return True
        return False

    @staticmethod
    def delete_task(task_id):
        """Remove task from tasks.xml (typically moved to archive first)"""
        try:
            tree = ET.parse(TASKS_XML)
            root = tree.getroot()
        except FileNotFoundError:
            return False

        # Find and remove task
        for task in root.findall('task'):
            if task.find('id').text == str(task_id):
                root.remove(task)
                tree.write(TASKS_XML, encoding='UTF-8', xml_declaration=True)
                return True
        return False

    @staticmethod
    def archive_task(task_id):
        """Move task from tasks.xml to archive_tasks.xml"""
        try:
            # Load source and destination
            tasks_tree = ET.parse(TASKS_XML)
            tasks_root = tasks_tree.getroot()

            try:
                archive_tree = ET.parse(ARCHIVE_XML)
                archive_root = archive_tree.getroot()
            except FileNotFoundError:
                archive_root = ET.Element('tasks')
                archive_tree = ET.ElementTree(archive_root)

            # Find task in active tasks
            for task in tasks_root.findall('task'):
                if task.find('id').text == str(task_id):
                    # Copy to archive
                    archived_task = ET.Element('task')
                    for child in task:
                        archived_task.append(ET.Element(child.tag))
                        archived_task.find(child.tag).text = child.text

                    # Add archive timestamp
                    ET.SubElement(archived_task, 'archived_at').text = datetime.now().isoformat()

                    archive_root.append(archived_task)
                    archive_tree.write(ARCHIVE_XML, encoding='UTF-8', xml_declaration=True)

                    # Remove from active tasks
                    tasks_root.remove(task)
                    tasks_tree.write(TASKS_XML, encoding='UTF-8', xml_declaration=True)
                    return True
        except Exception as e:
            print(f"Error archiving task: {e}")
            return False

    @staticmethod
    def add_user(user_id, username, password_hash, role='user'):
        """Add new user to users.xml"""
        try:
            tree = ET.parse(USERS_XML)
            root = tree.getroot()
        except FileNotFoundError:
            root = ET.Element('users')
            tree = ET.ElementTree(root)

        # Create user element
        user = ET.Element('user')
        ET.SubElement(user, 'id').text = str(user_id)
        ET.SubElement(user, 'username').text = username
        ET.SubElement(user, 'password_hash').text = password_hash
        ET.SubElement(user, 'role').text = role
        ET.SubElement(user, 'created_at').text = datetime.now().isoformat()

        root.append(user)
        tree.write(USERS_XML, encoding='UTF-8', xml_declaration=True)
        return True

    @staticmethod
    def validate_xml(xml_file, xsd_file=None):
        """Validate XML file structure (basic validation without external libs)"""
        try:
            tree = ET.parse(xml_file)
            root = tree.getroot()

            # Basic structure validation
            if xml_file == TASKS_XML:
                required_fields = ['id', 'user_id', 'title', 'description', 'status', 'created_at']
                for task in root.findall('task'):
                    for field in required_fields:
                        if task.find(field) is None:
                            return False, f"Missing field: {field}"
            elif xml_file == USERS_XML:
                required_fields = ['id', 'username', 'password_hash', 'role', 'created_at']
                for user in root.findall('user'):
                    for field in required_fields:
                        if user.find(field) is None:
                            return False, f"Missing field: {field}"

            return True, "XML structure is valid"
        except ET.ParseError as e:
            return False, f"XML parsing error: {str(e)}"
        except Exception as e:
            return False, f"Validation error: {str(e)}"


def main():
    """Command-line interface for XML operations"""
    if len(sys.argv) < 2:
        print(__doc__)
        return

    command = sys.argv[1].lower()
    handler = XMLHandler()

    try:
        if command == 'add-task' and len(sys.argv) >= 7:
            result = handler.add_task(sys.argv[2], sys.argv[3], sys.argv[4], sys.argv[5], sys.argv[6])
            print(f"{'✓ Task added' if result else '✗ Failed to add task'}")

        elif command == 'edit-task' and len(sys.argv) >= 4:
            result = handler.edit_task(sys.argv[2], sys.argv[3] if len(sys.argv) > 3 else None,
                                      sys.argv[4] if len(sys.argv) > 4 else None,
                                      sys.argv[5] if len(sys.argv) > 5 else None)
            print(f"{'✓ Task updated' if result else '✗ Failed to update task'}")

        elif command == 'delete-task' and len(sys.argv) >= 3:
            result = handler.delete_task(sys.argv[2])
            print(f"{'✓ Task deleted' if result else '✗ Failed to delete task'}")

        elif command == 'archive-task' and len(sys.argv) >= 3:
            result = handler.archive_task(sys.argv[2])
            print(f"{'✓ Task archived' if result else '✗ Failed to archive task'}")

        elif command == 'add-user' and len(sys.argv) >= 6:
            result = handler.add_user(sys.argv[2], sys.argv[3], sys.argv[4],
                                     sys.argv[5] if len(sys.argv) > 5 else 'user')
            print(f"{'✓ User added' if result else '✗ Failed to add user'}")

        elif command == 'validate' and len(sys.argv) >= 3:
            valid, message = handler.validate_xml(Path(sys.argv[2]))
            print(f"{'✓' if valid else '✗'} {message}")

        else:
            print(f"Unknown command: {command}")
            print(__doc__)

    except Exception as e:
        print(f"✗ Error: {str(e)}", file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
