import { useQuery } from '@tanstack/react-query';
import { categoriesQueryKey, fetchAllCategories, fetchAllUnits, unitsQueryKey, type Category } from './catalogApi';

// Categorías y unidades de medida: pocas filas, se leen enteras para las ayudas locales y para
// mostrar la descripción en las fichas y la lista.
export function useCategories() {
  return useQuery({ queryKey: categoriesQueryKey, queryFn: ({ signal }) => fetchAllCategories(signal) });
}

export function useUnits() {
  return useQuery({ queryKey: unitsQueryKey, queryFn: ({ signal }) => fetchAllUnits(signal) });
}

// Ruta de una categoría desde la raíz ("Ferretería / Tornillería"): el nombre solo no es único,
// porque dos categorías con distinto padre pueden llamarse igual.
export const categoryPathSeparator = ' / ';

export function categoryPaths(categories: readonly Category[]): Map<number, string> {
  const byId = new Map(categories.map((category) => [category.id, category]));
  const paths = new Map<number, string>();

  const pathOf = (category: Category, seen: Set<number>): string => {
    const cached = paths.get(category.id);

    if (cached !== undefined) {
      return cached;
    }

    const parent = category.parent_id === null ? undefined : byId.get(category.parent_id);
    // El backend impide los ciclos (CATEGORY_HIERARCHY_CYCLE); seen evita un bucle igual.
    const path =
      parent === undefined || seen.has(parent.id)
        ? category.name
        : `${pathOf(parent, new Set([...seen, category.id]))}${categoryPathSeparator}${category.name}`;

    paths.set(category.id, path);

    return path;
  };

  categories.forEach((category) => {
    pathOf(category, new Set([category.id]));
  });

  return paths;
}
