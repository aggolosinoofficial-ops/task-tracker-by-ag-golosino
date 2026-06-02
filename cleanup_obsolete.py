#!/usr/bin/env python3
"""
Cleanup script to remove obsolete example and documentation files
"""
import os
import sys

# Files to delete
files_to_delete = [
    # EXAMPLE files
    "EXAMPLE_add_task_modified.php",
    "EXAMPLE_delete_task_modified.php",
    "EXAMPLE_get_tasks_modified.php",
    "EXAMPLE_toggle_task_modified.php",
    
    # Old documentation
    "BEFORE_AFTER_COMPARISON.md",
    "BEFORE_AFTER_EXACT_CHANGES.md",
    "CODEBASE_ANALYSIS_COMPREHENSIVE.md",
    "DETAILED_CHANGE_LOG.md",
    "PATCH_0.5.md",
    "PATCH_0.5_COMPLETION_SUMMARY.md",
    "PATCH_REGISTRATION_SECURITY_OPTIMIZATION.md",
    "PATCH_SUMMARY.md",
    "PATCH_XML_FIRST_ARCHITECTURE.md",
    "QUICK_REFERENCE.md",
    "QUICK_REFERENCE_TESTING.md",
    "QUICK_START.md",
    "QUICK_START_PATCH.md",
    "QUICK_START_VERIFICATION.md",
    "README_XML_FIRST_STORAGE.md",
    "IMPLEMENTATION_COMPLETE.md",
    "IMPLEMENTATION_COMPLETE_SUMMARY.md",
    "IMPLEMENTATION_FIXES_SUMMARY.md",
    "IMPLEMENTATION_SUMMARY.md",
    "SECURITY_IMPROVEMENTS.md",
    "TASK_SAVING_FIX.md",
    "NOTIFICATION_FIX.md",
    "OPTIMIZATION_REPORT.md",
    "SECURITY_FIXES_SUMMARY.md",
    "SYSTEM_COMPLETION_SUMMARY.md",
    "FINAL_SUMMARY.md",
    "FIXES_APPLIED.md",
    "MIGRATION_GUIDE_XML_FIRST.md",
    "DEVELOPER_REFERENCE.md",
    "INTEGRATION_EXAMPLES.md",
    "JWT_IMPLEMENTATION.md",
    "PYTHON_SCRIPTS_README.md",
    "PYTHON_XML_SCRIPTS_README.md",
    "DATABASE_XML_SYNC.md",
    "AUTHENTICATION_SETUP.md",
    "ADMIN_ACCOUNT_SETUP.md",
    "ADMIN_SETUP_GUIDE.md",
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
