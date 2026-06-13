import subprocess
import os

def check_active_processes():
    print("--- Checking for Active Python Processes ---")
    
    try:
        # Use wmic to get the command line for each python process
        command = 'wmic process where "name=\'python.exe\'" get commandline'
        result = subprocess.run(command, 
                                capture_output=True, text=True, check=True)
        
        output = result.stdout
        lines = [line.strip() for line in output.splitlines() if line.strip() and 'python.exe' in line]
        
        print("--- Active Python Commands ---")
        for line in lines:
            print(f"  - {line}")
        print("----------------------------")

        # Check if the output contains references to your app or the debugger
        if any("app.py" in line for line in lines):
            print("\n[!] ALERT: Your Flask application process ('app.py') is running.")
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