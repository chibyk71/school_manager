import { useDialog } from "primevue";
import { ref } from "vue";
import { FilterMatchMode } from '@primevue/core/api';

export let isDarkTheme = ref(false);

export const menuItems = ref([
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
            { title: "Sports", icon: "ti ti-run", link: "sports.html" },
            { title: "Players", icon: "ti ti-play-football", link: "players.html" },
            {
                title: "Hostel",
                icon: "ti ti-building-fortress",
                submenu: [
                    { title: "Hostel List", link: "hostel-list.html" },
                    { title: "Hostel Rooms", link: "hostel-rooms.html" },
                    { title: "Room Type", link: "hostel-room-type.html" },
                ],
            },
            {
                title: "Transport",
                icon: "ti ti-bus",
                submenu: [
                    { title: "Routes", link: "transport-routes.html" },
                    { title: "Pickup Points", link: "transport-pickup-points.html" },
                    { title: "Vehicle Drivers", link: "transport-vehicle-drivers.html" },
                    { title: "Vehicle", link: "transport-vehicle.html" },
                    { title: "Assign Vehicle", link: "transport-assign-vehicle.html" },
                ],
            },
        ],
    },
    {
        header: "HRM",
        items: [
            { title: "Staffs", icon: "ti ti-users-group", link: "staffs.html" },
            { title: "Departments", icon: "ti ti-layout-distribute-horizontal", link: "departments.html" },
            { title: "Designation", icon: "ti ti-user-exclamation", link: "designation.html" },
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
                    { title: "Approve Request", link: "approve-request.html" },
                ],
            },
            { title: "Holidays", icon: "ti ti-briefcase", link: "holidays.html" },
            { title: "Payroll", icon: "ti ti-moneybag", link: "payroll.html" },
        ],
    },
    {
        header: "Finance & Accounts",
        items: [
            {
                title: "Accounts",
                icon: "ti ti-swipe",
                submenu: [
                    { title: "Expenses", link: "expenses.html" },
                    { title: "Expense Category", link: "expenses-category.html" },
                    { title: "Income", link: "accounts-income.html" },
                    { title: "Invoices", link: "accounts-invoices.html" },
                    { title: "Invoice View", link: "invoice.html" },
                    { title: "Transactions", link: "accounts-transactions.html" },
                ],
            },
        ],
    },
    {
        header: "Announcements",
        items: [
            { title: "NoticeBoard", icon: "ti ti-clipboard-data", link: "notice-board.html" },
            { title: "Events", icon: "ti ti-calendar-question", link: "events.html" },
        ],
    },
    {
        header: "Reports",
        items: [
            { title: "AttendanceReport", icon: "ti ti-calendar-due", link: "attendance-report.html" },
            { title: "Class Report", icon: "ti ti-graph", link: "class-report.html" },
            { title: "StudentReport", icon: "ti ti-chart-infographic", link: "student-report.html" },
            { title: "GradeReport", icon: "ti ti-calendar-x", link: "grade-report.html" },
            { title: "Leave Report", icon: "ti ti-line", link: "leave-report.html" },
            { title: "Fees Report", icon: "ti ti-mask", link: "fees-report.html" },
        ],
    },
    {
        header: "Membership",
        items: [
            { title: "MembershipPlans", icon: "ti ti-user-plus", link: "membership-plans.html" },
            { title: "MembershipAddons", icon: "ti ti-cone-plus", link: "membership-addons.html" },
            { title: "Transactions", icon: "ti ti-file-power", link: "membership-transactions.html" },
        ],
    },
    {
        header: "Content",
        items: [
            { title: "Pages", icon: "ti ti-page-break", link: "pages.html" },
            {
                title: "Blog",
                icon: "ti ti-brand-blogger",
                submenu: [
                    { title: "All Blogs", link: "blog.html" },
                    { title: "Categories", link: "blog-categories.html" },
                    { title: "Comments", link: "blog-comments.html" },
                    { title: "Tags", link: "blog-tags.html" },
                ],
            },
            {
                title: "Location",
                icon: "ti ti-map-pin-search",
                submenu: [
                    { title: "Countries", link: "countries.html" },
                    { title: "States", link: "states.html" },
                    { title: "Cities", link: "cities.html" },
                ],
            },
            { title: "Testimonials", icon: "ti ti-quote", link: "testimonials.html" },
            { title: "FAQ", icon: "ti ti-question-mark", link: "faq.html" },
        ],
    },
    {
        header: "Support",
        items: [
            { title: "ContactMessages", icon: "ti ti-message", link: "contact-messages.html" },
            { title: "Tickets", icon: "ti ti-ticket", link: "tickets.html" },
        ],
    },
    {
        header: "Pages",
        items: [
            { title: "Profile", icon: "ti ti-user", link: "/profile" },
            { title: "BlankPage", icon: "ti ti-brand-nuxt", link: "blank-page.html" },
            { title: "Coming Soon", icon: "ti ti-file", link: "coming-soon.html" },
            { title: "UnderMaintenance", icon: "ti ti-moon-2", link: "under-maintenance.html" },
        ],
    },
    {
        header: "Settings",
        items: [
            {
                title: "Website Settings",
                icon: "ti ti-device-laptop",
                submenu: [
                    { title: "Company Settings", link: "company-settings.html" },
                    { title: "Localization", link: route('website.localization') },
                ],
            },
            // {
            //     title: "App Settings",
            //     icon: "ti ti-apps",
            //     submenu: [
            //         { title: "Invoice Settings", link: "invoice-settings.html" },
            //         { title: "Custom Fields", link: "custom-fields.html" },
            //     ],
            // },
            {
                title: "System Settings",
                icon: "ti ti-file-symlink",
                submenu: [
                    { title: "Email Settings", link: route('system.email') },
                    { title: "Email Templates", link: route("system.email.template") },
                    { title: "SMS Settings", link: route("system.sms") },
                    { title: "OTP", link: route("system.otp") },
                    { title: "GDPR Cookies", link: route('system.gdpr') },
                    {title: "Custom Fields", link: route('website.custom_field')},
                ],
            },
            {
                title: "Financial Settings",
                icon: "ti ti-zoom-money",
                submenu: [
                    { title: "Payment Gateways", link: route("settings.payment-gate-ways") },
                    { title: "Tax Rates", link: route("settings.tax") },
                    {title: "Fees Settings", link: route('settings.fees')},
                    {title: "Invoice Setting", link: route("website.invoice")},
                ],
            },
            {
                title: "Other Settings",
                icon: "ti ti-flag-cog",
                submenu: [
                    { title: "Storage", link: route('settings.storage') },
                    {title: "Maintainance", link: "/settings/others/maintainance"},
                    { title: "Ban IP Address", link: "ban-ip-address.html" },
                ],
            },
        ],
    },
    {
        header: "UI Interface",
        items: [
            {
                title: "Base UI",
                icon: "ti ti-hierarchy-2",
                submenu: [
                    { title: "Alerts", link: "ui-alerts.html" },
                    { title: "Accordion", link: "ui-accordion.html" },
                    { title: "Avatar", link: "ui-avatar.html" },
                    { title: "Badges", link: "ui-badges.html" },
                    { title: "Border", link: "ui-borders.html" },
                    { title: "Buttons", link: "ui-buttons.html" },
                    { title: "Button Group", link: "ui-buttons-group.html" },
                    { title: "Breadcrumb", link: "ui-breadcrumb.html" },
                    { title: "Card", link: "ui-cards.html" },
                    { title: "Carousel", link: "ui-carousel.html" },
                    { title: "Colors", link: "ui-colors.html" },
                    { title: "Dropdowns", link: "ui-dropdowns.html" },
                    { title: "Grid", link: "ui-grid.html" },
                    { title: "Images", link: "ui-images.html" },
                    { title: "Lightbox", link: "ui-lightbox.html" },
                    { title: "Media", link: "ui-media.html" },
                    { title: "Modals", link: "ui-modals.html" },
                    { title: "Offcanvas", link: "ui-offcanvas.html" },
                    { title: "Pagination", link: "ui-pagination.html" },
                    { title: "Popovers", link: "ui-popovers.html" },
                    { title: "Progress", link: "ui-progress.html" },
                    { title: "Placeholders", link: "ui-placeholders.html" },
                    { title: "Spinner", link: "ui-spinner.html" },
                    { title: "Sweet Alerts", link: "ui-sweetalerts.html" },
                    { title: "Tabs", link: "ui-nav-tabs.html" },
                    { title: "Toasts", link: "ui-toasts.html" },
                    { title: "Tooltips", link: "ui-tooltips.html" },
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
                    { title: "Sticky Note", link: "ui-stickynote.html" },
                    { title: "Timeline", link: "ui-timeline.html" },
                ],
            },
            {
                title: "Charts",
                icon: "ti ti-chart-line",
                submenu: [
                    { title: "Apex Charts", link: "chart-apex.html" },
                    { title: "Chart C3", link: "chart-c3.html" },
                    { title: "Chart Js", link: "chart-js.html" },
                    { title: "Morris Charts", link: "chart-morris.html" },
                    { title: "Flot Charts", link: "chart-flot.html" },
                    { title: "Peity Charts", link: "chart-peity.html" },
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
                    { title: "Pe7 Icons", link: "icon-pe7.html" },
                    { title: "Simpleline Icons", link: "icon-simpleline.html" },
                    { title: "Themify Icons", link: "icon-themify.html" },
                    { title: "Weather Icons", link: "icon-weather.html" },
                    { title: "Typicon Icons", link: "icon-typicon.html" },
                    { title: "Flag Icons", link: "icon-flag.html" },
                ],
            },
            {
                title: "Forms",
                icon: "ti ti-input-search",
                submenu: [
                    {
                        title: "Form Elements",
                        submenu: [
                            { title: "Basic Inputs", link: "form-basic-inputs.html" },
                            { title: "Checkbox & Radios", link: "form-checkbox-radios.html" },
                            { title: "Input Groups", link: "form-input-groups.html" },
                            { title: "Grid & Gutters", link: "form-grid-gutters.html" },
                            { title: "Form Select", link: "form-select.html" },
                            { title: "Input Masks", link: "form-mask.html" },
                            { title: "File Uploads", link: "form-fileupload.html" },
                        ],
                    },
                    {
                        title: "Layouts",
                        submenu: [
                            { title: "Horizontal Form", link: "form-horizontal.html" },
                            { title: "Vertical Form", link: "form-vertical.html" },
                            { title: "Floating Labels", link: "form-floating-labels.html" },
                        ],
                    },
                    { title: "Form Validation", link: "form-validation.html" },
                    { title: "Select2", link: "form-select2.html" },
                    { title: "Form Wizard", link: "form-wizard.html" },
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
            { title: "Documentation", icon: "ti ti-file-text", link: "https://preschool.dreamstechnologies.com/documentation/index.html" },
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
            url: "fees-group.html",
            sevierity: "bg-surface-200/50",
            icon: "ti ti-license",
            borderClass: "border-surface-500",
            bgClass: "bg-surface-500",
            label: "Fees"
        }
    ],
    [
        {
            url: "exam-results.html",
            sevierity: "bg-primary/50",
            icon: "ti ti-hexagonal-prism",
            borderClass: "border-primary",
            bgClass: "bg-primary",
            label: "Exam Result"
        },
        {
            url: "class-home-work.html",
            sevierity: "bg-red-200/50",
            icon: "ti ti-report-money",
            borderClass: "border-red-500",
            bgClass: "bg-red-500",
            label: "Home Works"
        }
    ],
    [
        {
            url: "student-attendance.html",
            sevierity: "bg-yellow-200/50",
            icon: "ti ti-calendar-share",
            borderClass: "border-yellow-500",
            bgClass: "bg-yellow-500",
            label: "Attendance"
        },
        {
            url: "attendance-report.html",
            sevierity: "bg-blue-200/50",
            icon: "ti ti-file-pencil",
            borderClass: "border-blue-500",
            bgClass: "bg-blue-500",
            label: "Reports"
        }
    ]
];

export const filterByTimeOptions = ref([
    { label: "Today", value: "today" },
    { label: "This Week", value: "thisWeek" },
    { label: "This Month", value: "thisMonth" },
    { label: "This Year", value: "thisYear" },
]);

export const StudentQuickLinks: {
    label: string,
    url: string,
    border_color: string,
    icon: string,
}[] = [
        {
            label: "Pay Fees",
            url: "/student-fees",
            border_color: "border-primary",
            icon: "ti ti-report-money"
        },
        {
            label: "Exam Result",
            url: "student-result.html",
            border_color: "border-green-500",
            icon: "ti ti-hexagonal-prism-plus"
        },
        {
            label: "Calendar",
            url: "student-time-table.html",
            border_color: "border-yellow-500",
            icon: "ti ti-calendar"
        },
        {
            label: "Attendance",
            url: "student-leaves.html",
            border_color: "border-dark",
            icon: "ti ti-calendar-share"
        }
    ];

export const ListOfAcademicYears = (numberofYearsBack: number = 5) => {
    let cYear = new Date();

    const currentYear = cYear.getFullYear();

    let XYearsAgo = currentYear - numberofYearsBack;

    return Array.from(Array(numberofYearsBack).keys()).map((x) => `${XYearsAgo + x}/${XYearsAgo + x + 1}`);
}

export const StudentMenu = ref([
    { label: 'Collect Fees', icon: 'ti ti-money-alt' },
    { label: 'View Student', icon: 'ti ti-menu' },
    { label: 'Edit', icon: 'ti ti-edit-circle' },
    { label: 'Login Details', icon: 'ti ti-lock' },
    { label: 'Disable', icon: 'ti ti-toggle-right' },
    { label: 'Promote Student', icon: 'ti ti-arrow-ramp-right-2' },
    { label: 'Delete', icon: 'ti ti-trash-x' }
])

export const FilterModes = FilterMatchMode
