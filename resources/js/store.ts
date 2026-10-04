import { ref } from "vue";
import type { SidebarMenu } from "./types";

export let isDarkTheme = ref(false);

export const menuItems = ref<SidebarMenu>([
    {
        header: "Main",
        items: [
            {
                title: "Dashboard",
                icon: "ti ti-layout-dashboard",
                link: "/dashboard",
            },
            {
                title: "Application",
                icon: "ti ti-layout-list",
                submenu: [
                    { title: "Chat", link: "chat.html" },
                    { title: "Call", link: "call.html" },
                    { title: "Calendar", link: "calendar.html" },
                    { title: "Email", link: "email.html" },
                    { title: "To Do", link: "todo.html" },
                    { title: "Notes", link: "notes.html" },
                    { title: "File Manager", link: "file-manager.html" },
                ],
            },
        ],
    },
    {
        header: "User Management",
        items: [
            {
                title: "Students",
                icon: "ti ti-school",
                submenu: [
                    { title: "All Students", link: "student-grid.html" },
                    { title: "Student List", link: "students.html" },
                    { title: "Student Details", link: "student-details.html" },
                    { title: "Student Promotion", link: "student-promotion.html" },
                ],
            },
            {
                title: "Parents",
                icon: "ti ti-user-bolt",
                submenu: [
                    { title: "All Parents", link: "parent-grid.html" },
                    { title: "Parent List", link: "parents.html" },
                ],
            },
            {
                title: "Guardians",
                icon: "ti ti-user-shield",
                submenu: [
                    { title: "All Guardians", link: "guardian-grid.html" },
                    { title: "Guardian List", link: "guardians.html" },
                ],
            },
            {
                title: "Teachers",
                icon: "ti ti-users",
                submenu: [
                    { title: "All Teachers", link: "teacher-grid.html" },
                    { title: "Teacher List", link: "teachers.html" },
                    { title: "Teacher Details", link: "teacher-details.html" },
                    { title: "Routine", link: "routine-teachers.html" },
                ],
            },
            { title: "Users", icon: "ti ti-users-minus", link: "users.html" },
            { title: "Roles & Permissions", icon: "ti ti-shield-plus", link: "roles-permission.html" },
            { title: "DeleteAccount Request", icon: "ti ti-user-question", link: "delete-account.html" },
        ],
    },
    {
        header: "Academic",
        items: [
            {
                title: "Classes",
                icon: "ti ti-school-bell",
                submenu: [
                    { title: "All Classes", link: "classes.html" },
                    { title: "Schedule", link: "schedule-classes.html" },
                ],
            },
            { title: "Class Room", icon: "ti ti-building", link: "class-room.html" },
            { title: "ClassRoutine", icon: "ti ti-bell-school", link: "class-routine.html" },
            { title: "Section", icon: "ti ti-square-rotated-forbid-2", link: "class-section.html" },
            { title: "Subject", icon: "ti ti-book", link: "class-subject.html" },
            { title: "Syllabus", icon: "ti ti-book-upload", link: "class-syllabus.html" },
            { title: "TimeTable", icon: "ti ti-table", link: "class-time-table.html" },
            { title: "HomeWork", icon: "ti ti-license", link: "class-home-work.html" },
            {
                title: "Examinations",
                icon: "ti ti-hexagonal-prism-plus",
                submenu: [
                    { title: "Exam", link: "exam.html" },
                    { title: "Exam Schedule", link: "exam-schedule.html" },
                    { title: "Grade", link: "grade.html" },
                    { title: "Exam Attendance", link: "exam-attendance.html" },
                    { title: "Exam Results", link: "exam-results.html" },
                ],
            },
            { title: "Reasons", icon: "ti ti-lifebuoy", link: "academic-reasons.html" },
        ],
    },
    {
        header: "Management",
        items: [
            {
                title: "FeesCollection",
                icon: "ti ti-report-money",
                submenu: [
                    { title: "Fees Group", link: "fees-group.html" },
                    { title: "Fees Type", link: "fees-type.html" },
                    { title: "Fees Master", link: "fees-master.html" },
                    { title: "Fees Assign", link: "fees-assign.html" },
                    { title: "Collect Fees", link: "collect-fees.html" },
                ],
            },
            {
                title: "Library",
                icon: "ti ti-notebook",
                submenu: [
                    { title: "Library Members", link: "library-members.html" },
                    { title: "Books", link: "library-books.html" },
                    { title: "Issue Book", link: "library-issue-book.html" },
                    { title: "Return", link: "library-return.html" },
                ],
            },
            {
                title: "Sports & Athletes",
                icon: "ti ti-play-football",
                submenu: [
                    { title: "Sports List", link: "sports.html" },
                    { title: "Players", link: "players.html" },
                ],
            },
            { title: "Hostel", icon: "ti ti-building-cottage", link: "hostel-list.html" },
            { title: "Transport", icon: "ti ti-bus", link: "transport.html" },
        ],
    },
    {
        header: "HRM",
        items: [
            { title: "Staffs", icon: "ti ti-users-group", link: "staffs.html" },
            { title: "Departments", icon: "ti ti-layout-distribute-horizontal", link: "departments.html" },
            { title: "Designation", icon: "ti ti-user-cog", link: "designation.html" },
            {
                title: "Attendance",
                icon: "ti ti-calendar-share",
                submenu: [
                    { title: "Student Attendance", link: "student-attendance.html" },
                    { title: "Teacher Attendance", link: "teacher-attendance.html" },
                    { title: "Staff Attendance", link: "staff-attendance.html" },
                ],
            },
            {
                title: "Leaves",
                icon: "ti ti-calendar-stats",
                submenu: [
                    { title: "List of leaves", link: "list-leaves.html" },
                    { title: "Approve Leave", link: "approve-leave.html" },
                ],
            },
            { title: "Holidays", icon: "ti ti-calendar-event", link: "holidays.html" },
            { title: "Payroll", icon: "ti ti-coin", link: "payroll.html" },
        ],
    },
    {
        header: "Accounts",
        items: [
            { title: "Accounts Overview", icon: "ti ti-chart-bar", link: "accounts-overview.html" },
            {
                title: "Expenses",
                icon: "ti ti-moneybag",
                submenu: [
                    { title: "Expenses", link: "expenses.html" },
                    { title: "Expense Category", link: "expense-category.html" },
                ],
            },
            { title: "Income", icon: "ti ti-circle-plus", link: "income.html" },
            { title: "Invoices", icon: "ti ti-file-invoice", link: "invoices.html" },
            { title: "Invoice View", icon: "ti ti-file-info", link: "invoice-view.html" },
            { title: "Transactions", icon: "ti ti-transfer", link: "transactions.html" },
        ],
    },
    {
        header: "Reports",
        items: [
            { title: "Attendance Report", icon: "ti ti-file-analytics", link: "attendance-report.html" },
            { title: "Class Report", icon: "ti ti-report-analytics", link: "class-report.html" },
            { title: "Student Report", icon: "ti ti-report", link: "student-report.html" },
            { title: "Grade Report", icon: "ti ti-file-certificate", link: "grade-report.html" },
            { title: "Leave Report", icon: "ti ti-file-time", link: "leave-report.html" },
            { title: "Fees Report", icon: "ti ti-report-money", link: "fees-report.html" },
        ],
    },
    {
        header: "User Management Pages",
        items: [
            {
                title: "Profile",
                icon: "ti ti-user-circle",
                submenu: [
                    { title: "Student Profile", link: "student-profile.html" },
                    { title: "Teacher Profile", link: "teacher-profile.html" },
                    { title: "Parent Profile", link: "parent-profile.html" },
                ],
            },
        ],
    },
    {
        header: "Pages",
        items: [
            {
                title: "Authentication",
                icon: "ti ti-lock",
                submenu: [
                    { title: "Login", link: "login.html" },
                    { title: "Register", link: "register.html" },
                    { title: "Forgot Password", link: "forgot-password.html" },
                    { title: "Reset Password", link: "reset-password.html" },
                ],
            },
            { title: "Blank Page", icon: "ti ti-file", link: "blank-page.html" },
            { title: "Coming Soon", icon: "ti ti-file-time", link: "coming-soon.html" },
            { title: "Under Maintenance", icon: "ti ti-file-broken", link: "under-maintenance.html" },
            { title: "Error Pages", icon: "ti ti-file-alert", link: "error-404.html" },
        ],
    },
    {
        header: " annui",
        items: [
            {
                title: "Announcements",
                icon: "ti ti-speakerphone",
                link: "announcements.html",
            },
            {
                title: "Events",
                icon: "ti ti-calendar-check",
                link: "events.html",
            },
        ],
    },
    {
        header: "Settings",
                items: [
            { title: "General Settings", icon: "ti ti-settings", link: "general-settings.html" },
            { title: "School Settings", icon: "ti ti-building-community", link: "school-settings.html" },
            { title: "Payment Settings", icon: "ti ti-credit-card", link: "payment-settings.html" },
            { title: "Academic Settings", icon: "ti ti-school", link: "academic-settings.html" },
        ],
    },
    {
        header: "Support",
        items: [
            {
                title: "Tickets",
                icon: "ti ti-ticket",
                submenu: [
                    { title: "Ticket List", link: "tickets.html" },
                    { title: "Ticket Details", link: "ticket-details.html" },
                ],
            },
            {
                title: "Contact Messages",
                icon: "ti ti-messages",
                link: "contact-messages.html",
            },
        ],
    },
    {
        header: "UI Interface",
        items: [
            {
                title: "Base UI",
                icon: "ti ti-vector-bezier",
                submenu: [
                    { title: "Alerts", link: "ui-alerts.html" },
                    { title: "Apexcharts", link: "ui-apexcharts.html" },
                    { title: "Avatar", link: "ui-avatar.html" },
                    { title: "Badges", link: "ui-badges.html" },
                    { title: "Buttons", link: "ui-buttons.html" },
                    { title: "Buttons Group", link: "ui-buttons-group.html" },
                    { title: "Breadcrumb", link: "ui-breadcrumb.html" },
                    { title: "Cards", link: "ui-cards.html" },
                    { title: "Carousel", link: "ui-carousel.html" },
                    { title: "Dropdowns", link: "ui-dropdowns.html" },
                    { title: "Grid", link: "ui-grid.html" },
                    { title: "Images", link: "ui-images.html" },
                    { title: "Lightbox", link: "ui-lightbox.html" },
                    { title: "Media", link: "ui-media.html" },
                    { title: "Modals", link: "ui-modals.html" },
                    { title: "Offcanvas", link: "ui-offcanvas.html" },
                    { title: "Pagination", link: "ui-pagination.html" },
                    { title: "Progress", link: "ui-progress.html" },
                    { title: "Placeholders", link: "ui-placeholders.html" },
                    { title: "Range Slider", link: "ui-range-slider.html" },
                    { title: "Spinner", link: "ui-spinner.html" },
                    { title: "Tabs", link: "ui-tabs.html" },
                    { title: "Toasts", link: "ui-toasts.html" },
                    { title: "Tooltip", link: "ui-tooltip.html" },
                    { title: "Typography", link: "ui-typography.html" },
                    { title: "Video", link: "ui-video.html" },
                ],
            },
            {
                title: "Advanced UI",
                icon: "ti ti-hierarchy-3",
                submenu: [
                    { title: "Ribbon", link: "ui-ribbon.html" },
                    { title: "Clipboard", link: "ui-clipboard.html" },
                    { title: "Drag & Drop", link: "ui-drag-drop.html" },
                    { title: "Range Slider", link: "ui-rangeslider.html" },
                    { title: "Rating", link: "ui-rating.html" },
                    { title: "Text Editor", link: "ui-text-editor.html" },
                    { title: "Counter", link: "ui-counter.html" },
                    { title: "Scrollbar", link: "ui-scrollbar.html" },
                    { title: "Sticky Note", link: "ui-sticky-note.html" },
                    { title: "Timeline", link: "ui-timeline.html" },
                ],
            },
            {
                title: "Charts",
                icon: "ti ti-chart-pie",
                submenu: [
                    { title: "Apex Charts", link: "chart-apex.html" },
                    { title: "Chart C3", link: "chart-c3.html" },
                    { title: "Chart Js", link: "chart-js.html" },
                    { title: "Chart Morris", link: "chart-morris.html" },
                    { title: "Chart Flot", link: "chart-flot.html" },
                ],
            },
            {
                title: "Icons",
                icon: "ti ti-icons",
                submenu: [
                    { title: "Fontawesome Icons", link: "icon-fontawesome.html" },
                    { title: "Feather Icons", link: "icon-feather.html" },
                    { title: "Ionic Icons", link: "icon-ionic.html" },
                    { title: "Material Icons", link: "icon-material.html" },
                    { title: "PE7 Icons", link: "icon-pe7.html" },
                    { title: "Simpleline Icons", link: "icon-simpleline.html" },
                    { title: "Themify Icons", link: "icon-themify.html" },
                    { title: "Typicon Icons", link: "icon-typicon.html" },
                    { title: "Weather Icons", link: "icon-weather.html" },
                ],
            },
            {
                title: "Forms",
                icon: "ti ti-input-search",
                submenu: [
                    { title: "Basic Inputs", link: "form-basic-inputs.html" },
                    { title: "Input Groups", link: "form-input-groups.html" },
                    { title: "Horizontal Form", link: "form-horizontal.html" },
                    { title: "Vertical Form", link: "form-vertical.html" },
                    { title: "Mask Inputs", link: "form-mask.html" },
                    { title: "File Upload", link: "form-fileupload.html" },
                    { title: "Form Select", link: "form-select.html" },
                    { title: "Form Wizard", link: "form-wizard.html" },
                    { title: "Form Validation", link: "form-validation.html" },
                ],
            },
            {
                title: "Tables",
                icon: "ti ti-table-plus",
                submenu: [
                    { title: "Basic Tables", link: "tables-basic.html" },
                    { title: "Data Table", link: "data-tables.html" },
                ],
            },
        ],
    },
    {
        header: "Help",
        items: [
            { title: "Documentation", icon: "ti ti-file-text", link: route("docent.docs.home") },
            { title: "Changelog", icon: "ti ti-exchange", link: "https://preschool.dreamstechnologies.com/documentation/changelog.html", badge: "v1.8.3" },
        ],
    },
]);

export const sidebarCollapsed = ref(false);

export const quicklinksItems = [
    [
        {
            url: "class-time-table.html",
            sevierity: "bg-green-200/50",
            icon: "ti ti-calendar",
            borderClass: "border-green-500",
            bgClass: "bg-green-500",
            label: "Calendar"
        },
        {
            url: "student-attendance.html",
            sevierity: "bg-blue-200/50",
            icon: "ti ti-calendar-share",
            borderClass: "border-blue-500",
            bgClass: "bg-blue-500",
            label: "Attendance"
        },
    ],
    [
        {
            url: "student-grid.html",
            sevierity: "bg-red-200/50",
            icon: "ti ti-school",
            borderClass: "border-red-500",
            bgClass: "bg-red-500",
            label: "Students"
        },
        {
            url: "exam.html",
            sevierity: "bg-yellow-200/50",
            icon: "ti ti-hexagonal-prism-plus",
            borderClass: "border-yellow-500",
            bgClass: "bg-yellow-500",
            label: "Exam"
        },
    ],
];
