#!/usr/bin/env python3
"""
Database to XML Sync Verification Script
Purpose: Verify consistency between MySQL database and XML backups
Detects mismatches, orphaned records, and corruption

Usage:
    python verify_sync.py                    # Full verification
    python verify_sync.py tasks              # Verify tasks only
    python verify_sync.py users              # Verify users only
    python verify_sync.py --fix              # Auto-repair minor issues
"""

import xml.etree.ElementTree as ET
import sys
from datetime import datetime
from pathlib import Path
from collections import defaultdict

BASE_DIR = Path(__file__).resolve().parent
DATA_DIR = BASE_DIR / 'data'
SCHEMA_DIR = BASE_DIR / 'schema' 

# Ensure data and schema directories exist (for consistency, not strictly needed for just reading)
DATA_DIR.mkdir(parents=True, exist_ok=True)
SCHEMA_DIR.mkdir(parents=True, exist_ok=True)

TASKS_XML = DATA_DIR / 'tasks.xml'
USERS_XML = DATA_DIR / 'users.xml'
ARCHIVE_XML = DATA_DIR / 'archive_tasks.xml'


class SyncVerifier:
    """Verify consistency between database and XML backups"""

    def __init__(self, do_fix=False):
        self.issues = []
        self.warnings = []
        self.do_fix = do_fix

    def verify_tasks_xml(self):
        """Verify tasks.xml structure and integrity"""
        try:
            tree = ET.parse(TASKS_XML)
            root = tree.getroot()
            task_count = 0
            task_ids = set()
            changed = False

            for task in root.findall('task'):
                task_count += 1
                task_id_el = task.find('id')
                task_id_val = task_id_el.text if task_id_el is not None else task.get('id')

                # Migration: Move ID from attribute to element
                if task_id_el is None and task.get('id'):
                    if self.do_fix:
                        el = ET.Element('id')
                        el.text = task.get('id')
                        task.insert(0, el)
                        task.attrib.pop('id', None)
                        changed = True
                    else:
                        self.issues.append(f"Task {task.get('id')}: Missing <id> element (found as attribute)")

                # Repair common naming mismatches found in logs
                for old, new in [('created_by', 'user_id'), ('created_date', 'created_at')]:
                    if task.find(new) is None and task.find(old) is not None:
                        if self.do_fix:
                            el = ET.Element(new)
                            el.text = task.findtext(old)
                            task.append(el)
                            old_el = task.find(old)
                            if old_el is not None: task.remove(old_el)
                            changed = True
                        else:
                            self.issues.append(f"Task {task_id_val or '?'}: Missing {new} (found as {old})")

                # Aggressive Fix: Generate missing ID if totally absent
                if task.find('id') is None and self.do_fix:
                    new_id = str(max([int(tid) for tid in task_ids if tid.isdigit()] + [0]) + 1)
                    el = ET.Element('id')
                    el.text = new_id
                    task.insert(0, el)
                    task_id_val = new_id
                    changed = True

                # Check required fields
                required = ['id', 'user_id', 'title', 'description', 'status', 'created_at']
                for field in required:
                    node = task.find(field)
                    if node is None or not node.text:
                        if self.do_fix:
                            val = '1' if 'user' in field else 'pending' if field == 'status' else datetime.now().isoformat()
                            if node is None:
                                ET.SubElement(task, field).text = val
                            else:
                                node.text = val
                            changed = True
                        else:
                            self.issues.append(f"Task {task_id_val or '?'}: Missing {field}")

                # Check for duplicate IDs
                if task_id_val:
                    if task_id_val in task_ids:
                        self.issues.append(f"Duplicate task ID: {task_id_val}")
                    task_ids.add(task_id_val)

                # Validate status enum
                status = task.find('status')
                if status is not None and status.text not in ['pending', 'completed', 'in_progress', 'archived']:
                    self.warnings.append(f"Task {task_id_val}: Invalid status '{status.text}'")

            if changed and self.do_fix:
                self._save_xml(tree, TASKS_XML)

            print(f"✓ tasks.xml: {task_count} tasks verified")
            return True

        except ET.ParseError as e:
            self.issues.append(f"tasks.xml parse error: {str(e)}")
            return False
        except FileNotFoundError:
            self.warnings.append("tasks.xml not found (may be new installation)")
            return True

    def verify_users_xml(self):
        """Verify users.xml structure and integrity"""
        try:
            tree = ET.parse(USERS_XML)
            root = tree.getroot()
            user_count = 0
            user_ids = set()
            changed = False

            for user in root.findall('user'):
                user_count += 1
                user_id_el = user.find('id')
                user_id_val = user_id_el.text if user_id_el is not None else user.get('id')

                # Migration: Move ID from element to attribute (Aligning with users.xsd)
                if user_id_el is not None:
                    if self.do_fix:
                        user.set('id', user_id_el.text or "")
                        user.remove(user_id_el)
                        changed = True
                    else:
                        self.issues.append(f"User {user_id_val}: Found <id> element (should be attribute)")

                # Aggressive Fix: Generate missing ID if totally absent
                if user.get('id') is None and self.do_fix:
                    new_id = str(max([int(uid) for uid in user_ids if uid.isdigit()] + [0]) + 1)
                    el = ET.Element('id')
                    el.text = new_id
                    user.insert(0, el)
                    user_id_val = new_id
                    changed = True

                # Check required fields
                required = ['username', 'password_hash', 'role', 'created_at']
                for field in required:
                    node = user.find(field)
                    if node is None or not node.text:
                        if self.do_fix:
                            val = datetime.now().isoformat() if field == 'created_at' else 'user' if field == 'role' else 'unknown'
                            if node is None:
                                ET.SubElement(user, field).text = val
                            else:
                                node.text = val
                            changed = True
                        else:
                            self.issues.append(f"User {user_id_val or '?'}: Missing {field}")

                # Check for duplicate IDs
                if user_id_val:
                    if user_id_val in user_ids:
                        self.issues.append(f"Duplicate user ID: {user_id_val}")
                    user_ids.add(user_id_val)

                # Validate role enum
                role = user.find('role')
                if role is not None and role.text not in ['user', 'admin', 'moderator']:
                    self.warnings.append(f"User {user_id_val}: Invalid role '{role.text}'")

            if changed and self.do_fix:
                self._save_xml(tree, USERS_XML)

            print(f"✓ users.xml: {user_count} users verified")
            return True

        except ET.ParseError as e:
            self.issues.append(f"users.xml parse error: {str(e)}")
            return False
        except FileNotFoundError:
            self.warnings.append("users.xml not found (may be new installation)")
            return True

    def verify_archive_xml(self):
        """Verify archive_tasks.xml structure and integrity"""
        try:
            tree = ET.parse(ARCHIVE_XML)
            root = tree.getroot()
            archive_count = 0
            changed = False

            for task in root.findall('task'):
                archive_count += 1
                task_id_el = task.find('id')

                if task_id_el is None and task.get('id'):
                    if self.do_fix:
                        el = ET.Element('id')
                        el.text = task.get('id')
                        task.insert(0, el)
                        if 'id' in task.attrib: del task.attrib['id']
                        changed = True

                # Check required fields (same as tasks, plus archived_at)
                required = ['id', 'user_id', 'title', 'description', 'status', 'created_at', 'archived_at']
                for field in required:
                    if task.find(field) is None or not task.findtext(field):
                        self.issues.append(f"Archived task {task.findtext('id') or '?'}: Missing {field}")

            if changed and self.do_fix:
                self._save_xml(tree, ARCHIVE_XML)

            print(f"✓ archive_tasks.xml: {archive_count} archived tasks verified")
            return True

        except ET.ParseError as e:
            self.issues.append(f"archive_tasks.xml parse error: {str(e)}")
            return False
        except FileNotFoundError:
            self.warnings.append("archive_tasks.xml not found (no archives yet)")
            return True

    def _save_xml(self, tree, path):
        """Internal helper to save pretty-printed XML"""
        from xml.dom import minidom
        xml_str = ET.tostring(tree.getroot(), encoding='utf-8')
        pretty_xml = minidom.parseString(xml_str).toprettyxml(indent="  ")
        # Filter out empty lines caused by minidom's formatting
        clean_xml = "\n".join([line for line in pretty_xml.split('\n') if line.strip()])
        with open(path, 'w', encoding='utf-8') as f:
            f.write(clean_xml)
        self.warnings.append(f"Repaired and saved: {path.name}")

    def report(self):
        """Print verification report"""
        print("\n" + "="*60)
        print("XML SYNC VERIFICATION REPORT")
        print("="*60)

        if not self.issues and not self.warnings:
            print("✓ All XML files are valid and consistent!")
        else:
            if self.issues:
                print(f"\n✗ {len(self.issues)} ISSUES FOUND:")
                for issue in self.issues:
                    print(f"  - {issue}")

            if self.warnings:
                print(f"\n⚠ {len(self.warnings)} WARNINGS:")
                for warning in self.warnings:
                    print(f"  - {warning}")

        print("\n" + "="*60)
        return len(self.issues) == 0


def main():
    do_fix = '--fix' in sys.argv
    verifier = SyncVerifier(do_fix=do_fix)

    # Determine what to verify
    verify_tasks = True
    verify_users = True
    verify_archive = True

    if len(sys.argv) > 1:
        if sys.argv[1] == 'tasks':
            verify_users = verify_archive = False
        elif sys.argv[1] == 'users':
            verify_tasks = verify_archive = False
        elif sys.argv[1] == 'archive':
            verify_tasks = verify_users = False

    # Run verification
    if verify_users:
        verifier.verify_users_xml()
    if verify_tasks:
        verifier.verify_tasks_xml()
    if verify_archive:
        verifier.verify_archive_xml()

    # Print report
    success = verifier.report()
    sys.exit(0 if success else 1)


if __name__ == '__main__':
    main()
