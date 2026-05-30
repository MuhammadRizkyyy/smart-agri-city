import os
import sys
import subprocess

if __name__ == "__main__":
    current_dir = os.path.dirname(os.path.abspath(__file__))
    premium_simulator = os.path.join(current_dir, "simulator.py")
    
    if os.path.exists(premium_simulator):
        try:
            sys.exit(subprocess.call([sys.executable, premium_simulator] + sys.argv[1:]))
        except KeyboardInterrupt:
            sys.exit(0)
    else:
        print(f"Error: Premium simulator not found at '{premium_simulator}'", file=sys.stderr)
        sys.exit(1)