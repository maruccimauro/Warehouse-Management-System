import type { TFunction } from 'i18next';
import { z } from 'zod';
import { credentialMaxLength } from './loginSchema';

// Mismo mínimo que Password::defaults() del backend (IdentityServiceProvider). El backend
// sigue siendo quien valida: exige además la contraseña actual correcta.
export const passwordMinLength = 12;

export function createPasswordChangeSchema(t: TFunction<'auth'>) {
  return z
    .object({
      // Las contraseñas no se recortan: los espacios forman parte de ellas.
      current_password: z.string().min(1, t('passwordChange.validation.currentRequired')),
      password: z
        .string()
        .min(passwordMinLength, t('passwordChange.validation.tooShort', { min: passwordMinLength }))
        .max(credentialMaxLength, t('validation.passwordTooLong', { max: credentialMaxLength })),
      password_confirmation: z.string().min(1, t('passwordChange.validation.confirmationRequired')),
    })
    .refine((values) => values.password_confirmation === values.password, {
      path: ['password_confirmation'],
      message: t('passwordChange.validation.mismatch'),
    })
    .refine((values) => values.password !== values.current_password, {
      path: ['password'],
      message: t('passwordChange.validation.sameAsCurrent'),
    });
}

export type PasswordChangeValues = z.input<ReturnType<typeof createPasswordChangeSchema>>;
