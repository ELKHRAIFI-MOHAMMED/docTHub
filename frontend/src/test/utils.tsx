import type { ReactElement } from 'react';
import { render } from '@testing-library/react';
import { Provider } from 'react-redux';
import { createMemoryRouter, RouterProvider, type RouteObject } from 'react-router-dom';
import { vi } from 'vitest';
import { makeStore, type RootState } from '../app/store';
import { api } from '../app/api';
import { initI18n } from '../i18n';

type Handler = (url: URL, init: Request) => unknown | Promise<unknown>;

/**
 * Stubs global fetch. `routes` maps "METHOD /path" (path relative to /api/v1) to a JSON body
 * or a function. Unknown routes return 404. Returns the spy to assert calls.
 */
export function mockApi(routes: Record<string, unknown | Handler>) {
  const spy = vi.fn(async (input: RequestInfo | URL) => {
    const req = input instanceof Request ? input : new Request(String(input));
    const url = new URL(req.url);
    const path = url.pathname.replace(/^\/api\/v1/, '');
    const key = `${req.method} ${path}`;
    const match = routes[key];
    if (match === undefined) return new Response(JSON.stringify({ message: 'Not found' }), { status: 404, headers: { 'Content-Type': 'application/json' } });
    const body = typeof match === 'function' ? await (match as Handler)(url, req) : match;
    const status = (body as { __status?: number })?.__status ?? 200;
    return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
  });
  vi.stubGlobal('fetch', spy);
  return spy;
}

export function renderWithProviders(
  ui: ReactElement,
  { path = '/', route = '/', state, extraRoutes = [] }: { path?: string; route?: string; state?: Partial<RootState>; extraRoutes?: RouteObject[] } = {},
) {
  const store = makeStore(state);
  store.dispatch(api.util.resetApiState());
  initI18n(store.getState().preferences.locale);
  const router = createMemoryRouter([{ path, element: ui }, ...extraRoutes], { initialEntries: [route] });
  return { store, router, ...render(<Provider store={store}><RouterProvider router={router} /></Provider>) };
}

export const prefs = (over: Partial<RootState['preferences']> = {}): RootState['preferences'] => ({ locale: 'en', countryId: null, cityId: null, ...over });
