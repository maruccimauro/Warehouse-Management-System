import { createContext, use, useMemo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { SearchHelpField, type SearchHelpFieldProps } from '@/shared/ui/searchHelp/SearchHelpField';
import type { SearchHelp } from '@/shared/ui/searchHelp/searchHelpTypes';
import { fetchLocations, locationsQueryKey, normalizeCode, type Location, type Zone } from './warehousesApi';

// Depósito en el que trabaja la pantalla y sus zonas. Los campos Zona y Ubicación lo leen de
// acá: un campo `as` de los bloques de formulario recibe solo las props del control, y crear
// el componente con el depósito adentro lo volvería a montar en cada cambio.
export interface WarehouseScope {
  // Sin depósito válido todavía (la pantalla inicial antes de completarlo): la ayuda de
  // ubicaciones no tiene filas.
  warehouseId: number | undefined;
  // Zonas que el campo Zona ofrece y acepta (sin las de sistema al crear o modificar).
  zones: readonly Zone[];
  // Las zonas del depósito todavía se están leyendo.
  zonesLoading?: boolean;
}

const WarehouseScopeContext = createContext<WarehouseScope | undefined>(undefined);

export function WarehouseScopeProvider({ scope, children }: { scope: WarehouseScope; children: ReactNode }) {
  return <WarehouseScopeContext value={scope}>{children}</WarehouseScopeContext>;
}

function useWarehouseScope(): WarehouseScope {
  const scope = use(WarehouseScopeContext);

  if (scope === undefined) {
    throw new Error('El campo se usó fuera de WarehouseScopeProvider.');
  }

  return scope;
}

type ZoneFieldProps = Omit<SearchHelpFieldProps<Zone>, 'searchHelp'>;

// Campo Zona con F4 local: las zonas de un depósito son pocas. Valor: el código de la zona.
export function ZoneField(props: ZoneFieldProps) {
  const { t } = useTranslation('locations');
  const { t: tZones } = useTranslation('zones');
  const { zones, zonesLoading = false } = useWarehouseScope();

  const searchHelp = useMemo(
    (): SearchHelp<Zone> => ({
      title: t('zoneHelp.title'),
      columns: [
        { id: 'code', header: t('zoneHelp.code'), value: (row) => row.code },
        { id: 'name', header: t('zoneHelp.name'), value: (row) => row.name },
        { id: 'type', header: t('zoneHelp.type'), value: (row) => row.type, format: (value) => tZones(`types.${String(value) as Zone['type']}`) },
      ],
      source: { kind: 'local', rows: zones, loading: zonesLoading },
      criteria: ['code', 'name'],
      valueOf: (row) => row.code,
      descriptionOf: (row) => row.name,
      normalize: normalizeCode,
      invalidMessage: (code) => t('zoneHelp.invalid', { code }),
      exportName: 'zonas',
    }),
    [t, tZones, zones, zonesLoading],
  );

  return <SearchHelpField {...props} searchHelp={searchHelp} />;
}

type LocationFieldProps = Omit<SearchHelpFieldProps<Location>, 'searchHelp' | 'check'>;

// Campo Ubicación con F4 de servidor (E2.12): un depósito puede tener miles. La restricción
// por código busca por patrón (CP con *fragmento*). Sin tabla de verificación en el campo: la
// pantalla lee la ubicación al continuar e informa si no existe.
export function LocationField(props: LocationFieldProps) {
  const { t } = useTranslation('locations');
  const { warehouseId, zones } = useWarehouseScope();

  const searchHelp = useMemo((): SearchHelp<Location> => {
    const zoneCodes = new Map(zones.map((zone) => [zone.id, zone.code]));

    return {
      title: t('locationHelp.title'),
      columns: [
        { id: 'code', header: t('locationHelp.code'), value: (row) => row.code },
        { id: 'zone', header: t('locationHelp.zone'), value: (row) => zoneCodes.get(row.zone_id) ?? '' },
        { id: 'status', header: t('locationHelp.status'), value: (row) => row.status, format: (value) => t(`statuses.${String(value) as Location['status']}`) },
      ],
      source:
        warehouseId === undefined
          ? { kind: 'local', rows: [] }
          : {
              kind: 'server',
              queryKey: locationsQueryKey(warehouseId),
              fetchPage: (params, signal) =>
                fetchLocations(warehouseId, { ...params, sort: params.sort ?? { field: 'code', desc: false } }, signal),
              filtersOf: (criteria) =>
                criteria['code'] === undefined ? {} : { code: [{ sign: 'I', option: 'CP', low: `*${criteria['code'].toUpperCase()}*` }] },
            },
      criteria: ['code'],
      valueOf: (row) => row.code,
      descriptionOf: () => '',
      normalize: normalizeCode,
      invalidMessage: (code) => t('locationHelp.invalid', { code }),
      exportName: 'ubicaciones',
    };
  }, [t, warehouseId, zones]);

  return <SearchHelpField {...props} check={false} searchHelp={searchHelp} />;
}
