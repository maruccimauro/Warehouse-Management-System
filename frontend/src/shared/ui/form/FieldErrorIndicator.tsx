import { Icon } from '../icons';

interface FieldErrorIndicatorProps {
  // id del texto del error, para aria-describedby del control.
  id: string;
  error: string | undefined;
}

// Error de un campo como ícono en un espacio reservado junto al control: aparecer o
// desaparecer no mueve el resto de la pantalla. El texto se ve en un globo al pasar el puntero
// por el ícono o con el foco en el campo (CSS en index.css, .field-error); el lector de
// pantalla lo lee siempre por aria-describedby, que incluye el texto aunque esté oculto. Al
// grabar, la barra de estado muestra además el primer error.
export function FieldErrorIndicator({ id, error }: FieldErrorIndicatorProps) {
  return (
    <span className="field-error">
      {error !== undefined && (
        <>
          <Icon name="error" />
          <span id={id} className="field-error-tip">
            {error}
          </span>
        </>
      )}
    </span>
  );
}
