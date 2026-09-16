export const ApiEndpoints = {
  LOGIN_CHECK: '/login-check',
  REGISTER: '/users/register',
  TOKEN_REFRESH: '/token/refresh',
  TOKEN_LOGOUT: '/token/refresh/logout',
  TWO_FACTOR_CHECK: '/2fa/check',
  GOOGLE_AUTH: '/auth/google',
  FORGOT_PASSWORD: '/users/forgot-password',
  RESET_PASSWORD: '/users/reset-password',
  RESEND_2FA: '/users/resend-2fa',
  USERS: '/users',
} as const;

export type ApiEndpoint = (typeof ApiEndpoints)[keyof typeof ApiEndpoints];
