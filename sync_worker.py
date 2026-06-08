import json
import os
import time
import pymysql
from xml_service import XMLService
from task_service import TaskService

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'task_tracker',
    'cursorclass': pymysql.cursors.DictCursor
}

def process_queue():
    xml_service = XMLService()
    task_service = TaskService(xml_service)
    queue_path = os.path.join('data', 'sync_queue.json')

    if not os.path.exists(queue_path):
        return

    with open(queue_path, 'r') as f:
        try:
            queue = json.load(f)
        except:
            return

    if not queue:
        return

    remaining_queue = []
    try:
        conn = pymysql.connect(**DB_CONFIG)
        with conn.cursor() as cursor:
            for entry in queue:
                success = False
                if entry['filename'] == 'tasks':
                    # Fetch current state from XML
                    task = task_service.get_task_by_id(entry['id'])
                    if task:
                        sql = """INSERT INTO tasks (id, title, description, priority, status, user_id, created_at) 
                                 VALUES (%s, %s, %s, %s, %s, %s, %s) 
                                 ON DUPLICATE KEY UPDATE 
                                 title=%s, description=%s, priority=%s, status=%s"""
                        cursor.execute(sql, (
                            task['id'], task['title'], task.get('description', ''), 
                            task.get('priority', 'Medium'), task['status'], task['created_by'], 
                            task['created_date'], task['title'], task.get('description', ''), 
                            task.get('priority', 'Medium'), task['status']
                        ))
                        success = True
                
                if not success:
                    remaining_queue.append(entry)
            
            conn.commit()
    except Exception as e:
        print(f"Sync failed: {e}")
        remaining_queue = queue
    finally:
        if 'conn' in locals(): conn.close()

    with open(queue_path, 'w') as f:
        json.dump(remaining_queue, f, indent=2)

if __name__ == "__main__":
    while True:
        print("Checking sync queue...")
        process_queue()
        time.sleep(60) # Run every minute