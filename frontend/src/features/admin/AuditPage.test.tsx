import { fireEvent, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { mockApi, renderWithProviders } from '../../test/utils';
import AuditPage from './AuditPage';

const page = (data: unknown[]) => ({ data, current_page: 1, last_page: 1, total: data.length });

describe('AuditPage', () => {
  it('shows who changed what, when, with old and new values', async () => {
    mockApi({
      'GET /admin/audit-logs': page([
        {
          id: 2, action: 'admin.updated', auditable_type: 'disease', auditable_id: 12, created_at: '2026-11-21T10:00:00Z',
          user: { name: 'Admin', email: 'admin@docthub.test' }, user_agent: 'Mozilla',
          old_values: { name: 'Old name' }, new_values: { name: 'New name' },
        },
        {
          id: 1, action: 'knowledge.import.created', auditable_type: 'disease', auditable_id: 13, created_at: '2026-11-21T09:00:00Z',
          user: null, user_agent: 'artisan knowledge:import', old_values: null, new_values: { name: 'Imported' },
        },
      ]),
    });
    renderWithProviders(<AuditPage />);

    expect(await screen.findByText('Admin (admin@docthub.test)')).toBeInTheDocument();
    expect(screen.getByText('artisan knowledge:import')).toBeInTheDocument();
    expect(screen.getByText('disease #12')).toBeInTheDocument();

    fireEvent.click(screen.getAllByText('1 change')[0]);
    expect(screen.getByText('Old name')).toBeInTheDocument();
    expect(screen.getByText('New name')).toBeInTheDocument();
  });

  it('offers to retry when the journal cannot be loaded', async () => {
    mockApi({ 'GET /admin/audit-logs': { __status: 500, message: 'Server error' } });
    renderWithProviders(<AuditPage />);

    expect(await screen.findByRole('button', { name: 'Retry' })).toBeInTheDocument();
  });
});
