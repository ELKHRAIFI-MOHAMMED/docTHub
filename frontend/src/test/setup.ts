import '@testing-library/jest-dom/vitest';
import { afterEach, vi } from 'vitest';
import { cleanup } from '@testing-library/react';
import { initI18n } from '../i18n';

initI18n('en');

// jsdom's AbortSignal is not accepted by Node's native Request (undici). RTK Query and React Router
// both build Request objects with a signal, so drop the signal in tests only (no aborts are tested).
const NativeRequest = globalThis.Request;
class TestRequest extends NativeRequest {
  constructor(input: RequestInfo | URL, init?: RequestInit) {
    if (init?.signal) {
      // eslint-disable-next-line @typescript-eslint/no-unused-vars
      const { signal, ...rest } = init;
      super(input, rest);
    } else {
      super(input, init);
    }
  }
}
globalThis.Request = TestRequest as typeof Request;

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
  localStorage.clear();
});
