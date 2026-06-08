#!/usr/bin/env python3
"""
XML Sync & Optimization Utility
Lightweight Python script for offline XML operations
2GB RAM optimization: compact storage, lazy parsing, minimal nesting

Usage:
    python3 xml_sync_optimizer.py --compact      # Minimize XML file size
    python3 xml_sync_optimizer.py --sync         # Sync XML to MySQL
    python3 xml_sync_optimizer.py --status       # Check storage status
    python3 xml_sync_optimizer.py --prune        # Remove old archived tasks
"""

import xml.etree.ElementTree as ET
import json
import sys
import os
import time
from datetime import datetime, timedelta
import sqlite3
import argparse
import logging

# Configure logging (minimal overhead)
logging.basicConfig(
    level=logging.INFO,
    format='[%(asctime)s] %(levelname)s: %(message)s'
)
logger = logging.getLogger(__name__)

class XMLOptimizer:
    """Lightweight XML optimization for 2GB RAM systems"""
    
    def __init__(self):
        self.users_file = 'users.xml'
        self.tasks_file = 'tasks.xml'
        self.archive_file = 'archive_tasks.xml'
        self.max_file_size = 10 * 1024 * 1024  # 10MB limit
    
    def load_xml(self, filename):
        """Lazy load XML (only parse if file exists and reasonable size)"""
        if not os.path.exists(filename):
            return None
        
        try:
            # Check file size before loading
            file_size = os.path.getsize(filename)
            if file_size == 0:
                return None
            
            if file_size > self.max_file_size:
                logger.warning(f"{filename} exceeds {self.max_file_size} bytes - skipping")
                return None
            
            tree = ET.parse(filename)
            return tree.getroot()
        except ET.ParseError as e:
            logger.error(f"XML parse error in {filename}: {e}")
            return None
        except Exception as e:
            logger.error(f"Error loading {filename}: {e}")
            return None
    
    def save_xml(self, root, filename):
        """Save XML with minimal whitespace (compact format)"""
        try:
            tree = ET.ElementTree(root)
            # Remove indentation for compact storage
            self._remove_whitespace(root)
            tree.write(filename, encoding='utf-8', xml_declaration=True)
            logger.info(f"Saved {filename} ({os.path.getsize(filename)} bytes)")
            return True
        except Exception as e:
            logger.error(f"Error saving {filename}: {e}")
            return False
    
    def _remove_whitespace(self, elem):
        """Recursively remove unnecessary whitespace"""
        elem.tail = None
        for child in elem:
            self._remove_whitespace(child)
    
    def compact_xml(self):
        """Compress all XML files to minimal format"""
        logger.info("Starting XML compaction...")
        
        files_compacted = 0
        total_saved = 0
        
        for filename in [self.users_file, self.tasks_file, self.archive_file]:
            if not os.path.exists(filename):
                continue
            
            before_size = os.path.getsize(filename)
            root = self.load_xml(filename)
            
            if root is None:
                continue
            
            if self.save_xml(root, filename):
                after_size = os.path.getsize(filename)
                saved = before_size - after_size
                total_saved += saved
                files_compacted += 1
                
                percent = (saved / before_size * 100) if before_size > 0 else 0
                logger.info(f"  {filename}: {before_size} → {after_size} bytes ({percent:.1f}% saved)")
        
        logger.info(f"Compaction complete: {files_compacted} files, {total_saved} bytes saved")
        return total_saved
    
    def prune_archive(self, days_old=90):
        """Remove archived tasks older than N days (memory cleanup)"""
        logger.info(f"Pruning archived tasks older than {days_old} days...")
        
        root = self.load_xml(self.archive_file)
        if root is None:
            logger.info("No archive file found")
            return 0
        
        cutoff_date = datetime.now() - timedelta(days=days_old)
        removed = 0
        
        tasks_to_remove = []
        for task in root.findall('task'):
            archived_at = task.findtext('archived_at')
            if archived_at:
                try:
                    task_date = datetime.fromisoformat(archived_at)
                    if task_date < cutoff_date:
                        tasks_to_remove.append(task)
                except ValueError:
                    pass
        
        for task in tasks_to_remove:
            root.remove(task)
            removed += 1
        
        if removed > 0:
            self.save_xml(root, self.archive_file)
            logger.info(f"Pruned {removed} tasks from archive")
        
        return removed
    
    def get_file_stats(self):
        """Get XML file statistics"""
        stats = {}
        
        for filename in [self.users_file, self.tasks_file, self.archive_file]:
            if os.path.exists(filename):
                size = os.path.getsize(filename)
                mtime = os.path.getmtime(filename)
                mtime_str = datetime.fromtimestamp(mtime).isoformat()
                
                # Count items in file
                root = self.load_xml(filename)
                item_count = len(root) if root is not None else 0
                
                stats[filename] = {
                    'size_bytes': size,
                    'items': item_count,
                    'modified': mtime_str
                }
        
        return stats


class XMLMySQLSync:
    """Sync XML to MySQL for backup/recovery"""
    
    def __init__(self):
        self.optimizer = XMLOptimizer()
    
    def sync_to_mysql(self):
        """Sync all XML files to MySQL database"""
        logger.info("Starting XML → MySQL sync...")
        
        try:
            import pymysql
        except ImportError:
            logger.error("pymysql not installed. Install with: pip install pymysql")
            return False
        
        try:
            # Connect to MySQL
            conn = pymysql.connect(
                host='localhost',
                user='root',
                password='',
                database='task_tracker',
                connect_timeout=5
            )
            cursor = conn.cursor()
            
            sync_count = 0
            
            # Sync users
            users_root = self.optimizer.load_xml(self.optimizer.users_file)
            if users_root:
                cursor.execute("TRUNCATE TABLE users")
                for user in users_root.findall('user'):
                    user_id = user.get('id')
                    username = user.findtext('username')
                    password_hash = user.findtext('password_hash')
                    role = user.findtext('role', 'user')
                    created_at = user.findtext('created_at')
                    
                    cursor.execute(
                        "INSERT INTO users (id, username, password_hash, role, created_at) VALUES (%s, %s, %s, %s, %s)",
                        (user_id, username, password_hash, role, created_at)
                    )
                    sync_count += 1
                
                conn.commit()
                logger.info(f"  Synced {sync_count} users")
            
            # Sync tasks
            sync_count = 0
            tasks_root = self.optimizer.load_xml(self.optimizer.tasks_file)
            if tasks_root:
                cursor.execute("TRUNCATE TABLE tasks")
                for task in tasks_root.findall('task'):
                    task_id = task.get('id')
                    user_id = task.get('user_id')
                    title = task.findtext('title')
                    description = task.findtext('description')
                    status = task.findtext('status', 'pending')
                    created_at = task.findtext('created_at')
                    
                    cursor.execute(
                        "INSERT INTO tasks (id, user_id, title, description, status, created_at) VALUES (%s, %s, %s, %s, %s, %s)",
                        (task_id, user_id, title, description, status, created_at)
                    )
                    sync_count += 1
                
                conn.commit()
                logger.info(f"  Synced {sync_count} tasks")
            
            # Sync archived tasks
            sync_count = 0
            archive_root = self.optimizer.load_xml(self.optimizer.archive_file)
            if archive_root:
                cursor.execute("TRUNCATE TABLE archive_tasks")
                for task in archive_root.findall('task'):
                    task_id = task.get('id')
                    user_id = task.get('user_id')
                    title = task.findtext('title')
                    description = task.findtext('description')
                    status = task.findtext('status', 'pending')
                    created_at = task.findtext('created_at')
                    archived_at = task.findtext('archived_at')
                    
                    cursor.execute(
                        "INSERT INTO archive_tasks (id, user_id, title, description, status, created_at, archived_at) VALUES (%s, %s, %s, %s, %s, %s, %s)",
                        (task_id, user_id, title, description, status, created_at, archived_at)
                    )
                    sync_count += 1
                
                conn.commit()
                logger.info(f"  Synced {sync_count} archived tasks")
            
            cursor.close()
            conn.close()
            
            logger.info("MySQL sync complete!")
            return True
            
        except Exception as e:
            logger.error(f"MySQL sync failed: {e}")
            return False
    
    def sync_from_mysql(self):
        """Restore XML files from MySQL (recovery)"""
        logger.info("Starting MySQL → XML restore...")
        
        try:
            import pymysql 
        except ImportError:
            logger.error("pymysql not installed. Install with: pip install pymysql")
            return False
        
        try:
            conn = pymysql.connect(
                host='localhost',
                user='root',
                password='',
                database='task_tracker',
                connect_timeout=5
            )
            cursor = conn.cursor(pymysql.cursors.DictCursor)
            
            # Restore users
            users_root = ET.Element('users')
            cursor.execute("SELECT * FROM users")
            for row in cursor.fetchall():
                user_elem = ET.SubElement(users_root, 'user', id=str(row['id']))
                ET.SubElement(user_elem, 'username').text = row['username']
                ET.SubElement(user_elem, 'password_hash').text = row['password_hash']
                ET.SubElement(user_elem, 'role').text = row['role']
                ET.SubElement(user_elem, 'created_at').text = str(row['created_at'])
            
            self.optimizer.save_xml(users_root, self.optimizer.users_file)
            logger.info(f"  Restored users")
            
            # Restore tasks
            tasks_root = ET.Element('tasks')
            cursor.execute("SELECT * FROM tasks")
            for row in cursor.fetchall():
                task_elem = ET.SubElement(tasks_root, 'task', id=str(row['id']), user_id=str(row['user_id']))
                ET.SubElement(task_elem, 'title').text = row['title']
                ET.SubElement(task_elem, 'description').text = row['description']
                ET.SubElement(task_elem, 'status').text = row['status']
                ET.SubElement(task_elem, 'created_at').text = str(row['created_at'])
            
            self.optimizer.save_xml(tasks_root, self.optimizer.tasks_file)
            logger.info(f"  Restored tasks")
            
            # Restore archive
            archive_root = ET.Element('archive_tasks')
            cursor.execute("SELECT * FROM archive_tasks")
            for row in cursor.fetchall():
                task_elem = ET.SubElement(archive_root, 'task', id=str(row['id']), user_id=str(row['user_id']))
                ET.SubElement(task_elem, 'title').text = row['title']
                ET.SubElement(task_elem, 'description').text = row['description']
                ET.SubElement(task_elem, 'status').text = row['status']
                ET.SubElement(task_elem, 'created_at').text = str(row['created_at'])
                ET.SubElement(task_elem, 'archived_at').text = str(row['archived_at'])
            
            self.optimizer.save_xml(archive_root, self.optimizer.archive_file)
            logger.info(f"  Restored archived tasks")
            
            cursor.close()
            conn.close()
            
            logger.info("MySQL restore complete!")
            return True
            
        except Exception as e:
            logger.error(f"MySQL restore failed: {e}")
            return False


def main():
    parser = argparse.ArgumentParser(
        description='XML Sync & Optimization Utility',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog='''
Examples:
  python3 xml_sync_optimizer.py --compact      # Minimize XML files
  python3 xml_sync_optimizer.py --sync         # Sync XML to MySQL
  python3 xml_sync_optimizer.py --restore      # Restore XML from MySQL
  python3 xml_sync_optimizer.py --prune 30     # Remove archive > 30 days
  python3 xml_sync_optimizer.py --status       # Show file stats
        '''
    )
    
    parser.add_argument('--compact', action='store_true', help='Compact all XML files')
    parser.add_argument('--sync', action='store_true', help='Sync XML to MySQL')
    parser.add_argument('--restore', action='store_true', help='Restore XML from MySQL')
    parser.add_argument('--prune', type=int, metavar='DAYS', help='Prune archive older than N days')
    parser.add_argument('--status', action='store_true', help='Show XML file statistics')
    
    args = parser.parse_args()
    
    optimizer = XMLOptimizer()
    syncer = XMLMySQLSync()
    
    if args.compact:
        optimizer.compact_xml()
    
    elif args.sync:
        syncer.sync_to_mysql()
    
    elif args.restore:
        syncer.sync_from_mysql()
    
    elif args.prune:
        optimizer.prune_archive(args.prune)
    
    elif args.status:
        stats = optimizer.get_file_stats()
        print("\n=== XML File Statistics ===\n")
        for filename, data in stats.items():
            print(f"{filename}:")
            print(f"  Size: {data['size_bytes']:,} bytes")
            print(f"  Items: {data['items']}")
            print(f"  Modified: {data['modified']}\n")
    
    else:
        parser.print_help()


if __name__ == '__main__':
    main()
