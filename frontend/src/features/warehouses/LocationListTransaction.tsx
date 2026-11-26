import { useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { useTransactionActions } from '@/app/shell/shellActions';
import { useStatusBar } from '@/app/shell/statusMessages';
import { useScreenContext } from '@/app/shell/useScreenContext';
import type { WarehouseSummary } from '@/features/auth/authApi';
import type { ListFilterValue } from '@/shared/api/listing';
import { DataGrid } from '@/shared/ui/grid/DataGrid';
import type { GridColumn, GridSort, GridSource } from '@/shared/ui/grid/gridTypes';
import { SelectionScreen } from '@/shared/ui/selection/SelectionScreen';
import { parameterValue, selectionFilters } from '@/shared/ui/selection/selection';
import type { SelectionField, SelectionValues } from '@/shared/ui/selection/selectionTypes';
import { useSelectionScreen } from '@/shared/ui/selection/useSelectionScreen';
import { locationLink } from './locationLinks';
import { useZones } from './warehouseQueries';
import { useDefaultWarehouse } from './useDefaultWarehouse';
import { VisibleWarehouseField } from './WarehouseField';
import { useVisibleWarehouses } from './warehouseQueries';
import { fetchLocations, locationsQueryKey, locationStatuses, normalizeCode, type Location, type Zone } from './warehousesApi';
import { InfoNotice } from '@/shared/ui/InfoNotice';

const byCode: GridSort = { columnId: 'code', desc: false };

// LX03: lista de ubicaciones (E2.12). Pantalla de selección (depósito y ubicación con rango,
// valores, patrón con * y exclusiones) y, con F8, el árbol de zonas del depósito al lado de la
// grilla en modo servidor (D4). Elegir una zona filtra la grilla; doble clic abre la ubicación.
export function LocationListTransaction() {
  const { t } = useTranslation('locations');
  const { show } = useStatusBar();
  const visible = useVisibleWarehouses();
  const defaultWarehouse = useDefaultWarehouse();
  const [result, setResult] = useState<{ warehouse: WarehouseSummary; values: SelectionValues }>();
  useScreenContext(result === undefined ? 'selection' : 'list');

  const fields = useMemo(
    (): SelectionField[] => [
      { kind: 'parameter', id: 'warehouse', label: t('fields.warehouse'), as: VisibleWarehouseField, required: true },
      { kind: 'selectOptions', id: 'code', label: t('fields.code'), normalize: normalizeCode },
    ],
    [t],
  );
  const selection = useSelectionScreen(fields, { warehouse: defaultWarehouse ?? '' });

  const execute = () => {
    const values = selection.validate(show);
    const code = values === undefined ? undefined : parameterValue(values, 'warehouse');
    const warehouse = visible.data?.find((candidate) => candidate.code === code);

    if (values !== undefined && warehouse !== undefined) {
      setResult({ warehouse, values });
    }
  };

  if (result === undefined) {
    return <ListSelection selection={selection} onExecute={execute} />;
  }

  return (
    <LocationResult
      warehouse={result.warehouse}
      filters={selectionFilters(fields, result.values)}
      onBack={() => {
        setResult(undefined);
      }}
    />
  );
}

function ListSelection({ selection, onExecute }: { selection: ReturnType<typeof useSelectionScreen>; onExecute: () => void }) {
  const { t } = useTranslation('locations');
  const { show } = useStatusBar();

  useTransactionActions({ execute: onExecute });

  return (
    <div className="grid gap-3">
      <SelectionScreen selection={selection} title={t('list.selection')} onMessage={show} />
      <InfoNotice>{t('list.hint')}</InfoNotice>
    </div>
  );
}

interface LocationResultProps {
  warehouse: WarehouseSummary;
  filters: Record<string, ListFilterValue>;
  onBack: () => void;
}

function LocationResult({ warehouse, filters, onBack }: LocationResultProps) {
  const { t } = useTranslation('locations');
  const { t: tZones } = useTranslation('zones');
  const { show } = useStatusBar();
  const navigate = useNavigate();
  const zones = useZones(warehouse.id);
  const [zoneId, setZoneId] = useState<number>();

  useTransactionActions({ back: onBack });

  const zoneCodes = useMemo(() => new Map((zones.data ?? []).map((zone) => [zone.id, zone.code])), [zones.data]);

  const columns = useMemo(
    (): GridColumn<Location>[] => [
      // El código se restringe en la pantalla de selección, que admite rangos y patrones.
      { id: 'code', header: t('fields.code'), value: (row) => row.code, sortable: true, filter: false },
      { id: 'zone', header: t('fields.zone'), value: (row) => zoneCodes.get(row.zone_id) ?? '' },
      { id: 'aisle', header: t('fields.aisle'), value: (row) => row.aisle, sortable: true },
      { id: 'rack', header: t('fields.rack'), value: (row) => row.rack, sortable: true },
      { id: 'level', header: t('fields.level'), value: (row) => row.level, sortable: true },
      { id: 'position', header: t('fields.position'), value: (row) => row.position },
      {
        id: 'status',
        header: t('fields.status'),
        value: (row) => row.status,
        format: (value) => t(`statuses.${String(value) as Location['status']}`),
        sortable: true,
        filter: 'in',
        options: locationStatuses.map((status) => ({ value: status, label: t(`statuses.${status}`) })),
      },
      { id: 'status_reason', header: t('fields.statusReason'), value: (row) => row.status_reason },
      { id: 'max_units', header: t('fields.maxUnits'), value: (row) => row.max_units, type: 'number' },
      {
        id: 'is_system',
        header: t('fields.isSystem'),
        value: (row) => (row.is_system ? '1' : '0'),
        format: (value) => tZones(value === '1' ? 'yes' : 'no'),
        filter: 'equals',
        options: [
          { value: '1', label: tZones('yes') },
          { value: '0', label: tZones('no') },
        ],
      },
    ],
    [t, tZones, zoneCodes],
  );

  const queryFilters = useMemo(
    (): Record<string, ListFilterValue> => (zoneId === undefined ? filters : { ...filters, zone_id: [String(zoneId)] }),
    [filters, zoneId],
  );

  const source = useMemo(
    (): GridSource<Location> => ({
      kind: 'server',
      queryKey: [...locationsQueryKey(warehouse.id), 'list', queryFilters],
      fetchPage: (params, signal) => fetchLocations(warehouse.id, { ...params, filters: { ...params.filters, ...queryFilters } }, signal),
    }),
    [warehouse.id, queryFilters],
  );
  const exportName = `ubicaciones-${warehouse.code.toLowerCase()}`;

  return (
    <div className="grid gap-3">
      <p className="font-medium">{t('detail.warehouse', { code: warehouse.code, name: warehouse.name })}</p>
      <div className="grid items-start gap-3 md:grid-cols-[minmax(12rem,16rem)_1fr]">
        <ZoneTree zones={zones.data ?? []} selected={zoneId} onSelect={setZoneId} />
        <DataGrid
          label={t('list.title', { code: warehouse.code })}
          columns={columns}
          source={source}
          rowKey={(row) => row.id}
          exportName={exportName}
          onMessage={show}
          onRowActivate={(row) => {
            void navigate(locationLink('LS03N', warehouse.code, row.code));
          }}
          initialSort={byCode}
        />
      </div>
    </div>
  );
}

interface ZoneTreeProps {
  zones: readonly Zone[];
  selected: number | undefined;
  onSelect: (zoneId: number | undefined) => void;
}

// Árbol de zonas: la raíz (todas las zonas) y una zona por hoja. Una sola parada de
// tabulación; flechas arriba y abajo, Inicio y Fin mueven el foco y Enter o Espacio eligen.
function ZoneTree({ zones, selected, onSelect }: ZoneTreeProps) {
  const { t } = useTranslation('locations');
  const { t: tZones } = useTranslation('zones');
  const ref = useRef<HTMLUListElement>(null);
  const items: { id: number | undefined; label: string; detail: string }[] = [
    { id: undefined, label: t('tree.all'), detail: '' },
    ...zones.map((zone) => ({ id: zone.id, label: zone.code, detail: `${zone.name} · ${tZones(`types.${zone.type}`)}` })),
  ];
  const selectedIndex = Math.max(0, items.findIndex((item) => item.id === selected));

  const onKeyDown = (event: KeyboardEvent<HTMLUListElement>) => {
    const nodes = [...(ref.current?.querySelectorAll<HTMLElement>('[role="treeitem"]') ?? [])];
    const index = nodes.findIndex((node) => node === document.activeElement);
    const moves: Record<string, number> = { ArrowDown: index + 1, ArrowUp: index - 1, Home: 0, End: nodes.length - 1 };
    const target = moves[event.key];

    if (target !== undefined) {
      event.preventDefault();
      nodes[Math.min(Math.max(target, 0), nodes.length - 1)]?.focus();
    } else if ((event.key === 'Enter' || event.key === ' ') && index >= 0) {
      event.preventDefault();
      onSelect(items[index]?.id);
    }
  };

  return (
    <nav aria-label={t('tree.label')} className="rounded-sm border border-border bg-surface">
      <ul ref={ref} role="tree" aria-label={t('tree.label')} className="grid py-1" onKeyDown={onKeyDown}>
        {items.map((item, index) => (
          <li
            key={item.id ?? 'all'}
            role="treeitem"
            aria-level={item.id === undefined ? 1 : 2}
            aria-selected={index === selectedIndex}
            tabIndex={index === selectedIndex ? 0 : -1}
            className={`cursor-pointer px-3 py-1 outline-offset-[-2px] ${item.id === undefined ? '' : 'ps-6'} ${
              index === selectedIndex ? 'bg-selected font-medium' : 'hover:bg-hover'
            }`}
            onClick={() => {
              onSelect(item.id);
            }}
          >
            <span className="font-mono">{item.label}</span>
            {item.detail !== '' && <span className="block truncate text-xs text-ink-muted">{item.detail}</span>}
          </li>
        ))}
      </ul>
    </nav>
  );
}
