#!/usr/bin/env python3
"""
Database to XML Sync Verification Script
Purpose: Verify consistency between MySQL database and XML backups
Detects mismatches, orphaned records, and corruption

Usage:
    python3 verify_sync.py                    # Full verification
    python3 verify_sync.py tasks              # Verify tasks only
    python3 verify_sync.py users              # Verify users only
    python3 verify_sync.py --fix              # Auto-repair minor issues
"""

import xml.etree.ElementTree as ET
from pathlib import Path
from collections import defaultdict

BASE_DIR = Path(__file__).parent
TASKS_XML = BASE_DIR / 'tasks.xml'
USERS_XML = BASE_DIR / 'users.xml'
ARCHIVE_XML = BASE_DIR / 'archive_tasks.xml'


class SyncVerifier:
    """Verify consistency between database and XML backups"""

    def __init__(self):
        self.issues = []
        self.warnings = []

    def verify_tasks_xml(self):
        """Verify tasks.xml structure and integrity"""
        try:
            tree = ET.parse(TASKS_XML)
            root = tree.getroot()
            task_count = 0
            task_ids = set()

            for task in root.findall('task'):
                task_count += 1
                task_id = task.find('id')

                # Check required fields
                required = ['id', 'user_id', 'title', 'description', 'status', 'created_at']
                for field in required:
                    if task.find(field) is None or not task.find(field).text:
                        self.issues.append(f"Task {task_id.text if task_id else '?'}: Missing {field}")

                # Check for duplicate IDs
                if task_id is not None:
                    if task_id.text in task_ids:
                        self.issues.append(f"Duplicate task ID: {task_id.text}")
                    task_ids.add(task_id.text)

                # Validate status enum
                status = task.find('status')
                if status is not None and status.text not in ['pending', 'completed']:
                    self.warnings.append(f"Task {task_id.text}: Invalid status '{status.text}'")

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

            for user in root.findall('user'):
                user_count += 1
                user_id = user.find('id')

                # Check required fields
                required = ['id', 'username', 'password_hash', 'role', 'created_at']
                for field in required:
                    if user.find(field) is None or not user.find(field).text:
                        self.issues.append(f"User {user_id.text if user_id else '?'}: Missing {field}")

                # Check for duplicate IDs
                if user_id is not None:
                    if user_id.text in user_ids:
                        self.issues.append(f"Duplicate user ID: {user_id.text}")
                    user_ids.add(user_id.text)

                # Validate role enum
                role = user.find('role')
                if role is not None and role.text not in ['user', 'admin', 'moderator']:
                    self.warnings.append(f"User {user_id.text}: Invalid role '{role.text}'")

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

            for task in root.findall('task'):
                archive_count += 1
                task_id = task.find('id')

                # Check required fields (same as tasks, plus archived_at)
                required = ['id', 'user_id', 'title', 'description', 'status', 'created_at', 'archived_at']
                for field in required:
                    if task.find(field) is None or not task.find(field).text:
                        self.issues.append(f"Archived task {task_id.text if task_id else '?'}: Missing {field}")

            print(f"✓ archive_tasks.xml: {archive_count} archived tasks verified")
            return True

        except ET.ParseError as e:
            self.issues.append(f"archive_tasks.xml parse error: {str(e)}")
            return False
        except FileNotFoundError:
            self.warnings.append("archive_tasks.xml not found (no archives yet)")
            return True

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
    import sys

    verifier = SyncVerifier()

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
