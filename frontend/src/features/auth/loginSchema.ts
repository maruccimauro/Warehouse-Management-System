import type { TFunction } from 'i18next';
import { z } from 'zod';

// Mismos límites que LoginRequest del backend: la validación del cliente evita viajes
// innecesarios, la del servidor sigue siendo la que protege.
export const credentialMaxLength = 255;

export function createLoginSchema(t: TFunction<'auth'>) {
  return z.object({
    email: z
      .string()
      .trim()
      .min(1, t('validation.emailRequired'))
      .max(credentialMaxLength, t('validation.emailTooLong', { max: credentialMaxLength }))
      .pipe(z.email(t('validation.emailInvalid'))),
    // La contraseña no se recorta: los espacios forman parte de ella.
    password: z
      .string()
      .min(1, t('validation.passwordRequired'))
      .max(credentialMaxLength, t('validation.passwordTooLong', { max: credentialMaxLength })),
  });
}

export type LoginFormValues = z.input<ReturnType<typeof createLoginSchema>>;
