import type { GridMessage } from '../grid/gridTypes';
import type { SelectionField } from './selectionTypes';

interface SelectionInputComponentProps {
  field: SelectionField;
  id: string;
  value: string;
  onChange: (value: string, valid: boolean) => void;
  onMessage: (message: GridMessage) => void;
  // false en los límites de un SELECT-OPTIONS: sin tabla de verificación (D4 de E2.18).
  check: boolean;
  required?: boolean;
  'aria-labelledby'?: string | undefined;
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
  'aria-labelledby': labelledBy,
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
        {...(labelledBy === undefined ? {} : { 'aria-labelledby': labelledBy })}
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
      aria-labelledby={labelledBy}
      value={value}
      onChange={(event) => {
        onChange(event.target.value, true);
      }}
    />
  );
}
