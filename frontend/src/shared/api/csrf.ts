const xsrfCookieName = 'XSRF-TOKEN';

// Laravel envía el token en la cookie XSRF-TOKEN codificado para URL y espera el valor
// decodificado en la cabecera X-XSRF-TOKEN. Se lee en cada petición porque el login
// regenera la sesión y el token.
export function readXsrfToken(cookies: string): string | undefined {
  for (const cookie of cookies.split(';')) {
    const separator = cookie.indexOf('=');

    if (separator > 0 && cookie.slice(0, separator).trim() === xsrfCookieName) {
      return decodeURIComponent(cookie.slice(separator + 1).trim());
    }
  }

  return undefined;
}
