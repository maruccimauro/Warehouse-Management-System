import { useQuery } from '@tanstack/react-query';
import { fetchAllWarehouses, fetchAllZones, warehousesQueryKey, zonesQueryKey } from './warehousesApi';

// Todos los depósitos que el usuario consulta (GET /warehouses, alcance de consulta), activos
// o no, con su id. Lo usan el campo de consulta y las pantallas que listan.
export function useVisibleWarehouses() {
  return useQuery({
    queryKey: [...warehousesQueryKey, 'all'],
    queryFn: ({ signal }) => fetchAllWarehouses(signal),
  });
}

// Zonas de un depósito: pocas filas, se leen enteras para el F4 local y el árbol.
export function useZones(warehouseId: number | undefined) {
  return useQuery({
    queryKey: zonesQueryKey(warehouseId ?? 0),
    queryFn: ({ signal }) => fetchAllZones(warehouseId ?? 0, signal),
    enabled: warehouseId !== undefined,
  });
}
