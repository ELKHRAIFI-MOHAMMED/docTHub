/**
 * The assessment in progress on this device, so the patient can resume it (answers themselves are saved on the
 * server after each question). Only the assessment id is stored, for 24 hours, and it is removed as soon as the
 * results are shown or the user logs out.
 */
const KEY = 'docthub.assessmentInProgress';
const TTL_MS = 24 * 60 * 60 * 1000;

export interface InProgress { id: string; savedAt: number }

export function saveInProgress(id: string): void {
  try {
    localStorage.setItem(KEY, JSON.stringify({ id, savedAt: Date.now() } satisfies InProgress));
  } catch {
    /* storage unavailable (private mode): resuming is simply not offered */
  }
}

export function getInProgress(): InProgress | null {
  try {
    const value = JSON.parse(localStorage.getItem(KEY) ?? 'null') as InProgress | null;
    if (!value?.id || Date.now() - value.savedAt > TTL_MS) {
      localStorage.removeItem(KEY);
      return null;
    }
    return value;
  } catch {
    return null;
  }
}

/** Without id: always clear; with id: only if it is the stored assessment. */
export function clearInProgress(id?: string): void {
  try {
    if (!id || getInProgress()?.id === id) localStorage.removeItem(KEY);
  } catch {
    /* ignore */
  }
}
