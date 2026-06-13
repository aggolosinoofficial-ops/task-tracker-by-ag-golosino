import os
import sys
import pythoncom
from win32com.shell import shell, shellcon  # type: ignore
import subprocess
import win32com.client

def create_flask_shortcut():
    """
    Uses pywin32 shell extensions to create a Windows shortcut (.lnk)
    pointing to the Flask application.
    """
    # 1. Setup paths
    # Using absolute paths ensures the shortcut works regardless of the starting directory
    project_dir = r'c:\Users\Atheena\Desktop\task-tracker-by-ag-golosino'
    app_script = os.path.join(project_dir, 'app.py')
    python_exe = sys.executable  # Points to the python interpreter running this script
    
    # Define the path to your custom icon file
    icon_path = os.path.join(project_dir, 'favicon.ico') 
    
    # Retrieve the Desktop path reliably using Windows Shell API
    desktop = shell.SHGetSpecialFolderPath(0, shellcon.CSIDL_DESKTOP, False)
    shortcut_path = os.path.join(desktop, "Task Tracker.lnk")

    try:
        # 2. Initialize the COM object for Shell Links
        shortcut = pythoncom.CoCreateInstance(
            shell.CLSID_ShellLink, None,
            pythoncom.CLSCTX_INPROC_SERVER, shell.IID_IShellLink
        )

        # 3. Configure the shortcut properties
        # Target: python.exe, Argument: app.py
        shortcut.SetPath(python_exe)
        shortcut.SetArguments(f'"{app_script}"')
        shortcut.SetWorkingDirectory(project_dir)
        shortcut.SetDescription("Launch Task Tracker Flask App")

        # 3.5 Set the custom icon if the file exists
        if os.path.exists(icon_path):
            shortcut.SetIconLocation(icon_path, 0)

        # 4. Save the shortcut to disk via the IPersistFile interface
        persist_file = shortcut.QueryInterface(pythoncom.IID_IPersistFile)
        persist_file.Save(shortcut_path, 0)
        print(f"[✓] Shortcut created successfully on Desktop: {shortcut_path}")

        # 5. Attempt to pin to Taskbar
        pin_shortcut_to_taskbar(shortcut_path)

    except Exception as e:
        print(f"[✗] Error creating shortcut: {e}")

def pin_shortcut_to_taskbar(path):
    """Attempts to pin via COM, falling back to PowerShell if the verb isn't found."""
    try:
        # 1. Try standard COM approach
        shell_app = win32com.client.Dispatch("Shell.Application")
        directory = os.path.dirname(path)
        file_name = os.path.basename(path)
        folder = shell_app.NameSpace(directory)
        item = folder.ParseName(file_name)

        verbs = item.Verbs()
        for v in verbs:
            if v.Name.replace("&", "") == "Pin to taskbar":
                v.DoIt()
                print("[✓] Shortcut pinned to Taskbar.")
                return

        # 2. Fallback to PowerShell
        print("[!] COM method failed to find verb. Attempting PowerShell fallback...")
        ps_command = (
            f"$shell = New-Object -ComObject Shell.Application; "
            f"$folder = $shell.NameSpace('{directory}'); "
            f"$item = $folder.ParseName('{file_name}'); "
            f"$verb = $item.Verbs() | Where-Object {{ $_.Name.Replace('&', '') -match 'Pin to taskbar' }}; "
            f"if ($verb) {{ $verb.DoIt(); write-output 'Success' }} else {{ write-output 'Fail' }}"
        )

        result = subprocess.run(
            ["powershell", "-Command", ps_command],
            capture_output=True, text=True
        )

        if "Success" in result.stdout:
            print("[✓] Shortcut pinned to Taskbar via PowerShell.")
        else:
            print("[!] PowerShell also failed. This functionality is likely restricted by your OS version.")

    except Exception as e:
        print(f"[!] Could not pin to taskbar: {e}")

if __name__ == "__main__":
    create_flask_shortcut()