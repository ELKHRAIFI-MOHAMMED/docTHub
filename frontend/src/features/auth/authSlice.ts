import { createSlice, type PayloadAction } from '@reduxjs/toolkit';
import type { User } from '../../types/api';

const TOKEN_KEY = 'docthub.token';

export interface AuthState {
  token: string | null;
  user: User | null;
}

function readToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}

const authSlice = createSlice({
  name: 'auth',
  initialState: (): AuthState => ({ token: readToken(), user: null }),
  reducers: {
    credentialsReceived(state, action: PayloadAction<{ token: string; user: User }>) {
      state.token = action.payload.token;
      state.user = action.payload.user;
      localStorage.setItem(TOKEN_KEY, action.payload.token);
    },
    userLoaded(state, action: PayloadAction<User>) {
      state.user = action.payload;
    },
    loggedOut(state) {
      state.token = null;
      state.user = null;
      localStorage.removeItem(TOKEN_KEY);
    },
  },
});

export const { credentialsReceived, userLoaded, loggedOut } = authSlice.actions;
export default authSlice.reducer;
