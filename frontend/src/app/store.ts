import { combineReducers, configureStore, isRejectedWithValue, type Middleware } from '@reduxjs/toolkit';
import { useDispatch, useSelector } from 'react-redux';
import { api } from './api';
import authReducer, { loggedOut } from '../features/auth/authSlice';
import preferencesReducer, { savePreferences } from '../features/preferences/preferencesSlice';

const rootReducer = combineReducers({
  [api.reducerPath]: api.reducer,
  auth: authReducer,
  preferences: preferencesReducer,
});

/** A 401 on any request means the token is no longer valid: clear the session. */
const sessionExpiry: Middleware = (store) => (next) => (action) => {
  if (isRejectedWithValue(action) && (action.payload as { status?: number })?.status === 401) {
    if ((store.getState() as RootState).auth.token) store.dispatch(loggedOut());
  }
  return next(action);
};

export function makeStore(preloadedState?: Partial<RootState>) {
  const store = configureStore({
    reducer: rootReducer,
    preloadedState: preloadedState as RootState | undefined,
    middleware: (getDefault) => getDefault().concat(api.middleware, sessionExpiry),
  });
  store.subscribe(() => savePreferences(store.getState().preferences));
  return store;
}

export const store = makeStore();

export type RootState = ReturnType<typeof rootReducer>;
export type AppStore = ReturnType<typeof makeStore>;
export type AppDispatch = AppStore['dispatch'];
export const useAppDispatch = useDispatch.withTypes<AppDispatch>();
export const useAppSelector = useSelector.withTypes<RootState>();
