import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { GridDialog } from './GridDialog';

function Nested({ onOuterCancel }: { onOuterCancel: () => void }) {
  const [inner, setInner] = useState(false);

  return (
    <GridDialog open title="Exterior" onCancel={onOuterCancel}>
      <button
        type="button"
        onClick={() => {
          setInner(true);
        }}
      >
        Abrir interior
      </button>
      <GridDialog
        open={inner}
        title="Interior"
        onCancel={() => {
          setInner(false);
        }}
      >
        <button
          type="button"
          onClick={() => {
            setInner(false);
          }}
        >
          Cerrar interior
        </button>
      </GridDialog>
    </GridDialog>
  );
}

describe('GridDialog', () => {
  it('cerrar un diálogo anidado no cierra el que lo contiene', async () => {
    const onOuterCancel = vi.fn();

    render(<Nested onOuterCancel={onOuterCancel} />);
    await userEvent.click(screen.getByRole('button', { name: 'Abrir interior' }));
    await userEvent.click(await screen.findByRole('button', { name: 'Cerrar interior' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: 'Interior' })).not.toBeInTheDocument();
    });
    expect(onOuterCancel).not.toHaveBeenCalled();
    expect(screen.getByRole('dialog', { name: 'Exterior' })).toBeInTheDocument();
  });
});
