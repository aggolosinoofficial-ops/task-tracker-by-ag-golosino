# TROUBLESHOOTING GUIDE

## Quick Diagnosis

**Use this flowchart to identify issues:**

```
Problem: Can't register?
├─ Error "Too many attempts"?
│  └─ Check DEV_MODE in config.php (should be true for testing)
└─ Registration succeeds but user not in users.xml?
   └─ Check XML file permissions (chmod 644)

Problem: Task not in tasks.xml?
├─ Task added to MySQL OK?
│  ├─ YES → Check xml_sync_handler.php is included
│  └─ NO → Check add_task.php for errors
├─ Check error log for [XMLSync] messages
└─ Check if tasks.xml file exists and is writable

Problem: Archived task not in archive_tasks.xml?
├─ Task deleted from MySQL OK?
│  ├─ YES → Check if syncArchiveTaskToXML() is called
│  └─ NO → Check delete_task.php for errors
├─ Check error log
└─ Check if archive_tasks.xml file exists

Problem: Memory usage still high?
├─ Check if pagination is working (50 tasks per page)
├─ Check if bcrypt cost is 10 (not 12)
└─ Restart Apache to clear PHP memory cache
```

---

## ERROR MESSAGES & SOLUTIONS

### 1. "Too many attempts. Please wait X minutes"

**Problem:** Rate limiting is blocking you

**Cause:** You've exceeded max attempts (default: 5 failed login attempts in 15 minutes)

**Solution:**

```
QUICK FIX:
1. Wait 15 minutes for lockout to expire
  OR
2. Enable DEV_MODE in config.php:
   define('DEV_MODE', true);

Then restart Apache:
   Windows XAMPP: Click "Restart" button
   Linux: sudo systemctl restart apache2
```

**Verify:**

```bash
# Check if DEV_MODE is enabled:
grep "DEV_MODE" config.php
# Should show: define('DEV_MODE', true);

# Check if Apache reloaded config:
# Try registering again - should work
```

---

### 2. "Database error: No such file or directory"

**Problem:** MySQL connection failed

**Cause:** Either MySQL is stopped or credentials are wrong

**Solution:**

```
QUICK FIX:
1. Check if MySQL is running (XAMPP Control Panel)
2. If stopped, click "Start" button
3. Wait 5 seconds
4. Try again

Verify in db.php:
- Host: localhost
- User: root
- Password: (empty for XAMPP)
- Database: test
```

**Debug:**

```bash
# Test MySQL connection:
mysql -u root -h localhost test

# If error, start MySQL:
# Windows XAMPP: Click Start button
# Linux: sudo systemctl start mysql
```

---

### 3. "notificationContainer not found!" (Console Warning)

**Problem:** Notification popup doesn't show, falls back to alert()

**Cause:** HTML page missing `<div id="notificationContainer"></div>`

**Solution:**

```html
<!-- Add this to every page that handles tasks (inside <body>, near top) -->
<body>
  <div id="notificationContainer"></div>
  <!-- Rest of page here -->
</body>
```

**Check which pages have it:**

```bash
grep -r "notificationContainer" *.php

# Should show all: index.php, tasks.php, insights.php, archive.php
```

---

### 4. "tasks.xml not found" or file not created

**Problem:** XML files not being created

**Cause:** Directory permissions or sync function not called

**Solution:**

```
QUICK FIX - Check directory permissions:
1. Make directory writable:
   chmod 755 /xampp/htdocs/to-do-app-by-ag-golosino/

2. Delete any corrupted XML files:
   rm -f tasks.xml users.xml archive_tasks.xml

3. Try again - files should auto-create on next operation

Verify permissions:
ls -la *.xml
# Should show: -rw-r--r-- (644 permissions)
```

**Debug:**

```bash
# Check if sync functions are being called:
grep -n "syncTaskToXML" add_task.php
# Should show function call

# Check if xml_sync_handler.php is included:
grep "include.*xml_sync_handler" add_task.php
# Should show the include statement
```

---

### 5. "Failed to archive task" error

**Problem:** Delete operation fails during XML sync

**Cause:** archive_tasks.xml validation failed or permission denied

**Solution:**

```
QUICK FIX:
1. Check if archive_tasks.xsd exists:
   ls -la archive_tasks.xsd

   If missing:
   - Verify it was created (from IMPLEMENTATION_FIXES_SUMMARY.md)
   - Or copy from another backup

2. Check XML file permissions:
   chmod 644 archive_tasks.xml

3. Check error log:
   tail -f /var/log/apache2/error.log
   # Look for [XMLSync] messages

4. Try delete again
```

**Debug:**

```bash
# Validate schema:
xmllint --schema archive_tasks.xsd archive_tasks.xml

# Check file size:
ls -lh archive_tasks.xml
# If 0 bytes, it's corrupted - delete and recreate:
# rm archive_tasks.xml
# Then delete any task to recreate it
```

---

### 6. "Task restored successfully" but task doesn't appear

**Problem:** Restore succeeds but task not in active list

**Cause:** Frontend not refreshing or XML out of sync

**Solution:**

```
QUICK FIX:
1. Refresh the page:
   F5 or Cmd+R

2. Check MySQL directly:
   SELECT * FROM tasks WHERE user_id = 1;
   # Should show the restored task

3. Check tasks.xml:
   grep "title" tasks.xml | grep "restored_task_title"
   # Should show the task

4. Check archive_tasks.xml:
   grep -c "restored_task_title" archive_tasks.xml
   # Should return 0 (removed from archive)

5. If still not showing:
   - Clear browser cache (Ctrl+Shift+Delete)
   - Restart Apache
```

---

### 7. "Schema validation failed" (in error log)

**Problem:** XML doesn't match XSD schema

**Cause:** Data format mismatch (e.g., invalid status value, missing field)

**Solution:**

```
QUICK FIX:
1. Check what validation failed:
   grep "schemaValidate" /var/log/apache2/error.log
   # Look for detailed error message

2. Validate XML manually:
   xmllint --schema tasks.xsd tasks.xml
   # Shows exact error line and column

3. Check if status values are valid:
   grep "<status>" tasks.xml
   # Should only show: pending, completed, in_progress, cancelled

   If invalid value found:
   - Find and fix in XML file (or delete and recreate)

4. Try operation again
```

**Example Fix:**

```xml
<!-- WRONG (typo in status) -->
<status>compleeted</status>

<!-- RIGHT (correct spelling) -->
<status>completed</status>
```

---

### 8. Memory usage keeps spiking on 2GB system

**Problem:** System still using lots of memory despite optimizations

**Cause:** Not using pagination or bcrypt cost still at 12

**Solution:**

```
QUICK FIX:
1. Check bcrypt cost in register.php:
   grep "password_hash" register.php
   # Should show: ['cost' => 10]
   # NOT: ['cost' => 12]

2. Check pagination in get_tasks.php:
   grep "LIMIT" get_tasks.php
   # Should show: LIMIT 50
   # NOT loading all tasks

3. Check default page size:
   grep "DEFAULT_PAGE_SIZE" config.php
   # Should show: 50

4. If all OK but still high:
   - Restart Apache to clear cached memory
   - Check if other processes running (ps aux)
   - Increase XAMPP PHP memory limit if needed
```

**Memory Monitoring:**

```bash
# Watch memory usage real-time:
watch -n 1 free -h

# If Apache using too much:
ps aux | grep apache
# See memory usage per process
```

---

### 9. XML files keep getting corrupted

**Problem:** XML becomes invalid or loses data

**Cause:** Concurrent access or improper cleanup on errors

**Solution:**

```
QUICK FIX - Rebuild XML from MySQL:
1. Backup current files:
   cp tasks.xml tasks.xml.bak
   cp users.xml users.xml.bak
   cp archive_tasks.xml archive_tasks.bak

2. Delete corrupted files:
   rm tasks.xml users.xml archive_tasks.xml

3. Recreate from database:
   - Option A: Perform one operation per table (add task, register user, delete task)
   - Option B: Run full sync script (if exists)

4. Verify new files:
   xmllint --schema tasks.xsd tasks.xml
   xmllint --schema users.xsd users.xml
   xmllint --schema archive_tasks.xsd archive_tasks.xml
```

**Prevent Corruption:**

```bash
# Monitor for corruption regularly:
# Add to crontab for daily validation:
0 2 * * * xmllint --schema /path/to/tasks.xsd /path/to/tasks.xml >> /var/log/xml_validation.log 2>&1

# If corruption detected in log:
# Restore from backup and investigate cause
```

---

### 10. "This username is already registered" (unexpected)

**Problem:** Can't register same user twice even after deletion

**Cause:** User in MySQL but not deleted properly, or trying same username

**Solution:**

```
QUICK FIX:
1. Use different username:
   testuser1, testuser2, testuser3, etc

2. If needed to use same username:
   DELETE FROM users WHERE username = 'testuser1';
   DELETE FROM users WHERE id = 1;  # Also delete from archive

3. Then try registering again with same name

Verify deletion:
SELECT * FROM users WHERE username = 'testuser1';
# Should return empty result
```

---

## PERFORMANCE BENCHMARKS

### On 2GB RAM System

**Expected Performance:**

```
Before Optimization:
- Registration: ~1-2 seconds (bcrypt cost 12)
- Load all tasks: ~5-10 seconds (no pagination)
- Memory usage: Can spike to 1.8GB
- CPU: High during hashing

After Optimization:
- Registration: ~300-500ms (bcrypt cost 10) ✓ 3-4x faster
- Load first 50 tasks: ~500ms (with pagination) ✓ 10x+ faster
- Memory usage: Stable ~300MB
- CPU: Manageable spikes
```

### If Not Meeting Performance Targets

**Check:**

```bash
# 1. Verify bcrypt cost is 10:
grep "cost" register.php

# 2. Verify pagination is working:
curl "http://localhost/get_tasks.php?page=1&limit=50" | wc -c
# If returning massive response, pagination not working

# 3. Monitor memory:
free -h
ps aux | grep php

# 4. Check Apache max processes:
grep "MaxRequestWorkers" /etc/apache2/apache2.conf
# Should be set low (20-30) on 2GB system
```

**Optimization Tweaks:**

```php
// In config.php, try lowering page size:
define('DEFAULT_PAGE_SIZE', 30);  // Instead of 50

// Or increase bcrypt cost only slightly:
['cost' => 9]  // If still too slow (though 10 is recommended)
```

---

## RECOVERY PROCEDURES

### If All Tasks Lost

**Step 1: Check if backup exists**

```bash
ls -la tasks.xml.bak archive_tasks.xml.bak
```

**Step 2: Restore from backup**

```bash
cp tasks.xml.bak tasks.xml
cp archive_tasks.xml.bak archive_tasks.xml
```

**Step 3: Verify restoration**

```bash
xmllint --schema tasks.xsd tasks.xml
# Should validate
```

### If MySQL Database Corrupted

**Step 1: Backup current state**

```bash
mysqldump -u root test > backup_corrupted_$(date +%Y%m%d).sql
```

**Step 2: Restore from XML**

```bash
# Manually recreate tables from XML data:
# 1. Parse XML file
# 2. INSERT statements for each entry
# 3. Or use custom script
```

### If Completely Lost

**Step 1: Check system backups**

```bash
# Check for any .bak or backup files:
find . -name "*.bak"
find . -name "*backup*"
```

**Step 2: Recreate from scratch**

```bash
# Reset database:
mysql -u root test < database_setup.php

# Create new users and tasks
# XML files will auto-create on first use
```

---

## TESTING CHECKLIST - BEFORE GOING PRODUCTION

```
☐ Set DEV_MODE = false in config.php
☐ Test registration with valid password (uppercase, number, special char)
☐ Verify user appears in users.xml
☐ Validate users.xml against users.xsd
☐ Add a task - verify in tasks.xml
☐ Validate tasks.xml against tasks.xsd
☐ Edit task - verify updated in tasks.xml
☐ Delete task - verify removed from tasks.xml
☐ Check archived task in archive_tasks.xml
☐ Validate archive_tasks.xml against archive_tasks.xsd
☐ Restore task - verify back in tasks.xml and removed from archive
☐ Check all notification popups show (not just alerts)
☐ Test pagination (load 50 tasks per page)
☐ Monitor memory usage on 2GB system
☐ Check error logs for [XMLSync] errors
☐ Backup MySQL database
☐ Test on multiple browsers (Chrome, Firefox, Safari)
☐ Test on mobile (responsive design)
☐ Enable HTTPS (set SESSION_SECURE = true)
```

---

## SUPPORT CHECKLIST

**When reporting issues, include:**

1. **Error message:** Exact text of error
2. **Steps to reproduce:** What did you do?
3. **Expected behavior:** What should happen?
4. **Actual behavior:** What actually happened?
5. **Files affected:** Which pages/functions?
6. **Error log snippet:** tail of error.log
7. **System info:**

   ```bash
   # RAM available:
   free -h

   # PHP version:
   php -v

   # MySQL status:
   mysqladmin status -u root

   # Apache status:
   systemctl status apache2
   ```

---

**Most issues can be resolved by:**

1. Enabling DEV_MODE in config.php
2. Restarting Apache
3. Clearing browser cache
4. Checking error logs for [XMLSync] messages
