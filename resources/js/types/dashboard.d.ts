// resources/js/types/dashboard.d.ts
/** Generic widget props */
export interface WidgetProps {
    /** Title shown on the card */
    title: string;
    /** Optional icon name (PrimeVue) */
    icon?: string;
}

/** API response shape for all widgets */
export interface WidgetResponse<T = any> {
    data: T;
    meta?: {
        refreshed_at: string;
    };
}

export interface WidgetDefinition {
  slug: string;                     // unique key, e.g. "enrollment-stats"
  description: string;
  required_permissions: string[];
  dashboards: string[];             // e.g. ["admin", "academic"]
  component?: any;                  // optional pre-loaded component
  props?: Record<string, any>;      // props to pass to the component
}

// types/dashboard.ts

export interface DashboardCard {
  value: string | number;
  growth?: number;
  title: string;
  image?: string;
  icon?: string;
  color?: string;
  bg?: string;
  severity?: string;
  progress?: number;
}

export interface DashboardChartData {
  labels: string[];
  data: number[];
  growth?: number;
}

export interface DashboardCharts {
  staff_dept: {
    labels: string[];
    data: number[];
  };
  enrollment: DashboardChartData;
  student_att: DashboardChartData;
  staff_att: DashboardChartData;
}

export interface RecentLog {
  id: number;
  description: string;
  icon: string;
  time: string;
}

export interface DashboardData {
  cards: DashboardCard[];
  charts: DashboardCharts;
  recentLogs: RecentLog[];
}