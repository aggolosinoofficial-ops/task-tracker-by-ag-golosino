# Python XML/XSD Scripts Documentation

## Overview

These Python scripts provide comprehensive tools for working with XML and XSD files in your to-do application. They demonstrate XML validation, XPath querying, and CRUD operations.

## Files

### 1. **xml_display.py** - Simple Display Tool

Display and validate XML/XSD files with basic information.

**Features:**

- Load and parse XML files
- Validate against XSD schema
- Display file status and metadata
- Pretty print XML content
- Show task statistics
- Extract and display XSD schema information

**Usage:**

```bash
python xml_display.py
```

**Output:**

- File existence checks
- XML validation results
- Task list in table format
- Task statistics (totals, status breakdown)
- Raw XML content
- XSD schema information

### 2. **xml_advanced.py** - Comprehensive Advanced Tool

Complete demonstration of XML/XSD/XPath concepts with CRUD operations.

**Features:**

- Full XML/XSD/XPath concept explanations
- Advanced XPath queries
- XSD schema analysis
- Complete CRUD operations (Create, Read, Update, Delete)
- Task filtering and searching
- Validation with detailed error reporting
- Comprehensive statistics and reporting

**Classes:**

#### `AdvancedXMLHandler`

**Basic Operations:**

- `load_xml()` - Load XML document
- `save_xml()` - Save XML document back to file

**XPath Queries:**

- `xpath_get_all_tasks()` - Get all tasks
- `xpath_get_tasks_by_status(status)` - Filter by status
- `xpath_get_tasks_by_user(user_id)` - Filter by user
- `xpath_get_task_ids()` - Get all task IDs
- `xpath_get_task_by_id(task_id)` - Get specific task
- `xpath_search_title(keyword)` - Search by title
- `xpath_count_tasks()` - Count total tasks

**CRUD Operations:**

- `create_task(task_data)` - Add new task
- `read_task(task_id)` - Get task as dictionary
- `update_task(task_id, updates)` - Update fields
- `delete_task(task_id)` - Remove task

**Validation & Analysis:**

- `validate_against_xsd()` - Validate XML against XSD
- `analyze_schema()` - Extract schema structure
- `demo_xpath_queries()` - Show XPath examples

**Reporting:**

- `print_all_tasks()` - Display all tasks in table
- `print_task_statistics()` - Show statistics
- `print_schema_info()` - Display schema details

**Usage:**

```bash
python xml_advanced.py
```

### 3. **generate_sample_data.py** - Sample Data Generator

Populate tasks.xml with realistic test data.

**Features:**

- Generate realistic sample tasks
- Vary user IDs, statuses, and dates
- Pretty-print XML output
- Create 15 sample tasks by default

**Usage:**

```bash
# Generate sample data
python generate_sample_data.py

# Now run display scripts
python xml_display.py
python xml_advanced.py
```

## Concepts Explained

### XML (eXtensible Markup Language)

- Hierarchical, tree-based data format
- Self-describing: tags define meaning
- Example structure:

```xml
<tasks>
    <task>
        <id>1</id>
        <title>Buy groceries</title>
        <status>pending</status>
    </task>
</tasks>
```

### XSD (XML Schema Definition)

- Defines rules for XML document structure
- Specifies: element names, types, constraints
- Validates:
  - Data types (integer, string, dateTime)
  - Required fields
  - String length constraints
  - Enumeration values

Example XSD element:

```xml
<xs:element name="id" type="xs:positiveInteger"/>
<xs:element name="title" type="xs:string" minLength="1" maxLength="255"/>
<xs:element name="status" type="statusType"/>
```

### XPath (XML Path Language)

- Query language for navigating XML trees
- Used to select elements from XML documents

Common XPath expressions:

```
.//task                    - Select all task elements
.//task/title              - Select all title elements within tasks
.//task/id                 - Select all id elements within tasks
count(.//task)             - Count total tasks
.//task[status='pending']  - Tasks with status='pending'
```

### Validation

- Check XML against XSD schema rules
- Ensures data types are correct
- Validates required fields
- Checks constraints (min/max length, enum values)

Methods:

- **lxml**: Fast, C-based XML processing (recommended)
- **ElementTree**: Built-in Python module

### CRUD Operations

- **CREATE**: Add new elements to XML
- **READ**: Query and retrieve elements
- **UPDATE**: Modify element values
- **DELETE**: Remove elements from tree

## Installation Requirements

### Basic (Built-in Python):

```bash
# No external dependencies needed
python xml_display.py
python xml_advanced.py
```

### Enhanced (with lxml - Recommended):

```bash
pip install lxml
python xml_advanced.py
```

This enables:

- Faster XML processing
- Better XSD validation with detailed error messages
- More robust parsing

## Usage Examples

### Example 1: Display All Tasks

```bash
python xml_display.py
```

### Example 2: Run Complete Demo with Concepts

```bash
# First, generate sample data
python generate_sample_data.py

# Then run advanced demo
python xml_advanced.py
```

### Example 3: Use in Your Code

```python
from xml_advanced import AdvancedXMLHandler

# Initialize
handler = AdvancedXMLHandler()
handler.load_xml()

# Get all tasks
tasks = handler.xpath_get_all_tasks()

# Find specific task
task = handler.read_task(task_id=1)
print(f"Task: {task['title']} - {task['status']}")

# Add new task
handler.create_task({
    'user_id': 1,
    'title': 'New Task',
    'description': 'Task description',
    'status': 'pending'
})

# Update task
handler.update_task(task_id=1, updates={'status': 'completed'})

# Delete task
handler.delete_task(task_id=1)

# Save changes
handler.save_xml()
```

## Integration with PHP Code

These Python scripts complement your PHP backend:

**PHP handles:**

- Web server requests
- User authentication
- Database operations
- HTTP responses

**Python scripts handle:**

- Local XML file processing
- Data validation
- Reporting and analysis
- Testing and debugging

**Example workflow:**

1. User adds task via PHP web interface
2. PHP adds to MySQL or XML backend
3. Use Python script to analyze/validate XML
4. Generate reports using Python tools

## File Structure

```
tasks.xml              - Main data file (generated/managed)
tasks.xsd              - Schema definition (validation rules)
xml_display.py         - Simple display tool
xml_advanced.py        - Advanced features demo
generate_sample_data.py - Data generator
```

## Troubleshooting

### "XML file not found"

- Run `python generate_sample_data.py` first
- Check file path is correct

### "lxml not available"

- Install with: `pip install lxml`
- Scripts still work without it, but with reduced functionality

### "Validation errors"

- Check XML matches XSD schema
- Verify required fields are present
- Ensure data types match (e.g., ID must be positive integer)

### Permission errors on Windows

- Make sure Python is installed and in PATH
- Run from PowerShell or Command Prompt
- Check file permissions on tasks.xml

## Advanced Features

### Custom XPath Queries

```python
# Find all pending tasks for user 5
handler.xpath_get_tasks_by_user(5)
handler.xpath_get_tasks_by_status('pending')

# Search by title keyword
results = handler.xpath_search_title('buy')
```

### Schema Validation

```python
# Validate XML
validation = handler.validate_against_xsd()
if validation['valid']:
    print("✓ XML is valid")
else:
    for error in validation['errors']:
        print(f"Error: {error['message']}")
```

### Batch Operations

```python
# Create multiple tasks
for i in range(5):
    handler.create_task({
        'user_id': 1,
        'title': f'Task {i}',
        'status': 'pending'
    })

handler.save_xml()
```

## Performance Notes

- **xml_display.py**: Fast, good for reporting (< 100ms)
- **xml_advanced.py**: Comprehensive, with validation (< 200ms)
- **generate_sample_data.py**: One-time setup (< 50ms)

For files with thousands of tasks, consider:

- Using MySQL backend for better performance
- Implementing pagination in display
- Using database indexes

## Support & Documentation

- Python XML docs: https://docs.python.org/3/library/xml.html
- XPath Tutorial: https://www.w3.org/TR/xpath/
- XSD Guide: https://www.w3.org/XML/Schema

## License

Same as your to-do app project

## Next Steps

1. **Run sample data generator:**

   ```bash
   python generate_sample_data.py
   ```

2. **View XML content:**

   ```bash
   python xml_display.py
   ```

3. **Explore advanced features:**

   ```bash
   python xml_advanced.py
   ```

4. **Integrate into PHP if needed:**
   - Use `exec()` or `shell_exec()` in PHP
   - Parse JSON output from Python
   - Handle errors gracefully
