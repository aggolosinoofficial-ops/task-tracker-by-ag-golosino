from datetime import datetime
from lxml import etree

class ActivityService:
    def __init__(self, xml_service):
        self.xml = xml_service
        self.filename = "activity_logs"

    def log_activity(self, username, action):
        tree = self.xml.get_element_tree(self.filename)
        root = tree.getroot()
        
        log_entry = etree.SubElement(root, "log")
        etree.SubElement(log_entry, "user").text = username
        etree.SubElement(log_entry, "action").text = action
        etree.SubElement(log_entry, "timestamp").text = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        
        self.xml.save_safely(self.filename, tree)

    def get_recent_logs(self, limit=10):
        logs = self.xml.find_all(self.filename, "//log")
        logs.reverse()
        result = []
        for log in logs[:limit]:
            result.append({
                'user': log.find('user').text,
                'action': log.find('action').text,
                'timestamp': log.find('timestamp').text
            })
        return result

    def get_all_logs(self):
        logs = self.xml.find_all(self.filename, "//log")
        logs.reverse()
        return [{
            'user': log.find('user').text,
            'action': log.find('action').text,
            'timestamp': log.find('timestamp').text
        } for log in logs]