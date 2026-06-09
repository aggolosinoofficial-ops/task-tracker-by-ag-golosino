#!/usr/bin/env python3
"""
Cleanup script to remove obsolete example and documentation files
"""
import os
import sys

# Files to delete
files_to_delete = [
    # Legacy PHP files (Replaced by Python Flask app)
    "config.php",
    "config_xml.php",
    "db.php",
    "db_adapter.php",
    "storage_adapter.php",
    "xml_storage_core.php",
    "login.php",
    "register.php",
    "admin_create.php",
    "auth_check.php",
    "validation.php",
    "add_task.php",
    "get_tasks.php",
    "edit_task.php",
    "delete_task.php",
    "toggle_task.php",
    "archive_task.php",
    "restore_task.php",
    "xml_sync_handler.php",
    "run_sync.php",
    "system_check.php",
    "get_csrf_token.php",
    "logout.php",
    "test_connection.php",
    "debug_task_form.php",
    "test_admin_xml_only.php",
    "database_integrity_check.php",
    "database_setup_core.php",
    "admin_promotion.php",
    "debug_form.php",
    "admin_setup.php",
    
    # Temporary or Inconsistent Python/Text files
    "tofix.txt",
    "weeklymaintenamnce.md", # Legacy documentation
    "sync_queue.json", # Legacy sync file from MySQL era
    "sync_worker.py", # No longer needed after removing pymysql
    "migrate_from_mysql.py", # No longer needed after moving out of XAMPP
    "xml_handler.php", # PHP XML handler, replaced by Python XMLService
    "tasks.xsd", # PHP XSD schema, replaced by Python XSD validation
    "users.xsd", # PHP XSD schema, replaced by Python XSD validation
    "archive_tasks.xsd", # PHP XSD schema, replaced by Python XSD validation
    "XML_FIRST_ARCHITECTURE_ANALYSIS.md",
    "XML_FIRST_ARCHITECTURE_SUMMARY.md",
    "EXECUTIVE_SUMMARY.md",
    "SYSTEM_OPTIMIZATION_SUMMARY.md",
    "TROUBLESHOOTING_GUIDE.md",
    "TROUBLESHOOTING_NOTIFICATIONS.md"
]

# Get current directory
base_dir = os.path.dirname(os.path.abspath(__file__))

deleted_count = 0
error_count = 0

for filename in files_to_delete:
    filepath = os.path.join(base_dir, filename)
    if os.path.exists(filepath):
        try:
            os.remove(filepath)
            print(f"✓ Deleted: {filename}")
            deleted_count += 1
        except Exception as e:
            print(f"✗ Error deleting {filename}: {e}")
            error_count += 1
    else:
        print(f"- Not found: {filename}")

print(f"\n✓ Successfully deleted: {deleted_count} files")
if error_count > 0:
    print(f"✗ Errors: {error_count} files")

sys.exit(0)
