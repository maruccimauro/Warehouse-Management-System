import { useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { useTransactionForm } from '@/app/shell/useTransactionForm';
import { HelpLink } from '@/shared/help/HelpLink';
import { optionalPositiveInteger, required, textRule } from './fieldRules';
import { WarehouseScopeProvider, ZoneField } from './LocationFields';
import { useZones } from './warehouseQueries';
import { LocationInitialScreen, type LocationTarget } from './LocationInitialScreen';
import { createLocation, locationsQueryKey, updateLocation, type LocationInput, normalizeCode } from './warehousesApi';

type MaintenanceActivity = 'create' | 'change' | 'display';

// LS01N, LS02N y LS03N: crear, modificar y visualizar una ubicación (E2.12). La misma
// pantalla en tres modos: inicial con depósito y ubicación (F8) y la ficha. Al grabar vuelve
// a la pantalla inicial con el depósito y la ubicación, lista para la siguiente.
export function LocationTransaction({ activity }: { activity: MaintenanceActivity }) {
  const [target, setTarget] = useState<LocationTarget>();
  const [last, setLast] = useState<{ warehouse: string; location: string }>();

  if (target === undefined) {
    return (
      <LocationInitialScreen
        activity={activity}
        initial={last}
        onContinue={(next) => {
          setLast({ warehouse: next.warehouse.code, location: next.code });
          setTarget(next);
        }}
      />
    );
  }

  return (
    <LocationDetail
      activity={activity}
      target={target}
      onDone={() => {
        setTarget(undefined);
      }}
    />
  );
}

export function CreateLocationTransaction() {
  return <LocationTransaction activity="create" />;
}

export function ChangeLocationTransaction() {
  return <LocationTransaction activity="change" />;
}

export function DisplayLocationTransaction() {
  return <LocationTransaction activity="display" />;
}

const locationSchema = z.object({
  code: z.string(),
  zone: required(),
  aisle: textRule(10),
  rack: textRule(10),
  level: textRule(10),
  position: textRule(10),
  maxUnits: optionalPositiveInteger(),
  status: z.string(),
});

const nullable = (text: string) => (text === '' ? null : text);

interface LocationDetailProps {
  activity: MaintenanceActivity;
  target: LocationTarget;
  onDone: () => void;
}

function LocationDetail({ activity, target, onDone }: LocationDetailProps) {
  const { t } = useTranslation('locations');
  const { t: tZones } = useTranslation('zones');
  const queryClient = useQueryClient();
  const zones = useZones(target.warehouse.id);
  const { location } = target;
  const system = location?.is_system === true;
  const allZones = zones.data ?? [];
  // Al crear o modificar se ofrecen solo las zonas propias: las de sistema no admiten
  // ubicaciones dadas de alta a mano (DM-19). Una ubicación de sistema muestra la suya.
  const offeredZones = activity === 'display' || system ? allZones : allZones.filter((zone) => !zone.is_system);
  const zoneOf = (id: number | undefined) => allZones.find((zone) => zone.id === id);

  const defaultValues = useMemo(
    () => ({
      code: target.code,
      zone: '',
      aisle: location?.aisle ?? '',
      rack: location?.rack ?? '',
      level: location?.level ?? '',
      position: location?.position ?? '',
      maxUnits: location?.max_units == null ? '' : String(location.max_units),
      status: location?.status ?? 'active',
    }),
    [target.code, location],
  );
  // El código de la zona se conoce cuando llegan las zonas del depósito.
  const zoneCode = zoneOf(location?.zone_id)?.code;
  const values = useMemo(() => ({ ...defaultValues, zone: zoneCode ?? '' }), [defaultValues, zoneCode]);

  // Activar y desactivar se hace desde la ficha; bloquear tiene su transacción (D2 de E2.12).
  const statusEditable = activity === 'change' && !system && (location?.status === 'active' || location?.status === 'inactive');

  if (zones.data === undefined) {
    return <p className="text-ink-muted">{t('detail.loading')}</p>;
  }

  return (
    <WarehouseScopeProvider scope={{ warehouseId: target.warehouse.id, zones: offeredZones }}>
      <LocationForm
        key={zoneCode ?? 'no-zone'}
        activity={activity}
        target={target}
        defaultValues={values}
        statusEditable={statusEditable}
        onSaved={() => {
          // La lista se actualiza en segundo plano: el mensaje y la vuelta no la esperan.
          void queryClient.invalidateQueries({ queryKey: locationsQueryKey(target.warehouse.id) });
          onDone();
        }}
        onBack={onDone}
        zoneIdOf={(code) => allZones.find((zone) => zone.code === code)?.id}
      />
      {location !== undefined && (
        <section className="mt-3 grid max-w-4xl gap-1 text-ink-muted">
          <p>
            {t('fields.status')}:{' '}
            <HelpLink helpKey={`state.location.${location.status}`}>{t(`statuses.${location.status}`)}</HelpLink>
            {location.status_reason !== null && ` (${location.status_reason})`}
          </p>
          {location.system_role !== null && <p>{t('detail.systemRole', { role: t(`systemRoles.${location.system_role}`) })}</p>}
          {zoneOf(location.zone_id) !== undefined && (
            <p>{t('detail.zoneType', { type: tZones(`types.${zoneOf(location.zone_id)?.type ?? 'storage'}`) })}</p>
          )}
        </section>
      )}
    </WarehouseScopeProvider>
  );
}

interface LocationFormProps {
  activity: MaintenanceActivity;
  target: LocationTarget;
  defaultValues: z.input<typeof locationSchema>;
  statusEditable: boolean;
  onSaved: () => void;
  onBack: () => void;
  zoneIdOf: (code: string) => number | undefined;
}

function LocationForm({ activity, target, defaultValues, statusEditable, onSaved, onBack, zoneIdOf }: LocationFormProps) {
  const { t } = useTranslation('locations');
  const { location } = target;
  const system = location?.is_system === true;

  const { Form, Section, Field } = useTransactionForm({
    schema: locationSchema,
    defaultValues,
    namespace: 'locations',
    readOnly: activity === 'display',
    savedMessage: activity === 'create' ? t('messages.created', { code: target.code }) : t('messages.saved', { code: target.code }),
    saveFailedMessage: t('messages.saveFailed'),
    serverFields: { zone_id: 'zone', max_units: 'maxUnits' },
    onBack,
    onSave: async (values) => {
      const zoneId = zoneIdOf(normalizeCode(values.zone));
      const input: LocationInput = {
        aisle: nullable(values.aisle),
        rack: nullable(values.rack),
        level: nullable(values.level),
        position: nullable(values.position),
        max_units: values.maxUnits === '' ? null : Number(values.maxUnits),
      };

      if (!system && zoneId !== undefined) {
        input.zone_id = zoneId;
      }

      if (statusEditable && (values.status === 'active' || values.status === 'inactive')) {
        input.status = values.status;
      }

      if (activity === 'create') {
        await createLocation(target.warehouse.id, { ...input, code: target.code, zone_id: zoneId ?? 0 });
      } else {
        await updateLocation(location?.id ?? 0, input);
      }

      onSaved();
    },
  });

  const statusOptions = [
    { value: 'active', label: t('statuses.active') },
    { value: 'inactive', label: t('statuses.inactive') },
  ];

  return (
    <div className="grid gap-3">
      <p className="font-medium">{t('detail.warehouse', { code: target.warehouse.code, name: target.warehouse.name })}</p>
      <Form>
        <Section name="general">
          <Field name="code" readOnly className="w-48" />
          <Field name="zone" as={ZoneField} required readOnly={system} />
          {statusEditable && <Field name="status" options={statusOptions} />}
        </Section>
        <Section name="coordinates">
          <Field name="aisle" className="w-24 uppercase" />
          <Field name="rack" className="w-24" />
          <Field name="level" className="w-24" />
          <Field name="position" className="w-24" />
        </Section>
        <Section name="capacity">
          <Field name="maxUnits" inputMode="numeric" className="w-32" description={t('detail.maxUnitsHint')} />
        </Section>
      </Form>
      {activity !== 'display' && <p className="text-xs text-ink-muted">{t('detail.saveHint')}</p>}
    </div>
  );
}
