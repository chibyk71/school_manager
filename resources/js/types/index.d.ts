export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};

export type Statistic = {
    stat: number,
    percentage: number,
    title: string,
    active: number,
    inactive: number
}
