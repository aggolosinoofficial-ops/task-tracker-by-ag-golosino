# 🔧 Task Not Saving - Troubleshooting Guide

## Problem Summary

- Task form doesn't show success/error notifications
- Tasks are not appearing in "All Tasks" page
- No feedback when adding a task

---

## ✅ Step 1: Test With Debug Page

**First, test if everything is working correctly:**

```
http://localhost/to-do-app-by-ag-golosino/debug_task_form.php
```

This page will:

1. ✓ Verify you're logged in
2. ✓ Test CSRF token generation
3. ✓ Test database connection
4. ✓ Test getting tasks
5. ✓ Test adding tasks
6. ✓ Show console output for debugging

**Instructions:**

1. Login first with your non-admin account
2. Go to the debug page
3. Click each "Test" button and check the results
4. Look at console output to see what's happening
5. **Report any errors that appear**

---

## ✅ Step 2: Check Browser Console

If the debug page doesn't help, check for JavaScript errors:

**On the Login or Add Task page:**

1. Press **F12** to open Developer Tools
2. Click **Console** tab
3. Try adding a task
4. **Screenshot or copy any errors** and show them

**Common errors to look for:**

- `Uncaught TypeError: ...`
- `Failed to fetch`
- `undefined function`
- `null reference`

---

## ✅ Step 3: Manual Test

**To manually verify the API is working:**

1. Open browser console (F12 → Console)
2. Paste and run each command:

```javascript
// Test 1: Test if addTask function exists
typeof addTask;
// Should show: "function"

// Test 2: Test if notification system works
showNotification("Test message", "success");
// Should show green notification in top-right

// Test 3: Get tasks
fetch("get_tasks.php")
  .then((r) => r.json())
  .then((d) => console.log(d));
// Should show tasks in console

// Test 4: Add a task directly
fetch("add_task.php", {
  method: "POST",
  headers: { "Content-Type": "application/x-www-form-urlencoded" },
  body: "title=Manual Test&description=Test via console",
})
  .then((r) => r.json())
  .then((d) => console.log(d));
// Should show success message
```

---

## 🎯 Most Common Issues & Solutions

### Issue 1: Notifications not appearing

**Solution:**

- Check if `#notificationContainer` div exists in HTML
- Check if style.css has `.notification` styles
- Check browser console for JavaScript errors

### Issue 2: Form not submitting

**Solution:**

- Press F12 and check console for errors
- Verify form has `id="taskForm"`
- Verify button has `type="submit"`

### Issue 3: API returning errors

**Solution:**

- Check if you're logged in (session valid)
- Verify user ID is being passed correctly
- Check database has user record

### Issue 4: Tasks added but not showing

**Solution:**

- Check if tasks are in database (use debug page)
- Verify get_tasks.php returns correct format
- Check browser console for JS errors in loadTasks()

---

## 📋 What Should Happen (Working Flow)

1. **User adds task** → Form submits
2. **JavaScript validates** → Shows nothing if empty
3. **Notification appears** → "Adding..." message
4. **API called** → POST to add_task.php
5. **Task saved** → Returns success response
6. **Success notification** → "Task saved successfully!"
7. **Form cleared** → Input fields empty
8. **Tasks reloaded** → loadTasks() called
9. **New task appears** → In All Tasks page

---

## 🆘 If Still Not Working

**Please provide:**

1. Screenshot of browser console errors (F12)
2. Output from the debug page (debug_task_form.php)
3. The exact error message or behavior you see
4. Whether you're on a regular user account or admin account

Then I can provide a specific fix!

---

## Quick Links

- Debug Page: http://localhost/to-do-app-by-ag-golosino/debug_task_form.php
- Add Task: http://localhost/to-do-app-by-ag-golosino/index.php
- View Tasks: http://localhost/to-do-app-by-ag-golosino/tasks.php
- Connection Test: http://localhost/to-do-app-by-ag-golosino/test_connection.php
