from lxml import etree
from datetime import datetime
import copy

class ArchiveService:
    def __init__(self, xml_service):
        self.xml = xml_service
        self.tasks_file = "tasks"
        self.archive_file = "archive_tasks" # Updated to match documentation

    def get_archived_task(self, task_id, user_id=None):
        xpath = "//task[id=$tid]"
        if user_id:
            xpath += "[user_id=$uid]"
            
        nodes = self.xml.find_all(self.archive_file, xpath, tid=task_id, uid=str(user_id))
        return self._element_to_dict(nodes[0]) if nodes else None

    def archive_task(self, task_id, user_id, is_admin=False):
        """
        Moves a task from tasks.xml to archived_tasks.xml.
        Verifies ownership or admin status before moving.
        """
        tree = self.xml.get_element_tree(self.tasks_file)
        root = tree.getroot()
        
        # Find the specific task using XPath
        task_nodes = root.xpath("//task[id=$tid]", tid=task_id)
        if not task_nodes:
            return False, "Task not found."
        
        task_node = task_nodes[0]
        
        # Permission check: User must be creator or the assigned user
        user_id_val = task_node.findtext('user_id')
        assigned_to = task_node.findtext('assigned_to')
        if not is_admin and str(user_id) not in [user_id_val, assigned_to]:
            return False, "Permission denied."

        # Load/Initialize the archive tree
        archive_tree = self.xml.get_element_tree(self.archive_file)
        archive_root = archive_tree.getroot()
        
        # Use deepcopy to preserve all data including attributes
        archived_node = copy.deepcopy(task_node)

        # Add archived_at timestamp so the xml_sync_optimizer can prune old tasks
        arch_ts = archived_node.find('archived_at')
        if arch_ts is None:
            etree.SubElement(archived_node, 'archived_at').text = datetime.now().isoformat()
        else:
            arch_ts.text = datetime.now().isoformat()
        
        archive_root.append(archived_node)
        root.remove(task_node)
        
        # Save both files; save_safely performs XSD validation before writing
        success, msg = self.xml.save_safely(self.tasks_file, tree, task_id)
        if not success:
            return False, msg
            
        return self.xml.save_safely(self.archive_file, archive_tree, task_id)

    def restore_task(self, task_id, user_id, is_admin=False):
        """Moves a task from archived_tasks.xml back to tasks.xml."""
        archive_tree = self.xml.get_element_tree(self.archive_file)
        archive_root = archive_tree.getroot()
        
        task_nodes = archive_root.xpath("//task[id=$tid]", tid=task_id)
        if not task_nodes:
            return False, "Archived task not found."
            
        task_node = task_nodes[0]
        
        # Ownership check (typically based on original creator)
        user_id_val = task_node.findtext('user_id')
        assigned_to = task_node.findtext('assigned_to')
        if not is_admin and str(user_id) not in [user_id_val, assigned_to]:
            return False, "Permission denied."
            
        tasks_tree = self.xml.get_element_tree(self.tasks_file)
        tasks_root = tasks_tree.getroot()
        
        # Use deepcopy to ensure consistency
        restored_node = copy.deepcopy(task_node)
        
        # Remove archived_at timestamp when moving back to active
        arch_ts = restored_node.find('archived_at')
        if arch_ts is not None:
            restored_node.remove(arch_ts)

        tasks_root.append(restored_node)
        archive_root.remove(task_node)
        
        success, msg = self.xml.save_safely(self.archive_file, archive_tree, task_id)
        if not success:
            return False, msg
            
        return self.xml.save_safely(self.tasks_file, tasks_tree, task_id)

    def _element_to_dict(self, el):
        data = {}
        for child in el:
            data[child.tag] = child.text
        return data