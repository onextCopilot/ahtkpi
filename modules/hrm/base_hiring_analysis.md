# Base Hiring UI/UX Analysis for Recruitment Module

This document summarizes the research on Base Hiring's (hiring.base.vn) recruitment module structure, used as a reference for building the HRM Recruitment functionality in AHTKPI.

## 1. Job Detail Page (Pipeline View)
- **URL Pattern:** `https://hiring.base.vn/opening/[id]`
- **Layout:**
    - **Header:** Displays Job Title, ID, Status (Draft/Published), Date Range, Candidate Count, and Public Link.
    - **Main Content (Kanban/Pipeline):** A horizontal board showing hiring stages as columns.
        - Default stages: Applied (Nhận hồ sơ), Interview (Phỏng vấn), Client Interview, Offered, Hired, Rejected.
    - **Action Buttons:** Quick access to "All Candidates", "Add Candidate", "AI Reading".
    - **Navigation:** Switching between views (Board, List, Calendar) via top tabs or icons.

## 2. Job Edit Page (Configuration View)
- **URL Pattern:** `https://hiring.base.vn/opening/edit/[id]/[section]`
- **Layout:**
    - **Secondary Sidebar:** Vertical menu to switch between configuration sections.
    - **Main Content Area:** The form/settings for the active section.
- **Key Configuration Sections:**
    1.  **Job Information (Thông tin tuyển dụng):** Basic details like Title, Dept, Salary, JD, etc.
    2.  **Competences (Tiêu chí):**
        - Evaluation criteria with "Target Score" and "Weight (%)".
        - Ability to add from library or quick-add.
        - Mandatory requirements for filtering.
    3.  **Hiring Workflow (Quy trình tuyển dụng):**
        - List of stages that can be reordered.
        - Each stage can be linked to Email Templates or Interview Forms.
    4.  **Application Form (Đơn ứng tuyển):**
        - Toggles for "Show" and "Required" for standard fields (Name, Phone, CV, etc.).
        - Section for adding custom questions.
    5.  **Evaluation Forms & Interview Questions:** Specialized tools for creating templates.
    6.  **Publishing (Đăng tin):** Preview and final settings before making the post public.

## 3. UI/UX Principles Observed
- **Sticky Header:** Basic job info is always visible during editing.
- **Linear Navigation:** "Continue" buttons guide users through the 7-8 step setup process.
- **Library Reuse:** Strong emphasis on selecting from pre-defined criteria, email templates, and interview questions.
- **Context Preservation:** The global sidebar remains fixed, while the secondary sidebar changes based on the module.

---
*Last updated: 2026-05-08*
