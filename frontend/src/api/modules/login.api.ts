import { apiClient, rawClient } from '../client';
import { ApiEndpoints } from '@/constants/api';
import type {
  AuthResponse,
  ForgotPasswordPayload,
  LoginCredentials,
  LoginSuccessResponse,
  RefreshTokenResponse,
  RegisterPayload,
  RegisterResponse,
  ResetPasswordPayload,
  TwoFactorVerifyPayload,
} from '@/types/auth';

export const authApi = {
  login(credentials: LoginCredentials) {
    return rawClient.post<AuthResponse>(ApiEndpoints.LOGIN_CHECK, credentials);
  },

  register(payload: RegisterPayload) {
    return rawClient.post<RegisterResponse>(ApiEndpoints.REGISTER, payload);
  },

  verify2FA(payload: TwoFactorVerifyPayload) {
    return rawClient.post<LoginSuccessResponse>(ApiEndpoints.TWO_FACTOR_CHECK, payload);
  },

  refresh() {
    return rawClient.post<RefreshTokenResponse>(ApiEndpoints.TOKEN_REFRESH);
  },

  logout() {
    return rawClient.post<void>(ApiEndpoints.TOKEN_LOGOUT);
  },

  googleLogin(idToken: string) {
    return rawClient.post<LoginSuccessResponse>(ApiEndpoints.GOOGLE_AUTH, { token: idToken });
  },

  forgotPassword(payload: ForgotPasswordPayload) {
    return apiClient.post<void>(ApiEndpoints.FORGOT_PASSWORD, payload);
  },

  resetPassword(payload: ResetPasswordPayload) {
    return apiClient.post<{ message: string }>(ApiEndpoints.RESET_PASSWORD, payload);
  },

  resend2FA(email: string) {
    return apiClient.post<void>(ApiEndpoints.RESEND_2FA, { email });
  },

  updateUserLocale(userId: string, locale: string) {
    return apiClient.patch(
      `${ApiEndpoints.USERS}/${userId}`,
      { locale },
      { headers: { 'Content-Type': 'application/merge-patch+json' } }
    );
  },
};