export function formatFee(amount: number, currency: string | null, locale: string): string {
  if (!currency) return String(amount);
  try {
    return new Intl.NumberFormat(locale, { style: 'currency', currency, maximumFractionDigits: 0 }).format(amount);
  } catch {
    return `${amount} ${currency}`;
  }
}

export function formatDate(iso: string | null, locale: string): string {
  if (!iso) return '';
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}
