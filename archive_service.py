from lxml import etree  # type: ignore
from datetime import datetime
import copy

class ArchiveService:
    def __init__(self, xml_service):
        self.xml = xml_service
        self.tasks_file = "tasks"
        self.archive_file = "archive_tasks" # Updated to match documentation

    def get_archived_task(self, task_id, user_id=None):
        xpath = "//*[local-name()='task'][normalize-space(*[local-name()='id'])=$tid or normalize-space(@id)=$tid]"
        if user_id:
            xpath += "[*[local-name()='user_id']=$uid]"
            
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
        task_nodes = root.xpath("//*[local-name()='task'][normalize-space(*[local-name()='id'])=$tid or normalize-space(@id)=$tid]", tid=task_id)
        if not task_nodes:
            return False, "Task not found."
        
        task_node = task_nodes[0]
        
        # Permission check: User must be creator or the assigned user
        user_id_val = task_node.xpath("string(*[local-name()='user_id'])").strip()
        assigned_to = task_node.xpath("string(*[local-name()='assigned_to'])").strip()
        if not is_admin and str(user_id) not in [user_id_val, assigned_to]:
            return False, "Permission denied."

        # Load/Initialize the archive tree
        archive_tree = self.xml.get_element_tree(self.archive_file)
        archive_root = archive_tree.getroot()
        
        # Use deepcopy to preserve all data including attributes
        archived_node = copy.deepcopy(task_node)

        ns = archive_root.nsmap.get(None)
        ts_nodes = archived_node.xpath("*[local-name()='archived_at']")
        if not ts_nodes:
            tag = f"{{{ns}}}archived_at" if ns else "archived_at"
            etree.SubElement(archived_node, tag).text = datetime.now().isoformat()
        else:
            ts_nodes[0].text = datetime.now().isoformat()
        
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
        
        task_nodes = archive_root.xpath("//*[local-name()='task'][normalize-space(*[local-name()='id'])=$tid or normalize-space(@id)=$tid]", tid=task_id)
        if not task_nodes:
            return False, "Archived task not found."
            
        task_node = task_nodes[0]
        
        # Ownership check (typically based on original creator)
        user_id_val = task_node.xpath("string(*[local-name()='user_id'])").strip()
        assigned_to = task_node.xpath("string(*[local-name()='assigned_to'])").strip()
        if not is_admin and str(user_id) not in [user_id_val, assigned_to]:
            return False, "Permission denied."
            
        tasks_tree = self.xml.get_element_tree(self.tasks_file)
        tasks_root = tasks_tree.getroot()
        
        # Use deepcopy to ensure consistency
        restored_node = copy.deepcopy(task_node)
        
        ts_nodes = restored_node.xpath("*[local-name()='archived_at']")
        for ts in ts_nodes:
            restored_node.remove(ts)

        tasks_root.append(restored_node)
        archive_root.remove(task_node)
        
        success, msg = self.xml.save_safely(self.archive_file, archive_tree, task_id)
        if not success:
            return False, msg
            
        return self.xml.save_safely(self.tasks_file, tasks_tree, task_id)

    def bulk_restore_tasks(self, user_id, is_admin=False, task_ids=None):
        """
        Moves tasks back to tasks.xml. 
        If task_ids is provided, restores only those. Otherwise, restores all for the user.
        """
        archive_tree = self.xml.get_element_tree(self.archive_file)
        archive_root = archive_tree.getroot()
        
        tasks_tree = self.xml.get_element_tree(self.tasks_file)
        tasks_root = tasks_tree.getroot()

        # Build XPath for target tasks
        if task_ids:
            # Restore specific IDs
            xpath = "//*[local-name()='task'][" + " or ".join([f"*[local-name()='id']='{tid}' or @id='{tid}'" for tid in task_ids]) + "]"
        else:
            # Restore all
            xpath = "//*[local-name()='task']"
            
        variables = {}
        if not is_admin:
            xpath += "[*[local-name()='user_id']=$uid or *[local-name()='assigned_to']=$uid]"
            variables['uid'] = str(user_id)
            
        nodes = archive_root.xpath(xpath, **variables)
        if not nodes:
            return True, "No tasks found to restore."
            
        restored_count = 0
        for node in nodes:
            restored_node = copy.deepcopy(node)
            
            # Remove archive-specific metadata
            for ts in restored_node.xpath("*[local-name()='archived_at']"):
                restored_node.remove(ts)

            tasks_root.append(restored_node)
            archive_root.remove(node)
            restored_count += 1
            
        if restored_count == 0:
            return False, "No tasks were restored."

        # Save state atomically
        success, msg = self.xml.save_safely(self.archive_file, archive_tree)
        if not success:
            return False, msg
            
        return self.xml.save_safely(self.tasks_file, tasks_tree)

    def _element_to_dict(self, el):
        data = {}
        # Capture attributes to support the attribute ID format
        for name, value in el.attrib.items():
            data[name] = value
        for child in el:
            local_tag = etree.QName(child).localname
            data[local_tag] = child.text.strip() if child.text else ""
        return data