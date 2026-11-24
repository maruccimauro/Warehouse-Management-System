import { useId, type ReactNode } from 'react';

interface FormSectionProps {
  title: ReactNode;
  // Contenido que no son renglones de campo (una tabla de parámetros, por ejemplo) va sin el
  // espaciado de la grilla.
  plain?: boolean | undefined;
  children: ReactNode;
}

// Bloque con título de un formulario, como un marco de grupo del Screen Painter de SAP.
export function FormSection({ title, plain = false, children }: FormSectionProps) {
  const titleId = useId();

  return (
    <section className="rounded-sm border border-border bg-surface" aria-labelledby={titleId}>
      <h2 id={titleId} className="border-b border-border bg-surface-alt px-3 py-1.5 text-sm font-semibold">
        {title}
      </h2>
      {plain ? children : <div className="grid gap-2 px-3 py-2">{children}</div>}
    </section>
  );
}
