from datetime import datetime, timedelta
from lxml import etree  # type: ignore

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
            if user_id is not None:
                uid = task_el.xpath("string(*[local-name()='user_id'])").strip()
                aid = task_el.xpath("string(*[local-name()='assigned_to'])").strip() or uid
                if uid != str(user_id) and aid != str(user_id):
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
        
        ns = root.nsmap.get(None)
        tag = lambda t: f"{{{ns}}}{t}" if ns else t

        task_id = self.xml.get_next_id(self.filename, "task")
        new_task = etree.SubElement(root, "task")
        
        fields = {
            'id': task_id,
            'user_id': str(data['user_id']),
            'assigned_to': str(data.get('assigned_to', data['user_id'])),
            'title': data['title'],
            'description': data.get('description', ''),
            'status': 'pending',
            'created_at': datetime.now().isoformat(),
            'priority': data.get('priority', 'Medium'),
            'due_date': data.get('due_date', ''),
            'last_updated': datetime.now().isoformat()
        }
        
        for key, val in fields.items():
            el = etree.SubElement(new_task, tag(key))
            el.text = val
            
        return self.xml.save_safely(self.filename, tree, task_id)

    def get_archived_tasks(self, user_id=None):
        """Retrieves archived tasks. Uses 'archive_tasks' to match ArchiveService naming."""
        tasks = []
        # Using streaming iterator for efficiency
        for task_el in self.xml.iter_all("archive_tasks", "task"):
            if user_id is not None:
                uid = task_el.xpath("string(*[local-name()='user_id'])").strip()
                aid = task_el.xpath("string(*[local-name()='assigned_to'])").strip() or uid
                if uid != str(user_id) and aid != str(user_id):
                    continue
            tasks.append(self._element_to_dict(task_el))
        return tasks

    def get_task_by_id(self, task_id, user_id=None):
        xpath = "//*[local-name()='task'][*[local-name()='id']=$tid or @id=$tid]"
        if user_id:
            xpath += "[*[local-name()='user_id']=$uid or *[local-name()='assigned_to']=$uid]"
        
        nodes = self.xml.find_all(self.filename, xpath, tid=task_id, uid=str(user_id))
        return self._element_to_dict(nodes[0]) if nodes else None

    def update_task(self, task_id, data, user_id, is_admin=False):
        tree = self.xml.get_element_tree(self.filename)
        task_nodes = tree.xpath("//*[local-name()='task'][normalize-space(*[local-name()='id'])=$tid or normalize-space(@id)=$tid]", tid=task_id)
        
        if not task_nodes:
            return False, "Task not found."
        
        task = task_nodes[0]
        uid = task.xpath("string(*[local-name()='user_id'])").strip()
        aid = task.xpath("string(*[local-name()='assigned_to'])").strip()
        if not is_admin and uid != str(user_id) and aid != str(user_id):
            return False, "Unauthorized."

        # Smooth Update: Just update/add tags. 
        # XMLService.save_safely now handles the XSD ordering via XSLT automatically.
        for key, value in data.items():
            if value is not None:
                node = task.find(key)
                if node is None: node = etree.SubElement(task, key)
                node.text = str(value)
        
        last_updated = task.find('last_updated')
        if last_updated is None: last_updated = etree.SubElement(task, 'last_updated')
        last_updated.text = datetime.now().isoformat()

        return self.xml.save_safely(self.filename, tree, task_id)

    def update_task_status(self, task_id, status, user_id, is_admin=False):
        return self.update_task(task_id, {'status': status}, user_id, is_admin)

    def delete_task(self, task_id, user_id, is_admin=False):
        """Permanent deletion of a task."""
        tree = self.xml.get_element_tree(self.filename)
        task_nodes = tree.xpath("//*[local-name()='task'][normalize-space(*[local-name()='id'])=$tid or normalize-space(@id)=$tid]", tid=task_id)
        
        if not task_nodes:
            return False, "Task not found."
        
        task = task_nodes[0]
        # Only admin or creator can hard delete
        uid = task.xpath("string(*[local-name()='user_id'])").strip()
        aid = task.xpath("string(*[local-name()='assigned_to'])").strip()
        if not is_admin and uid != str(user_id) and aid != str(user_id):
            return False, "Unauthorized."
            
        task.getparent().remove(task)
        return self.xml.save_safely(self.filename, tree, task_id)

    def bulk_delete_tasks(self, status, user_id, is_admin=False):
        """Deletes all tasks matching a status for a specific user using a single XPath selection."""
        tree = self.xml.get_element_tree(self.filename)
        
        # Build an optimized XPath to select the entire set of nodes at once
        xpath = "//*[local-name()='task'][normalize-space(*[local-name()='status'])=$s]"
        variables = {'s': status}
        
        if not is_admin:
            xpath += "[normalize-space(*[local-name()='user_id'])=$uid or normalize-space(*[local-name()='assigned_to'])=$uid]"
            variables['uid'] = str(user_id)
            
        for task in tree.xpath(xpath, **variables):
            task.getparent().remove(task)
            
        return self.xml.save_safely(self.filename, tree)

    def bulk_update_status(self, current_status, new_status, user_id, is_admin=False):
        """Updates multiple tasks from one status to another using a single XPath selection."""
        tree = self.xml.get_element_tree(self.filename)
        
        # Select the target set of nodes
        xpath = "//*[local-name()='task'][normalize-space(*[local-name()='status'])=$cs]"
        variables = {'cs': current_status}
        
        if not is_admin:
            xpath += "[normalize-space(*[local-name()='user_id'])=$uid or normalize-space(*[local-name()='assigned_to'])=$uid]"
            variables['uid'] = str(user_id)
            
        nodes = tree.xpath(xpath, **variables)
        if not nodes:
            return True, "No tasks found to update."
            
        now_ts = datetime.now().isoformat()
        for node in nodes:
            status_node = node.find('status')
            if status_node is not None:
                status_node.text = new_status
            
            # Ensure last_updated is refreshed
            last_updated = node.find('last_updated')
            if last_updated is None:
                last_updated = etree.SubElement(node, 'last_updated')
            last_updated.text = now_ts
            
        return self.xml.save_safely(self.filename, tree)

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
            created_at_str = t.get('created_at')
            if created_at_str:
                try:
                    dt = datetime.fromisoformat(created_at_str)
                    if dt < earliest_date: earliest_date = dt
                    
                    date_key = created_at_str.split('T')[0] if 'T' in created_at_str else created_at_str
                    if date_key in daily_data:
                        daily_data[date_key] += 1
                except (ValueError, TypeError, AttributeError): continue

        # Calculate priorities correctly
        # Handle cases where priority might be None or empty string by defaulting to Medium
        priorities = {
            'High': len([t for t in tasks if t.get('priority') == 'High']),
            'Medium': len([t for t in tasks if t.get('priority') in [None, '', 'Medium']]),
            'Low': len([t for t in tasks if t.get('priority') == 'Low'])
        }

        # High Priority Productivity Score: Completed vs Pending High Priority
        hp_tasks = [t for t in tasks if t.get('priority') == 'High']
        hp_completed = len([t for t in hp_tasks if t.get('status') == 'completed'])
        hp_pending = len([t for t in hp_tasks if t.get('status') == 'pending'])
        hp_total = hp_completed + hp_pending
        productivity_score = round((hp_completed / hp_total * 100), 1) if hp_total > 0 else 0

        total_active = len(tasks)
        completed = len([t for t in tasks if t.get('status') == 'completed'])
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
            'pending': len([t for t in tasks if t.get('status') == 'pending']),
            'high_priority': priorities['High'],
            'priorities': priorities,
            'archived': len(archived),
            'daily_data': daily_data,
            'completion_rate': completion_rate,
            'productivity_score': productivity_score,
            'avg_per_day': avg_per_day,
            'productivity_level': productivity_level
        }

    def search(self, query='', status=None, priority=None, user_id=None):
        filtered = []
        # Optimization: Use streaming for search to remain memory efficient
        for task_el in self.xml.iter_all(self.filename, 'task'):
            if user_id:
                uid_val = task_el.xpath("string(*[local-name()='user_id'])")
                at_val = task_el.xpath("string(*[local-name()='assigned_to'])")
                if uid_val != str(user_id) and at_val != str(user_id):
                    continue
            
            t = self._element_to_dict(task_el)
            title = (t.get('title') or "").lower()
            desc = (t.get('description') or "").lower()
            if query and query not in title and query not in desc:
                continue
            if status and t['status'] != status:
                continue
            if priority and t['priority'] != priority:
                continue
            filtered.append(t)
        return filtered

    def _element_to_dict(self, el):
        data = {}
        for child in el:
            local_tag = etree.QName(child).localname
            data[local_tag] = child.text.strip() if child.text else ""
        return data