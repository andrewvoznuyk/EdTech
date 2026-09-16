export const RouteNames = {
  HERO: 'hero',
  LOGIN: 'login',
  REGISTER: 'register',
} as const;

export type RouteName = (typeof RouteNames)[keyof typeof RouteNames];

export const RoutePaths = {
  HERO: '/',
  LOGIN: '/login',
  REGISTER: '/register',
} as const;

export type RoutePath = (typeof RoutePaths)[keyof typeof RoutePaths];
