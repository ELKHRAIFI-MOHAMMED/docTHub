import { useEffect } from 'react';
import { useAppDispatch, useAppSelector } from '../app/store';
import { useMeQuery } from '../features/auth/authApi';
import { userLoaded } from '../features/auth/authSlice';

/** Current session: restores the user from the stored token. */
export function useSession() {
  const dispatch = useAppDispatch();
  const token = useAppSelector((s) => s.auth.token);
  const user = useAppSelector((s) => s.auth.user);
  const { data, isLoading, isFetching } = useMeQuery(undefined, { skip: !token });

  useEffect(() => {
    if (data) dispatch(userLoaded(data));
  }, [data, dispatch]);

  const current = user ?? data ?? null;
  return {
    user: current,
    isAuthenticated: Boolean(token && current),
    isLoading: Boolean(token) && (isLoading || (isFetching && !current)),
    isAdmin: Boolean(current?.roles.includes('admin')),
    isPatient: Boolean(current?.roles.includes('patient')),
  };
}
