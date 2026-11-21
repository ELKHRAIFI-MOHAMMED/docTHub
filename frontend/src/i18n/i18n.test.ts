import { describe, expect, it } from 'vitest';
import en from './locales/en.json';
import fr from './locales/fr.json';
import ar from './locales/ar.json';
import { applyDocumentLocale } from '.';

const PLURAL = /_(zero|one|two|few|many|other)$/;

function keys(obj: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(obj).flatMap(([k, v]) =>
    v && typeof v === 'object' ? keys(v as Record<string, unknown>, `${prefix}${k}.`) : [`${prefix}${k}`.replace(PLURAL, '')],
  );
}

describe('i18n', () => {
  const reference = new Set(keys(en));

  it.each([['fr', fr], ['ar', ar]])('%s has exactly the same keys as English', (_, dict) => {
    const other = new Set(keys(dict as Record<string, unknown>));
    expect([...reference].filter((k) => !other.has(k))).toEqual([]);
    expect([...other].filter((k) => !reference.has(k))).toEqual([]);
  });

  it('switches the document to RTL for Arabic and back', () => {
    applyDocumentLocale('ar');
    expect(document.documentElement.dir).toBe('rtl');
    expect(document.documentElement.lang).toBe('ar');
    applyDocumentLocale('fr');
    expect(document.documentElement.dir).toBe('ltr');
  });
});
