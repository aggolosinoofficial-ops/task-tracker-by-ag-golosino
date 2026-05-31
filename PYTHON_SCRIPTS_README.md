# Python XML Scripts - User Guide

**Purpose:** Lightweight, fast Python utilities for XML manipulation and verification  
**Target Platform:** 2GB RAM systems (minimal dependencies)  
**Requirements:** Python 3.6+, no external packages needed (uses only stdlib)

---

## Overview

These scripts provide command-line tools for managing and verifying XML data files that back up your To-Do app's database.

### Files:

| Script | Purpose | Use Case |
|--------|---------|----------|
| `xml_handler.py` | Add/Edit/Delete/Archive tasks and users | Bulk operations, manual corrections |
| `verify_sync.py` | Verify XML consistency | QA, troubleshooting, integrity checks |

---

## Installation

No installation needed! Just use Python 3.6+:

```bash
# Make scripts executable (Linux/Mac)
chmod +x xml_handler.py verify_sync.py

# Run directly
python3 xml_handler.py --help
python3 verify_sync.py --help
```

---

## xml_handler.py - XML Operations

### Add a Task

```bash
python3 xml_handler.py add-task <id> <user_id> <title> <description> <status>
```

**Example:**
```bash
python3 xml_handler.py add-task 100 5 "Buy groceries" "Milk, eggs, bread" pending
```

**Parameters:**
- `<id>`: Unique task number (integer, e.g., 100)
- `<user_id>`: Owner user ID (integer, e.g., 5)
- `<title>`: Task name (string, max 255 chars)
- `<description>`: Task details (string)
- `<status>`: "pending" or "completed"

**Creates:** Entry in `tasks.xml`

---

### Edit a Task

```bash
python3 xml_handler.py edit-task <id> [title] [description] [status]
```

**Example:**
```bash
python3 xml_handler.py edit-task 100 "Buy groceries" "Milk, eggs, bread, butter" completed
```

**Parameters:**
- `<id>`: Task ID to edit (required)
- `[title]`: New title (optional, blank = keep old)
- `[description]`: New description (optional)
- `[status]`: New status (optional)

**Note:** Provide empty string to skip a field

---

### Delete a Task

```bash
python3 xml_handler.py delete-task <id>
```

**Example:**
```bash
python3 xml_handler.py delete-task 100
```

**Removes:** Entry from `tasks.xml` permanently

---

### Archive a Task

```bash
python3 xml_handler.py archive-task <id>
```

**Example:**
```bash
python3 xml_handler.py archive-task 100
```

**Effect:**
- Removes task from `tasks.xml`
- Adds task to `archive_tasks.xml`
- Adds `archived_at` timestamp

---

### Add a User

```bash
python3 xml_handler.py add-user <id> <username> <password_hash> [role]
```

**Example:**
```bash
python3 xml_handler.py add-user 10 "john_doe" "$2y$10$..." user
```

**Parameters:**
- `<id>`: User ID (integer)
- `<username>`: Username (3-50 chars, letters/numbers/_- only)
- `<password_hash>`: Bcrypt hash from database (starts with `$2y$`)
- `[role]`: "user" (default) or "admin"

**Creates:** Entry in `users.xml`

---

### Validate XML

```bash
python3 xml_handler.py validate <file>
```

**Example:**
```bash
python3 xml_handler.py validate tasks.xml
python3 xml_handler.py validate users.xml
python3 xml_handler.py validate archive_tasks.xml
```

**Checks:**
- XML is well-formed (parseable)
- Required fields present
- Data types valid
- Field values not empty

---

## verify_sync.py - Verification Tool

### Full Verification

```bash
python3 verify_sync.py
```

**Checks all XML files:**
- `users.xml`
- `tasks.xml`
- `archive_tasks.xml`

**Validates:**
- Correct XML structure
- No duplicate IDs
- Required fields present
- Valid enums (status, role)
- Data integrity

---

### Verify Specific File Type

```bash
python3 verify_sync.py tasks       # Check tasks.xml only
python3 verify_sync.py users       # Check users.xml only
python3 verify_sync.py archive     # Check archive_tasks.xml only
```

---

### Example Output

**Success:**
```
✓ users.xml: 5 users verified
✓ tasks.xml: 42 tasks verified
✓ archive_tasks.xml: 8 archived tasks verified

============================================================
XML SYNC VERIFICATION REPORT
============================================================
✓ All XML files are valid and consistent!

============================================================
```

**With Issues:**
```
✗ Duplicate task ID: 100
✗ Task 50: Missing status
⚠ Task 75: Invalid status 'in_progress'

============================================================
XML SYNC VERIFICATION REPORT
============================================================
✗ 2 ISSUES FOUND:
  - Duplicate task ID: 100
  - Task 50: Missing status

⚠ 1 WARNINGS:
  - Task 75: Invalid status 'in_progress'
```

---

## Practical Examples

### Scenario 1: Bulk Task Import

You have data from another app and want to add it to tasks.xml:

```bash
# Add multiple tasks
python3 xml_handler.py add-task 101 1 "Task 1" "Description 1" pending
python3 xml_handler.py add-task 102 1 "Task 2" "Description 2" pending
python3 xml_handler.py add-task 103 2 "Task 3" "Description 3" completed

# Verify they were added correctly
python3 verify_sync.py tasks
```

---

### Scenario 2: Archive Old Tasks

Move completed tasks older than 6 months to archive:

```bash
# Archive task 50
python3 xml_handler.py archive-task 50

# Verify task moved to archive
python3 verify_sync.py archive
```

---

### Scenario 3: Data Recovery

Database corrupted, but XML backup intact:

```bash
# Verify XML files are valid
python3 verify_sync.py

# If valid, restore from XML to database (manual process)
# 1. Read tasks from tasks.xml
# 2. INSERT into database
# 3. Verify sync
```

---

### Scenario 4: Regular Maintenance

Run weekly integrity check:

```bash
# Create simple cron job (Linux/Mac)
# 0 2 * * 0 python3 /path/to/verify_sync.py >> /var/log/todo-sync.log

# Or Windows scheduled task
# Add python3 verify_sync.py to Task Scheduler running Sundays at 2 AM
```

---

## Performance Notes

### Memory Usage:
- `xml_handler.py`: ~5-10 MB (entire XML file loaded into RAM)
- `verify_sync.py`: ~5-10 MB (reads and validates XML)
- Both scale linearly with file size

### Speed:
- Add task: ~50-100ms
- Edit task: ~50-100ms
- Delete task: ~50-100ms
- Archive task: ~100-150ms (read from two files)
- Verify sync: ~200-300ms (full check)

**For 2GB RAM systems:** No issues with typical usage (~10,000 tasks)

---

## Troubleshooting

### Script won't run

```bash
# Error: "No such file or directory"
# Solution: Make sure you're in the correct directory
cd /path/to/todo-app
python3 xml_handler.py validate tasks.xml

# Error: "ModuleNotFoundError"
# Solution: Uses only stdlib (xml.etree, pathlib, collections)
# Check Python version: python3 --version (need 3.6+)
```

### XML validation fails

```bash
# Error: XML parsing error
# Solution: Check file isn't corrupted
# Restore from backup: cp tasks.xml.bak tasks.xml

# Error: Missing required fields
# Solution: File format changed, re-generate from database
# Run register or task add through web interface
```

### Commands not working

```bash
# Check file paths
# Scripts assume they're in the same directory as XML files

# Example structure:
/var/www/html/todo-app/
    ├── xml_handler.py
    ├── verify_sync.py
    ├── tasks.xml
    ├── users.xml
    └── archive_tasks.xml
```

---

## Security Notes

⚠️ **Important:** These scripts directly modify XML files:

1. **Backup first:** Always backup XML files before running scripts
   ```bash
   cp tasks.xml tasks.xml.backup
   cp users.xml users.xml.backup
   ```

2. **Restricted access:** Only run scripts as web server user or admin
   ```bash
   chmod 750 xml_handler.py verify_sync.py
   ```

3. **Verify after:** Always verify after modifications
   ```bash
   python3 verify_sync.py
   ```

4. **No direct password input:** Never pass raw passwords
   - Always use bcrypt hashes (from database)
   - Start with `$2y$` for validity

---

## Technical Details

### XML Structure

**tasks.xml:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<tasks>
  <task>
    <id>1</id>
    <user_id>1</user_id>
    <title>Example Task</title>
    <description>Task details here</description>
    <status>pending</status>
    <created_at>2026-05-31T14:30:00</created_at>
  </task>
</tasks>
```

**users.xml:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<users>
  <user>
    <id>1</id>
    <username>admin123</username>
    <password_hash>$2y$10$DIsG67LtQ...</password_hash>
    <role>admin</role>
    <created_at>2026-05-31T14:30:00</created_at>
  </user>
</users>
```

### Why ElementTree?

- Part of Python standard library (no external dependencies)
- Fast XML parsing for files up to several MB
- Memory efficient (uses iterparse for large files)
- Perfect for 2GB RAM constraint

---

## Future Enhancements

Possible additions (not in current version):

- [ ] JSON export/import
- [ ] CSV bulk import
- [ ] Database restore from XML
- [ ] Compression support
- [ ] Encryption support

---

## Support

For issues:

1. Check `verify_sync.py` for data integrity
2. Review error messages carefully
3. Consult `TROUBLESHOOTING_GUIDE.md`
4. Check `DEVELOPER_REFERENCE.md` for architecture

---

**Last Updated:** May 31, 2026  
**Python Version:** 3.6+  
**Status:** Stable
