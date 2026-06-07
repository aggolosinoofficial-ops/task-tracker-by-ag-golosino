#!/usr/bin/env python3
"""
Cleanup script to remove obsolete example and documentation files
"""
import os
import sys

# Files to delete
files_to_delete = [
    # EXAMPLE files
    

    # Main Application PHP Files
        
    "database_integrity_check.php",
    "database_setup_core.php",
    "admin_promotion.php",
    "debug_form.php",
    "admin_setup.php",
    # Logic and Configuration
    

    # Old documentation
    
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
