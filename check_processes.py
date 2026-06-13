import subprocess
import os

def check_active_processes():
    print("--- Checking for Active Python Processes ---")
    
    try:
        # Use tasklist with Verbose flag to see which scripts are running
        result = subprocess.run(['tasklist', '/FI', 'IMAGENAME eq python.exe', '/V'], 
                                capture_output=True, text=True, check=True)
        
        output = result.stdout
        print(output)

        # Check if the output contains references to your app or the debugger
        if "app.py" in output or "Flask" in output:
            print("\n[!] ALERT: Lingering app processes detected.")
            print("You can kill them using: taskkill /F /IM python.exe")
        else:
            print("\n[✓] No orphaned app processes found.")
            
    except subprocess.CalledProcessError:
        print("No python.exe processes are currently running.")
    except Exception as e:
        print(f"An error occurred while checking: {e}")

if __name__ == "__main__":
    check_active_processes()
    # Keep the window open if run via double-click
    input("\nPress Enter to exit...")