# System Architecture: Task Tracker (XML-Only)

## 1. Architectural Overview
The Task Tracker system now follows an **XML-Only Architecture**. It treats local XML files as the **sole and primary source of truth** for all application data. This design completely removes any dependency on a Relational Database Management System (RDBMS) like MySQL.
 
This design is specifically optimized for environments with limited resources (e.g., 2GB RAM) and requires high resilience against database downtime.

## 2. Core Components
### 2.1 Presentation Layer
- **Technology:** Flask (Python) with Jinja2 Templating.
- **Interaction:** Asynchronous search and live status updates using Chart.js.

### 2.2 Business Logic Layer (Python Services)
- **AuthService:** Manages user lifecycles and Bcrypt-based security.
- **TaskService:** Encapsulates CRUD logic, search filtering, and dashboard telemetry.
- **ArchiveService:** Handles the state transition of tasks between active and archival storage.
- **ActivityService:** Provides a non-intrusive audit log of system events.

### 2.3 Data Access Abstraction (`XMLService`)
- Acts as the gatekeeper for all I/O operations.
- Implements **Memory Caching** to reduce disk reads.
- Enforces data integrity via **XSD (XML Schema Definition)** before any write operation.

### 2.4 Maintenance & DevOps
- **`xml_sync_optimizer.py`:** A CLI utility for file compaction (whitespace removal) and pruning.

## 3. Data Flow (Write Path)
1. **Request:** User submits a data modification (e.g., task update, new user).
2. **Service Logic:** The `TaskService` processes business rules.
3. **XML Validation:** `XMLService` builds a candidate tree and validates it against `tasks.xsd`.
4. **Primary Write:** Upon successful validation, the XML file on disk is updated.
5. **Sync Trigger:** The system logs a sync requirement for the MySQL fallback.

## 4. Resilience Strategy
- **Offline-First:** If MySQL is unreachable, the system continues to function normally using XML.
- **Schema Enforcement:** Use of XSD prevents XML "bloat" or corruption.
- **Resource Awareness:** Lazy-loading and compaction scripts ensure the application remains responsive on systems with minimal RAM.