#!/usr/bin/env python3
"""
XML Sync & Optimization Utility
Lightweight Python script for offline XML operations
2GB RAM optimization: compact storage, lazy parsing, minimal nesting

Usage:
    python3 xml_sync_optimizer.py --compact      # Minimize XML file size
    python3 xml_sync_optimizer.py --status       # Check storage status
    python3 xml_sync_optimizer.py --prune        # Remove old archived tasks
"""

import xml.etree.ElementTree as ET
import sys
import os
import time
from datetime import datetime, timedelta
import argparse
import logging
try:
    portalocker = None
    import portalocker
    HAS_PORTALOCKER = True
except ImportError:
    portalocker = None
    HAS_PORTALOCKER = False

# Configure logging (minimal overhead)
logging.basicConfig(
    level=logging.INFO,
    format='[%(asctime)s] %(levelname)s: %(message)s'
)
logger = logging.getLogger(__name__)

class XMLOptimizer:
    """Lightweight XML optimization for 2GB RAM systems"""
    
    def __init__(self):
        self.users_file = os.path.join('data', 'users.xml')
        self.tasks_file = os.path.join('data', 'tasks.xml')
        self.archive_file = os.path.join('data', 'archive_tasks.xml')
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

            # Use a sidecar lock if portalocker is available to match XMLService behavior
            lock_path = filename + ".lock"
            lock = portalocker.Lock(lock_path, timeout=5) if (HAS_PORTALOCKER and portalocker is not None) else None

            def perform_write():
                tree.write(filename, encoding='utf-8', xml_declaration=True)

            if lock:
                with lock:
                    perform_write()
            else:
                perform_write()

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


def main():
    parser = argparse.ArgumentParser(
        description='XML Sync & Optimization Utility',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog='''
Examples:
  python3 xml_sync_optimizer.py --compact      # Minimize XML files
  python3 xml_sync_optimizer.py --prune 30     # Remove archive > 30 days
  python3 xml_sync_optimizer.py --status       # Show file stats
        '''
    )
    
    parser.add_argument('--compact', action='store_true', help='Compact all XML files')
    parser.add_argument('--prune', type=int, metavar='DAYS', help='Prune archive older than N days')
    parser.add_argument('--status', action='store_true', help='Show XML file statistics')
    
    args = parser.parse_args()
    
    optimizer = XMLOptimizer()
    
    if args.compact:
        optimizer.compact_xml()
    
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
