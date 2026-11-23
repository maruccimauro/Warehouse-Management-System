import { createContext } from 'react';
import type { AuthenticatedUser } from './authApi';

// Lo provee RequireAuth con el usuario ya verificado. Los componentes protegidos leen de
// acá y no de la consulta de sesión: cuando la sesión pasa a null (logout, 401) pueden
// re-renderizarse antes de que la guarda los desmonte, y el contexto conserva el usuario
// hasta ese momento.
export const AuthenticatedUserContext = createContext<AuthenticatedUser | undefined>(undefined);
