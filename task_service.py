from datetime import datetime, timedelta
from lxml import etree

class TaskService:
    def __init__(self, xml_service):
        """Initializes the TaskService with a dependency on XMLService."""
        self.xml = xml_service
        self.filename = "tasks"

    def get_all_tasks(self, user_id=None, limit=None, offset=0):
        tasks = []
        count = 0
        returned = 0
        
        # Phase 3: Streaming parsing for memory efficiency
        for task_el in self.xml.iter_all(self.filename, 'task'):
            if user_id:
                if task_el.findtext('created_by') != str(user_id) and task_el.findtext('assigned_to') != str(user_id):
                    continue
            
            if count < offset:
                count += 1
                continue
                
            tasks.append(self._element_to_dict(task_el))
            returned += 1
            count += 1
            
            if limit and returned >= limit:
                break
        return tasks

    def create_task(self, data):
        tree = self.xml.get_element_tree(self.filename)
        root = tree.getroot()
        
        task_id = self.xml.get_next_id(self.filename, "task")
        new_task = etree.SubElement(root, "task", id=task_id)
        
        fields = {
            'title': data['title'],
            'description': data.get('description', ''),
            'priority': data.get('priority', 'Medium'),
            'status': 'pending',
            'created_by': str(data['created_by']),
            'assigned_to': str(data.get('assigned_to', data['created_by'])),
            'created_date': datetime.now().isoformat(),
            'due_date': data.get('due_date', ''),
            'last_updated': datetime.now().isoformat()
        }
        
        for key, val in fields.items():
            el = etree.SubElement(new_task, key)
            el.text = val
            
        return self.xml.save_safely(self.filename, tree, task_id)

    def get_archived_tasks(self, user_id=None):
        """Retrieves archived tasks. Uses 'archive_tasks' to match ArchiveService naming."""
        tasks = []
        # Using streaming iterator for efficiency
        for task_el in self.xml.iter_all("archive_tasks", 'task'):
            if user_id and task_el.findtext('created_by') != str(user_id) and task_el.findtext('assigned_to') != str(user_id):
                continue
            tasks.append(self._element_to_dict(task_el))
        return tasks

    def get_task_by_id(self, task_id, user_id=None):
        xpath = "//task[@id=$tid]"
        if user_id:
            xpath += "[created_by=$uid or assigned_to=$uid]"
        
        nodes = self.xml.find_all(self.filename, xpath, tid=task_id, uid=str(user_id))
        return self._element_to_dict(nodes[0]) if nodes else None

    def update_task(self, task_id, data, user_id, is_admin=False):
        tree = self.xml.get_element_tree(self.filename)
        task_nodes = tree.xpath("//task[@id=$tid]", tid=task_id)
        
        if not task_nodes:
            return False, "Task not found."
        
        task = task_nodes[0]
        if not is_admin and task.findtext('created_by') != str(user_id) and task.findtext('assigned_to') != str(user_id):
            return False, "Unauthorized."

        for key, value in data.items():
            node = task.find(key)
            if node is not None:
                node.text = str(value)
            else:
                # Create the element if it doesn't exist to prevent missing data
                etree.SubElement(task, key).text = str(value)
        
        task.find('last_updated').text = datetime.now().isoformat()
        return self.xml.save_safely(self.filename, tree, task_id)

    def update_task_status(self, task_id, status, user_id, is_admin=False):
        return self.update_task(task_id, {'status': status}, user_id, is_admin)

    def delete_task(self, task_id, user_id, is_admin=False):
        """Permanent deletion of a task."""
        tree = self.xml.get_element_tree(self.filename)
        task_nodes = tree.xpath("//task[@id=$tid]", tid=task_id)
        
        if not task_nodes:
            return False, "Task not found."
        
        task = task_nodes[0]
        # Only admin or creator can hard delete
        if not is_admin and task.findtext('created_by') != str(user_id) and task.findtext('assigned_to') != str(user_id):
            return False, "Unauthorized."
            
        task.getparent().remove(task)
        return self.xml.save_safely(self.filename, tree, task_id)

    def get_dashboard_stats(self, user_id):
        tasks = self.get_all_tasks(user_id)
        archived = self.get_archived_tasks(user_id)
        
        now = datetime.now()
        daily_data = {}
        earliest_date = now
        
        # Initialize the last 7 days with zero counts
        for i in range(6, -1, -1):
            date_str = (now - timedelta(days=i)).strftime('%Y-%m-%d')
            daily_data[date_str] = 0
            
        all_relevant_tasks = tasks + archived
        for t in all_relevant_tasks:
            created_at_str = t.get('created_date')
            if created_at_str:
                try:
                    dt = datetime.fromisoformat(created_at_str)
                    if dt < earliest_date: earliest_date = dt
                    
                    date_key = created_at_str.split('T')[0]
                    if date_key in daily_data:
                        daily_data[date_key] += 1
                except (ValueError, TypeError): continue

        total_active = len(tasks)
        completed = len([t for t in tasks if t['status'] == 'completed'])
        completion_rate = round((completed / total_active * 100), 1) if total_active > 0 else 0
        
        days_active = max(1, (now - earliest_date).days + 1)
        avg_per_day = round((total_active + len(archived)) / days_active, 1)
        
        # Productivity logic based on completion percentage and volume
        productivity_level = 'Starting'
        if completion_rate >= 80 and total_active >= 5: productivity_level = 'Excellent'
        elif completion_rate >= 50: productivity_level = 'Good'
        elif total_active > 0: productivity_level = 'Moderate'

        return {
            'total': total_active,
            'completed': completed,
            'pending': len([t for t in tasks if t['status'] == 'pending']),
            'high_priority': len([t for t in tasks if t['priority'] == 'High']),
            'archived': len(archived),
            'daily_data': daily_data,
            'completion_rate': completion_rate,
            'avg_per_day': avg_per_day,
            'productivity_level': productivity_level
        }

    def search(self, query='', status=None, priority=None, user_id=None):
        filtered = []
        # Optimization: Use streaming for search to remain memory efficient
        for task_el in self.xml.iter_all(self.filename, 'task'):
            if user_id:
                if task_el.findtext('created_by') != str(user_id) and task_el.findtext('assigned_to') != str(user_id):
                    continue
            
            t = self._element_to_dict(task_el)
            if query and query not in t['title'].lower() and query not in t['description'].lower():
                continue
            if status and t['status'] != status:
                continue
            if priority and t['priority'] != priority:
                continue
            filtered.append(t)
        return filtered

    def _element_to_dict(self, el):
        data = {'id': el.get('id')}
        for child in el:
            data[child.tag] = child.text
        return data