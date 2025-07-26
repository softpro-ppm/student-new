# Database v2.0 Schema Improvements Plan

## Overview
Upgrading from v1.0 (u820431346_smis) to v2.0 (student_management_v2) with improved structure, naming conventions, and features.

## Current v1.0 Issues Identified:
1. **Inconsistent naming conventions** (mix of `tbl` prefix and non-prefixed tables)
2. **Non-standard column names** (CamelCase vs snake_case)
3. **Missing foreign key constraints**
4. **No proper indexes for performance**
5. **Weak data validation** 
6. **Missing audit trails**
7. **Inefficient EMI/Payment structure**
8. **No proper user roles management**

## v2.0 Improvements:

### 1. **Standardized Naming Convention**
- All tables: `snake_case` (no `tbl` prefix)
- All columns: `snake_case`
- Clear, descriptive names

### 2. **Improved Table Structure**

#### **Core Tables:**
- `users` - Unified user management (admin, training_partners, students)
- `training_centers` - Training center details
- `students` - Student information (replaces tblcandidate)
- `sectors` - Skill development sectors
- `schemes` - Government schemes
- `job_roles` - Job roles under schemes
- `courses` - Training courses
- `batches` - Training batches
- `batch_students` - Many-to-many relationship

#### **Financial Tables:**
- `fees` - Comprehensive fee management
- `payments` - Payment transactions (replaces payment & emi_list)
- `invoices` - Proper invoicing system

#### **Academic Tables:**
- `assessments` - Assessment/exam management
- `results` - Student results
- `certifications` - Certificate tracking
- `placements` - Placement records

#### **System Tables:**
- `audit_logs` - Complete audit trail
- `notifications` - System notifications
- `settings` - Application settings

### 3. **Enhanced Features**
- **Proper foreign key relationships**
- **Data validation constraints**
- **Audit trails for all changes**
- **Soft deletes** (status-based)
- **Comprehensive indexing**
- **JSON fields for flexible data**
- **Timestamp tracking**

### 4. **Security Improvements**
- **Password hashing** (bcrypt)
- **Role-based access control**
- **API tokens** for authentication
- **Input validation**

### 5. **Performance Optimizations**
- **Strategic indexes**
- **Query optimization**
- **Proper data types**
- **Normalized structure**

## Migration Strategy:
1. Create v2.0 database schema
2. Map v1.0 data to v2.0 structure
3. Data transformation and cleaning
4. Validation and testing
5. Gradual cutover

## Database Names:
- **v1.0 (Current):** `u820431346_smis`
- **v2.0 (New):** `student_management_v2`

## Timeline:
- Schema Creation: Immediate
- Migration Scripts: Next
- Testing: After migration
- Go-live: After validation
