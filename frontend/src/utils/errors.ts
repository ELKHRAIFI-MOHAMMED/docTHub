import type { FetchBaseQueryError } from '@reduxjs/toolkit/query';
import type { SerializedError } from '@reduxjs/toolkit';

type AnyError = FetchBaseQueryError | SerializedError | undefined | unknown;

/** Field errors from a Laravel 422 response: { field: firstMessage }. */
export function fieldErrors(error: AnyError): Record<string, string> {
  const data = (error as FetchBaseQueryError | undefined)?.data as { errors?: Record<string, string[]> } | undefined;
  const out: Record<string, string> = {};
  Object.entries(data?.errors ?? {}).forEach(([k, v]) => {
    out[k] = Array.isArray(v) ? v[0] : String(v);
  });
  return out;
}

/** Human readable message, or null when there is no error. */
export function errorMessage(error: AnyError, fallback: string): string | null {
  if (!error) return null;
  const e = error as FetchBaseQueryError;
  if (e.status === 'FETCH_ERROR') return fallback;
  const data = e.data as { message?: string } | undefined;
  return data?.message ?? fallback;
}

export function errorStatus(error: AnyError): number | string | undefined {
  return (error as FetchBaseQueryError | undefined)?.status;
}
