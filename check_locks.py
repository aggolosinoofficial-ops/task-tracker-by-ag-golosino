import os
import time
import portalocker
from pathlib import Path

def diagnose_file_lock(filename, cleanup=False):
    base_dir = Path(r'c:\Users\Atheena\Desktop\task-tracker-by-ag-golosino')
    xml_path = base_dir / 'data' / f"{filename}.xml"
    lock_path = base_dir / 'data' / f"{filename}.xml.lock"
    
    print(f"--- Diagnosing Lock Status for {filename}.xml ---")
    
    # 1. Check if the XML file exists
    if not xml_path.exists():
        print(f"Error: {xml_path} does not exist.")
        return

    # 2. Check for sidecar lock file (.lock)
    if lock_path.exists():
        mtime = os.path.getmtime(lock_path)
        age = time.time() - mtime
        print(f"[FOUND] Sidecar lock file exists: {lock_path.name}")
        print(f"        Age: {age:.1f} seconds")
        if age > 30:
            print("        Warning: This might be an orphaned lock from a crashed process.")
            if cleanup:
                try:
                    # Try direct removal; if this fails with PermissionError, the process is alive
                    os.remove(lock_path)
                    print(f"        [FIXED] Removed orphaned lock file.")
                except PermissionError:
                    print("        [BLOCK] A background process (Zombie) is still holding this file open.")
                    print("                You MUST run this in PowerShell: taskkill /F /IM python.exe")
                    print("                Then run this script again with --fix")
                except Exception as e:
                    print(f"        [ERROR] Could not remove lock: {e}")
    else:
        print("[CLEAN] No sidecar .lock file found.")

    # 3. Test for Windows File System Handle
    # os.replace fails if ANY process has a handle open to the target file.
    try:
        # Attempting to open for appending/writing tests the OS-level lock
        with open(xml_path, 'r+b') as f:
            try:
                # Try to acquire an exclusive lock via portalocker
                portalocker.lock(f, portalocker.LOCK_EX | portalocker.LOCK_NB)
                print("[SUCCESS] No external process is locking the actual XML file.")
                portalocker.unlock(f)
            except portalocker.exceptions.LockException:
                print("[LOCKED] The file is currently locked by another Python instance/thread.")
    except PermissionError:
        print("[CRITICAL] Access Denied: Another process (Excel, VS Code, etc.)")
        print("           has an open handle to this file. Close other apps and try again.")
    except Exception as e:
        print(f"[ERROR] Unexpected error: {e}")

if __name__ == "__main__":
    # Check your main data files
    import sys
    should_fix = '--fix' in sys.argv
    for db_file in ["tasks", "users", "archive_tasks"]:
        diagnose_file_lock(db_file, cleanup=should_fix)
        print("-" * 40)