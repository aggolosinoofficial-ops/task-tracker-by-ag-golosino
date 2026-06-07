import pymysql
from lxml import etree
from datetime import datetime
from xml_service import XMLService

# Database configuration - Update these to match your PHP environment
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'task_tracker',  # Based on your previous config.php
    'cursorclass': pymysql.cursors.DictCursor
}

def format_dt(dt):
    """Ensures datetime objects are converted to ISO format for XML/XSD compatibility."""
    if isinstance(dt, datetime):
        return dt.isoformat()
    return str(dt)

def migrate_data():
    xml_service = XMLService()
    
    try:
        connection = pymysql.connect(**DB_CONFIG)
        print(f"Connected to MySQL database: {DB_CONFIG['database']}")
        
        with connection.cursor() as cursor:
            # 1. Migrate Users
            print("Migrating users...")
            cursor.execute("SELECT * FROM users")
            users = cursor.fetchall()
            user_tree = etree.ElementTree(etree.Element("users"))
            for u in users:
                user_el = etree.SubElement(user_tree.getroot(), "user", id=str(u['id']))
                etree.SubElement(user_el, "username").text = u['username']
                etree.SubElement(user_el, "password_hash").text = u['password_hash']
                etree.SubElement(user_el, "role").text = u.get('role', 'user')
                etree.SubElement(user_el, "created_at").text = format_dt(u.get('created_at', datetime.now()))
            
            success, msg = xml_service.save_safely("users", user_tree)
            print(f"Users migration: {msg}")

            # 2. Migrate Tasks
            print("Migrating active tasks...")
            cursor.execute("SELECT * FROM tasks")
            tasks = cursor.fetchall()
            task_tree = etree.ElementTree(etree.Element("tasks"))
            for t in tasks:
                task_el = etree.SubElement(task_tree.getroot(), "task", id=str(t['id']))
                etree.SubElement(task_el, "title").text = t['title']
                etree.SubElement(task_el, "description").text = t.get('description', '')
                etree.SubElement(task_el, "priority").text = t.get('priority', 'Medium')
                etree.SubElement(task_el, "status").text = t.get('status', 'pending')
                etree.SubElement(task_el, "created_by").text = str(t['user_id']) # Mapping PHP user_id to XML created_by
                etree.SubElement(task_el, "assigned_to").text = str(t.get('assigned_to', t['user_id']))
                etree.SubElement(task_el, "created_date").text = format_dt(t.get('created_at', datetime.now()))
            
            success, msg = xml_service.save_safely("tasks", task_tree)
            print(f"Tasks migration: {msg}")

            # 3. Migrate Archived Tasks
            print("Migrating archived tasks...")
            # Some PHP systems use a separate table, others use a flag. 
            # Adjust the table name 'archive_tasks' if your original PHP schema was different.
            try:
                cursor.execute("SELECT * FROM archive_tasks")
                archived_tasks = cursor.fetchall()
                archive_tree = etree.ElementTree(etree.Element("archived_tasks"))
                for at in archived_tasks:
                    at_el = etree.SubElement(archive_tree.getroot(), "task", id=str(at['id']))
                    etree.SubElement(at_el, "title").text = at['title']
                    etree.SubElement(at_el, "description").text = at.get('description', '')
                    etree.SubElement(at_el, "priority").text = at.get('priority', 'Medium')
                    etree.SubElement(at_el, "status").text = at.get('status', 'completed')
                    etree.SubElement(at_el, "created_by").text = str(at['user_id'])
                    etree.SubElement(at_el, "assigned_to").text = str(at.get('assigned_to', at['user_id']))
                    etree.SubElement(at_el, "created_date").text = format_dt(at.get('created_at', datetime.now()))
                
                success, msg = xml_service.save_safely("archived_tasks", archive_tree)
                print(f"Archive migration: {msg}")
            except pymysql.err.ProgrammingError:
                print("Table 'archive_tasks' not found, skipping archive migration.")

            # 4. Migrate Activity Logs
            print("Migrating activity logs...")
            try:
                cursor.execute("SELECT * FROM activity_logs LIMIT 100") # Limit to prevent memory overhead
                logs = cursor.fetchall()
                log_tree = etree.ElementTree(etree.Element("activity_logs"))
                for l in logs:
                    log_el = etree.SubElement(log_tree.getroot(), "log")
                    # Map fields based on standard PHP activity log structures
                    etree.SubElement(log_el, "user").text = str(l.get('username', 'System'))
                    etree.SubElement(log_el, "action").text = l['action']
                    etree.SubElement(log_el, "timestamp").text = format_dt(l.get('created_at', datetime.now()))
                
                success, msg = xml_service.save_safely("activity_logs", log_tree)
                print(f"Activity logs migration: {msg}")
            except pymysql.err.ProgrammingError:
                print("Table 'activity_logs' not found, skipping logs migration.")

        print("\nMigration process completed.")

    except Exception as e:
        print(f"Migration failed: {str(e)}")
    finally:
        if 'connection' in locals():
            connection.close()

if __name__ == "__main__":
    # Create necessary folders if they don't exist
    import os
    if not os.path.exists('data'): os.makedirs('data')
    if not os.path.exists('schema'): 
        print("Please ensure your XSD files are in the 'schema/' folder before running.")
    else:
        migrate_data()