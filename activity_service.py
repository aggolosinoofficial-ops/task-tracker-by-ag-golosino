from lxml import etree
from datetime import datetime

class ActivityService:
    def __init__(self, xml_service):
        self.xml = xml_service
        self.filename = "activity_logs"

    def log_activity(self, username, action):
        """Records a system event into the activity_logs.xml file."""
        tree = self.xml.get_element_tree(self.filename)
        root = tree.getroot()
        
        log_el = etree.SubElement(root, "log")
        etree.SubElement(log_el, "user").text = str(username)
        etree.SubElement(log_el, "action").text = str(action)
        etree.SubElement(log_el, "timestamp").text = datetime.now().isoformat()
        
        return self.xml.save_safely(self.filename, tree)

    def get_recent_logs(self, limit=10):
        """Retrieves the most recent logs using the memory-efficient iterator."""
        logs = []
        # Using iter_all to handle potentially large log files on 2GB RAM
        all_logs = list(self.xml.iter_all(self.filename, 'log'))
        # Get the last N logs
        recent = all_logs[-limit:] if len(all_logs) > limit else all_logs
        
        for log_el in reversed(recent):
            logs.append(self._element_to_dict(log_el))
        return logs

    def get_all_logs(self):
        """Returns all logs for the admin view."""
        return [self._element_to_dict(l) for l in self.xml.iter_all(self.filename, 'log')]

    def _element_to_dict(self, el):
        return {
            'user': el.findtext('user'),
            'action': el.findtext('action'),
            'timestamp': self._format_timestamp(el.findtext('timestamp'))
        }

    def _format_timestamp(self, iso_timestamp):
        if iso_timestamp:
            try:
                dt_object = datetime.fromisoformat(iso_timestamp)
                return dt_object.strftime('%Y-%m-%d %H:%M:%S')
            except ValueError:
                return iso_timestamp # Return original if parsing fails
        return "N/A"