import os
import sys

def generate_road_map():
    # Get the project root path (current working directory)
    project_root = os.path.abspath(os.getcwd())
    
    # List to store all paths
    all_paths = []
    
    # List of directories to exclude
    exclude_dirs = ['venv', '.git', 'vendor']
    
    # Recursively traverse the directory structure
    for root, dirs, files in os.walk(project_root, topdown=True):
        # Sort for consistent output
        dirs.sort()
        files.sort()
        
        # Filter out excluded directories
        dirs[:] = [d for d in dirs if d not in exclude_dirs]
        
        # Check if current directory should be excluded
        if any(ex_dir in root for ex_dir in exclude_dirs):
            continue
            
        # Add current directory path
        all_paths.append(root)
        
        # Add paths of all files in current directory
        for file in files:
            file_path = os.path.join(root, file)
            all_paths.append(file_path)
    
    # Output file path
    output_file = os.path.join(project_root, "road_map.txt")
    
    # Write paths to output file
    try:
        with open(output_file, 'w', encoding='utf-8') as f:
            for path in all_paths:
                f.write(path + '\n')
        print(f"✓ Road map successfully generated: {output_file}")
        print(f"✓ Total paths recorded: {len(all_paths)}")
    except Exception as e:
        print(f"✗ Error creating output file: {e}")
        sys.exit(1)

if __name__ == "__main__":
    generate_road_map()