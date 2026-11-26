import type { GridMessage } from '../grid/gridTypes';
import type { SelectionField } from './selectionTypes';

interface SelectionInputComponentProps {
  field: SelectionField;
  id: string;
  value: string;
  onChange: (value: string, valid: boolean, problem?: GridMessage) => void;
  onMessage: (message: GridMessage) => void;
  // false en los límites de un SELECT-OPTIONS: sin tabla de verificación (D4 de E2.18).
  check: boolean;
  required?: boolean;
  disabled?: boolean;
  'aria-labelledby'?: string | undefined;
  'aria-describedby'?: string | undefined;
}

// Entrada de un valor de la pantalla de selección: el campo con F4 que declara la pantalla
// (`as`) o un <input> propio. Los códigos no llevan corrección ni mayúscula automática.
export function SelectionInput({
  field,
  id,
  value,
  onChange,
  onMessage,
  check,
  required = false,
  disabled = false,
  'aria-labelledby': labelledBy,
  'aria-describedby': describedBy,
}: SelectionInputComponentProps) {
  const As = field.as;

  if (As !== undefined) {
    return (
      <As
        id={id}
        value={value}
        onChange={onChange}
        onMessage={onMessage}
        check={check}
        required={required}
        disabled={disabled}
        {...(labelledBy === undefined ? {} : { 'aria-labelledby': labelledBy })}
        {...(describedBy === undefined ? {} : { 'aria-describedby': describedBy })}
      />
    );
  }

  return (
    <input
      id={id}
      className="field-input w-40"
      autoComplete="off"
      autoCorrect="off"
      autoCapitalize="none"
      spellCheck={false}
      inputMode={field.type === 'integer' ? 'numeric' : undefined}
      required={required}
      disabled={disabled}
      aria-labelledby={labelledBy}
      aria-describedby={describedBy}
      value={value}
      onChange={(event) => {
        onChange(event.target.value, true);
      }}
    />
  );
}
