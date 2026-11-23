import type { ButtonHTMLAttributes } from 'react';

type Variant = 'primary' | 'default' | 'ghost';

const base =
  'inline-flex min-h-7 min-w-7 items-center justify-center gap-1.5 rounded-sm px-3 font-medium ' +
  'disabled:cursor-not-allowed disabled:opacity-60 pointer-coarse:min-h-11';

const variants: Record<Variant, string> = {
  primary: 'bg-accent text-white hover:bg-accent-strong',
  default: 'border border-border-strong bg-surface text-ink hover:bg-hover',
  ghost: 'text-accent hover:bg-hover',
};

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
}

export function Button({ variant = 'default', className = '', type = 'button', ...props }: ButtonProps) {
  // type por defecto "button": un botón secundario dentro de un formulario no lo envía.
  return <button type={type} className={`${base} ${variants[variant]} ${className}`} {...props} />;
}
