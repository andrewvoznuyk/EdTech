export interface JWTPayload {
  id?: string;
  username?: string;
  email?: string;
  roles?: string[];
  locale?: string;
  exp?: number;
  iat?: number;
  [key: string]: unknown;
}

export interface AuthUser {
  id: string;
  email: string;
  roles: string[];
  locale?: string;
  exp?: number;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface RegisterPayload {
  email: string;
  password: string;
  userProfile?: {
    firstName?: string;
    lastName?: string;
  };
}

export interface RegisterResponse {
  id: string;
  email: string;
  isVerified?: boolean;
  [key: string]: unknown;
}

export interface LoginSuccessResponse {
  token: string;
}

export interface TwoFactorChallengeResponse {
  require_2fa: true;
  method: string;
  available_methods: string[];
  email: string;
  message?: string;
}

export interface TwoFactorVerifyPayload {
  email: string;
  code: string;
  method?: string;
}

export type AuthResponse = LoginSuccessResponse | TwoFactorChallengeResponse;

export interface RefreshTokenResponse {
  token: string;
}

export interface ForgotPasswordPayload {
  email: string;
}

export interface ResetPasswordPayload {
  resetToken: string;
  password: string;
}

export function isTwoFactorChallenge(
  response: unknown
): response is TwoFactorChallengeResponse {
  return (
    typeof response === 'object' &&
    response !== null &&
    'require_2fa' in response &&
    (response as Record<string, unknown>).require_2fa === true
  );
}
