import os
import sys
try:
    import pythoncom
    from win32com.shell import shell, shellcon  # type: ignore
    print("[✓] Success: win32com.shell and pythoncom modules imported.")
except ImportError as e:
    print(f"[✗] Import Error: {e}")
    print("    This usually means the post-install script didn't copy the DLLs to System32.")
    sys.exit(1)

def test_shell_extensions():
    print("--- Starting Shell Extension Verification ---")
    
    # 1. Test COM Initialization
    try:
        pythoncom.CoInitialize()
        print("[✓] COM subsystem initialized successfully.")
    except Exception as e:
        print(f"[✗] COM Initialization failed: {e}")

    # 2. Test Special Folder Access (uses shell extensions)
    try:
        desktop_path = shell.SHGetSpecialFolderPath(0, shellcon.CSIDL_DESKTOP, False)
        print(f"[✓] SHGetSpecialFolderPath retrieved Desktop: {desktop_path}")
    except Exception as e:
        print(f"[✗] Failed to retrieve special folder: {e}")

    # 3. Test Shell Link (Shortcut) Capability
    try:
        shortcut = pythoncom.CoCreateInstance(
            shell.CLSID_ShellLink, None,
            pythoncom.CLSCTX_INPROC_SERVER, shell.IID_IShellLink
        )
        print("[✓] ShellLink COM object created (Extension registered).")
    except Exception as e:
        print(f"[✗] Failed to create ShellLink object: {e}")

if __name__ == "__main__":
    test_shell_extensions()
    print("--- Verification Complete ---")