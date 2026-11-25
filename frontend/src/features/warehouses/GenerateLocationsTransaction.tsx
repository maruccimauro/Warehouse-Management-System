import { useQueryClient } from '@tanstack/react-query';
import type { TFunction } from 'i18next';
import { useMemo, useRef, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { StatusMessageError } from '@/app/shell/statusMessages';
import { useTransactionForm } from '@/app/shell/useTransactionForm';
import { useAuthenticatedUser } from '@/features/auth/useAuthenticatedUser';
import { HelpLink } from '@/shared/help/HelpLink';
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog';
import { formatNumber } from '@/shared/format/format';
import { aisleRule, gridNumberRule, optionalPositiveInteger, required } from './fieldRules';
import { WarehouseScopeProvider, ZoneField } from './LocationFields';
import { useZones } from './warehouseQueries';
import { useDefaultWarehouse } from './useDefaultWarehouse';
import { WarehouseField } from './WarehouseField';
import {
  generateLocations,
  locationsQueryKey,
  maxGeneratedLocations,
  maxGridNumber,
  templateSize,
  type LocationTemplate,
  fetchAllZones,
  zonesQueryKey, normalizeCode } from './warehousesApi';

const optionalGridNumber = z.union([z.literal(''), gridNumberRule(maxGridNumber)]);

// El esquema se arma con los textos del idioma: los errores de rango son propios de la pantalla.
function templateSchemaOf(t: TFunction<'locations'>) {
  return z
  .object({
    warehouse: required(),
    zone: required(),
    aislesFrom: aisleRule(),
    aislesTo: aisleRule(),
    racksFrom: gridNumberRule(maxGridNumber),
    racksTo: gridNumberRule(maxGridNumber),
    levelsFrom: gridNumberRule(maxGridNumber),
    levelsTo: gridNumberRule(maxGridNumber),
    positionsFrom: optionalGridNumber,
    positionsTo: optionalGridNumber,
    maxUnits: optionalPositiveInteger(),
  })
  .superRefine((values, context) => {
    const inverted = (from: string, to: string) => from !== '' && to !== '' && from.toUpperCase() > to.toUpperCase();
    const invertedNumber = (from: string, to: string) => from !== '' && to !== '' && Number(from) > Number(to);

    if (inverted(values.aislesFrom, values.aislesTo)) {
      context.addIssue({ code: 'custom', path: ['aislesTo'], message: t('generate.rangeInverted') });
    }

    if (invertedNumber(values.racksFrom, values.racksTo)) {
      context.addIssue({ code: 'custom', path: ['racksTo'], message: t('generate.rangeInverted') });
    }

    if (invertedNumber(values.levelsFrom, values.levelsTo)) {
      context.addIssue({ code: 'custom', path: ['levelsTo'], message: t('generate.rangeInverted') });
    }

    if ((values.positionsFrom === '') !== (values.positionsTo === '')) {
      context.addIssue({ code: 'custom', path: [values.positionsFrom === '' ? 'positionsFrom' : 'positionsTo'], message: t('generate.positionsIncomplete') });
    } else if (invertedNumber(values.positionsFrom, values.positionsTo)) {
      context.addIssue({ code: 'custom', path: ['positionsTo'], message: t('generate.rangeInverted') });
    }
  });
}

type TemplateSchema = ReturnType<typeof templateSchemaOf>;
type TemplateValues = z.input<TemplateSchema>;

// Plantilla que arma la pantalla con lo ingresado; undefined mientras falte un dato o un rango
// no sea válido. La cuenta es la misma que hace el backend.
function templateOf(schema: TemplateSchema, values: TemplateValues): Omit<LocationTemplate, 'zone_id'> | undefined {
  const parsed = schema.safeParse(values);

  if (!parsed.success) {
    return undefined;
  }

  const data = parsed.data;

  return {
    aisles: { from: data.aislesFrom.toUpperCase(), to: data.aislesTo.toUpperCase() },
    racks: { from: Number(data.racksFrom), to: Number(data.racksTo) },
    levels: { from: Number(data.levelsFrom), to: Number(data.levelsTo) },
    positions: data.positionsFrom === '' ? null : { from: Number(data.positionsFrom), to: Number(data.positionsTo) },
    max_units: data.maxUnits === '' ? null : Number(data.maxUnits),
  };
}

const pad = (value: number) => String(value).padStart(2, '0');

function firstAndLast(template: Omit<LocationTemplate, 'zone_id'>): [string, string] {
  const code = (aisleCode: string, rack: number, level: number, position: number | undefined) =>
    [aisleCode, pad(rack), pad(level), ...(position === undefined ? [] : [pad(position)])].join('-');

  return [
    code(template.aisles.from, template.racks.from, template.levels.from, template.positions?.from),
    code(template.aisles.to, template.racks.to, template.levels.to, template.positions?.to),
  ];
}

// LS10: generación masiva de ubicaciones (E2.12, DM-19). Pasillos por letra, estanterías,
// niveles y posiciones por número; F8 muestra cuántas ubicaciones se crean y pide
// confirmación. El lote es todo o nada: un código que ya existe no crea ninguna.
export function GenerateLocationsTransaction() {
  const { t } = useTranslation('locations');
  const { t: tWarehouses } = useTranslation('warehouses');
  const user = useAuthenticatedUser();
  const queryClient = useQueryClient();
  const defaultWarehouse = useDefaultWarehouse();
  const [pending, setPending] = useState<{ count: number; first: string; last: string; zone: string }>();
  const resolveConfirm = useRef<(confirmed: boolean) => void>(undefined);

  const templateSchema = useMemo(() => templateSchemaOf(t), [t]);
  const defaultValues = useMemo(
    (): TemplateValues => ({
      warehouse: defaultWarehouse ?? '',
      zone: '',
      aislesFrom: '',
      aislesTo: '',
      racksFrom: '',
      racksTo: '',
      levelsFrom: '',
      levelsTo: '',
      positionsFrom: '',
      positionsTo: '',
      maxUnits: '',
    }),
    [defaultWarehouse],
  );

  const confirm = (summary: NonNullable<typeof pending>) =>
    new Promise<boolean>((resolve) => {
      resolveConfirm.current = resolve;
      setPending(summary);
    });

  const closeConfirm = (confirmed: boolean) => {
    setPending(undefined);
    resolveConfirm.current?.(confirmed);
    resolveConfirm.current = undefined;
  };


  const { Form, Section, Field, values } = useTransactionForm({
    schema: templateSchema,
    defaultValues,
    namespace: 'locations',
    submitWith: 'execute',
    savedMessage: t('generate.done', { count: 0, zone: '' }),
    saveFailedMessage: t('generate.failed'),
    serverFields: {
      zone_id: 'zone',
      aisles: 'aislesFrom',
      'aisles.from': 'aislesFrom',
      'aisles.to': 'aislesTo',
      'racks.from': 'racksFrom',
      'racks.to': 'racksTo',
      'levels.from': 'levelsFrom',
      'levels.to': 'levelsTo',
      'positions.from': 'positionsFrom',
      'positions.to': 'positionsTo',
      max_units: 'maxUnits',
    },
    onSave: async (data) => {
      const warehouseCode = normalizeCode(data.warehouse);
      const zoneCode = normalizeCode(data.zone);
      const warehouse = user.warehouses.find((candidate) => candidate.code === warehouseCode);

      if (warehouse === undefined) {
        throw new StatusMessageError({ type: 'E', text: tWarehouses('searchHelp.invalid', { code: warehouseCode }), id: 'VALUE_NOT_FOUND' });
      }

      // Las zonas pueden no haber llegado todavía: se esperan antes de decidir.
      const zones = await queryClient.query({
        queryKey: zonesQueryKey(warehouse.id),
        queryFn: ({ signal }) => fetchAllZones(warehouse.id, signal),
        // Las que ya están en caché sirven: no hace falta volver a pedirlas.
        staleTime: 'static',
      });
      const zone = zones.find((candidate) => !candidate.is_system && candidate.code === zoneCode);

      if (zone === undefined) {
        throw new StatusMessageError({ type: 'E', text: t('zoneHelp.invalid', { code: zoneCode }), id: 'VALUE_NOT_FOUND' });
      }

      // El esquema ya validó la plantilla: siempre está completa acá.
      const template = templateOf(templateSchema, data);

      if (template === undefined) {
        throw new StatusMessageError({ type: 'E', text: t('generate.failed') });
      }

      const count = templateSize(template);

      // El límite lo controla también el backend (422); acá se avisa antes de enviar.
      if (count > maxGeneratedLocations) {
        throw new StatusMessageError({
          type: 'E',
          text: t('generate.tooMany', { count: formatNumber(count), max: formatNumber(maxGeneratedLocations) }),
          id: 'LOCATION_BATCH_TOO_LARGE',
        });
      }

      const [first, last] = firstAndLast(template);

      if (!(await confirm({ count, first, last, zone: zone.code }))) {
        throw new StatusMessageError({ type: 'I', text: t('generate.cancelled') });
      }

      const generated = await generateLocations(warehouse.id, { ...template, zone_id: zone.id });

      void queryClient.invalidateQueries({ queryKey: locationsQueryKey(warehouse.id) });

      return t('generate.done', { count: generated, zone: zone.code });
    },
  });

  const warehouse = user.warehouses.find((candidate) => candidate.code === normalizeCode(values.warehouse));
  const zones = useZones(warehouse?.id);
  const userZones = useMemo(() => (zones.data ?? []).filter((zone) => !zone.is_system), [zones.data]);

  const template = templateOf(templateSchema, values);
  const count = template === undefined ? undefined : templateSize(template);
  const preview = template === undefined ? undefined : firstAndLast(template);

  return (
    <WarehouseScopeProvider scope={{ warehouseId: warehouse?.id, zones: userZones, zonesLoading: zones.isFetching }}>
      <div className="grid gap-3">
        <Form>
          <Section name="target">
            <Field name="warehouse" as={WarehouseField} required />
            <Field name="zone" as={ZoneField} required />
          </Section>
          <Section name="template">
            <RangeRow label={t('generate.aisles')}>
              <Field name="aislesFrom" required className="w-16 uppercase" />
              <Field name="aislesTo" required className="w-16 uppercase" />
            </RangeRow>
            <RangeRow label={t('generate.racks')}>
              <Field name="racksFrom" required inputMode="numeric" className="w-16" />
              <Field name="racksTo" required inputMode="numeric" className="w-16" />
            </RangeRow>
            <RangeRow label={t('generate.levels')}>
              <Field name="levelsFrom" required inputMode="numeric" className="w-16" />
              <Field name="levelsTo" required inputMode="numeric" className="w-16" />
            </RangeRow>
            <RangeRow label={t('generate.positions')}>
              <Field name="positionsFrom" inputMode="numeric" className="w-16" />
              <Field name="positionsTo" inputMode="numeric" className="w-16" />
            </RangeRow>
            <Field name="maxUnits" inputMode="numeric" className="w-32" description={t('detail.maxUnitsHint')} />
          </Section>
        </Form>

        <section aria-live="polite" className="grid max-w-4xl gap-1 rounded-sm border border-border bg-surface px-3 py-2">
          {count === undefined || preview === undefined ? (
            <p className="text-ink-muted">{t('generate.previewEmpty')}</p>
          ) : (
            <>
              <p>{t('generate.preview', { count: formatNumber(count), first: preview[0], last: preview[1] })}</p>
              {count > maxGeneratedLocations && (
                <p className="text-error">
                  {t('generate.tooMany', { count: formatNumber(count), max: formatNumber(maxGeneratedLocations) })}{' '}
                  <HelpLink helpKey="message.LOCATION_BATCH_TOO_LARGE">{t('generate.whatToDo')}</HelpLink>
                </p>
              )}
            </>
          )}
        </section>
        <p className="text-xs text-ink-muted">{t('generate.hint')}</p>
      </div>

      <ConfirmDialog
        open={pending !== undefined}
        title={t('generate.confirmTitle')}
        message={
          pending === undefined
            ? ''
            : t('generate.confirm', { count: formatNumber(pending.count), first: pending.first, last: pending.last, zone: pending.zone })
        }
        confirmLabel={t('generate.confirmAction')}
        cancelLabel={t('generate.confirmCancel')}
        onConfirm={() => {
          closeConfirm(true);
        }}
        onCancel={() => {
          closeConfirm(false);
        }}
      />
    </WarehouseScopeProvider>
  );
}

// Desde y hasta de una coordenada en una fila, con el rótulo de la coordenada como grupo.
function RangeRow({ label, children }: { label: string; children: ReactNode }) {
  return (
    <fieldset className="grid gap-1">
      <legend className="text-xs font-semibold text-ink-muted">{label}</legend>
      <div className="flex flex-wrap gap-x-6 gap-y-1">{children}</div>
    </fieldset>
  );
}
