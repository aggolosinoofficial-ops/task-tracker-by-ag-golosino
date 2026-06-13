import os
import time
import portalocker  # type: ignore
from pathlib import Path
import subprocess

def diagnose_file_lock(filename, cleanup=False):
    base_dir = Path(r'c:\Users\Atheena\Desktop\task-tracker-by-ag-golosino')
    xml_path = base_dir / 'data' / f"{filename}.xml"
    lock_path = base_dir / 'data' / f"{filename}.xml.lock"
    
    print(f"--- Diagnosing Lock Status for {filename}.xml ---")
    
    # 1. Check if the XML file exists
    if not xml_path.is_file():
        print(f"[INFO] XML file does not exist: {xml_path.name}")
        return # Nothing to check

    # 2. Check for sidecar lock file (.lock)
    if lock_path.exists():
        mtime = os.path.getmtime(lock_path)
        age = time.time() - mtime
        print(f"[FOUND] Sidecar lock file exists: {lock_path.name}")
        print(f"        - Age: {age:.1f} seconds")
        if age > 15: # A lock older than 15s is suspicious for a web app
            print("        - [WARNING] This might be an orphaned lock from a crashed process.")
            if cleanup:
                try:
                    os.remove(lock_path)
                    print(f"        - [FIXED] Removed orphaned lock file.")
                except PermissionError:
                    print("        - [BLOCKED] A background process is still holding this file open.")
                    print("                  You MUST run this in PowerShell: taskkill /F /IM python.exe")
                    print("                  Then run this script again with --fix")
                except Exception as e:
                    print(f"        - [ERROR] Could not remove lock: {e}")
    else:
        print("[CLEAN] No sidecar .lock file found.")

    # 3. Test for OS-level file handle locks
    # os.replace fails if ANY process has a handle open to the target file.
    try:
        # A robust way to check for an OS-level lock is to try to rename the file.
        # If it's locked by another process, this will raise a PermissionError.
        temp_name = xml_path.with_suffix('.tmp_lock_check')
        os.rename(xml_path, temp_name)
        # If the rename succeeds, immediately rename it back to restore state.
        os.rename(temp_name, xml_path)
        print("[SUCCESS] No external process is locking the actual XML file.")
    except PermissionError:
        print("[CRITICAL] A non-Python process has a lock on the file.")
        print("           Check if it's open in VS Code, Notepad++, Excel, etc.")
        find_locking_process(str(xml_path))
    except Exception as e:
        # This can catch portalocker.LockException if the app is running
        if "lock" in str(e).lower():
            print("[LOCKED] A Python process (likely the app) has an active lock on the file.")
            print("         This is normal if the app is busy, but a problem if it's stuck.")
        else:
            print(f"[ERROR] Unexpected error during OS lock check: {e}")

def find_locking_process(file_path):
    """Uses Windows' `handle.exe` utility to find which process is locking a file."""
    print("         Attempting to find the locking process...")
    try:
        # Assumes handle.exe from Sysinternals is in the system PATH
        result = subprocess.run(
            ['handle.exe', '-a', file_path], 
            capture_output=True, text=True, check=True, timeout=5
        )
        output = result.stdout
        if "No matching handles found." in output:
            print("         Could not identify a specific process. The lock may be transient.")
        else:
            print("\n--- Process(es) with a handle to the file ---")
            print(output)
            print("---------------------------------------------")
    except FileNotFoundError:
        print("         [INFO] `handle.exe` not found. Download from Microsoft Sysinternals")
        print("                and add it to your system PATH to enable this check.")
    except subprocess.CalledProcessError as e:
        print(f"         Error running handle.exe: {e.stderr}")
    except subprocess.TimeoutExpired:
        print("         `handle.exe` timed out.")


if __name__ == "__main__":
    # Check your main data files
    import sys
    should_fix = '--fix' in sys.argv
    for db_file in ["tasks", "users", "archive_tasks"]:
        diagnose_file_lock(db_file, cleanup=should_fix)
        print("-" * 40)