// Verifica que cada clave de ayuda del frontend (frontend/src/shared/help/helpLinks.ts)
// apunte a una página existente de la wiki y, si tiene ancla, a un {#ancla} explícito de esa
// página (DM-11, D9 de E2.16). El build de VitePress detecta los enlaces rotos entre páginas,
// pero no las anclas que usa la aplicación.
//
// Node 26 ejecuta TypeScript borrable sin compilar; helpLinks.ts no importa nada en tiempo
// de ejecución, así que se importa directamente. En el contenedor wiki, docker-compose monta
// frontend/src/shared/help en la misma posición relativa que en el repositorio.
import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { helpLinks } from '../../../frontend/src/shared/help/helpLinks.ts';

const wikiRoot = fileURLToPath(new URL('..', import.meta.url));
const pageCache = new Map<string, string | undefined>();

// Una página puede incluir fragmentos con <!--@include: ./ruta.md-->, como hace VitePress al
// compilar: las anclas del fragmento son anclas de la página que lo incluye.
const includePattern = /<!--\s*@include:\s*([^\s>]+?)\s*-->/g;

function withIncludes(file: string, depth = 0): string {
  const source = readFileSync(file, 'utf8');

  if (depth > 5) {
    return source;
  }

  return source.replaceAll(includePattern, (_, relative: string) => {
    const included = fileURLToPath(new URL(relative, pathToFileURL(file)));

    return existsSync(included) ? withIncludes(included, depth + 1) : '';
  });
}

function pageSource(page: string): string | undefined {
  if (!pageCache.has(page)) {
    const file = `${wikiRoot}${page === '' ? 'index' : page}.md`;
    pageCache.set(page, existsSync(file) ? withIncludes(file) : undefined);
  }

  return pageCache.get(page);
}

const problems: string[] = [];

for (const [key, target] of Object.entries(helpLinks)) {
  const [page = '', anchor] = target.split('#');
  const source = pageSource(page);

  if (source === undefined) {
    problems.push(`${key}: no existe la página ${page === '' ? 'index' : page}.md`);
  } else if (anchor !== undefined && !source.includes(`{#${anchor}}`)) {
    problems.push(`${key}: ${page}.md no tiene el ancla {#${anchor}}`);
  }
}

if (problems.length > 0) {
  console.error(`Claves de ayuda sin destino en la wiki (${problems.length}):`);
  for (const problem of problems) {
    console.error(`  ${problem}`);
  }
  process.exit(1);
}

console.log(`${Object.keys(helpLinks).length} claves de ayuda verificadas.`);
