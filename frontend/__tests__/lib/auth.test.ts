import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';

// Mock dependencies
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

vi.mock('@/lib/timezones', () => ({
  detectBrowserTimezone: vi.fn(() => 'America/New_York'),
}));

vi.mock('@/lib/utils', () => ({
  setUserTimezone: vi.fn(),
  cn: vi.fn((...args: any[]) => args.filter(Boolean).join(' ')),
}));

describe('useAuth Hook', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.resetModules();
  });

  it('initializes with default state', async () => {
    const { useAuth } = await import('@/lib/auth');
    const { result } = renderHook(() => useAuth());

    expect(result.current.user).toBeNull();
    expect(result.current.isLoading).toBe(true);
    expect(result.current.error).toBeNull();
  });

  it('fetches user on mount', async () => {
    const { api } = await import('@/lib/api');
    const mockUser = { id: 1, name: 'Test User', email: 'test@example.com' };

    vi.mocked(api.get).mockResolvedValueOnce({ data: { user: mockUser } });

    const { useAuth } = await import('@/lib/auth');
    const { result } = renderHook(() => useAuth());

    await act(async () => {
      await result.current.fetchUser();
    });

    expect(api.get).toHaveBeenCalledWith('/auth/user');
  });

  it('handles login', async () => {
    const { api } = await import('@/lib/api');
    const mockUser = { id: 1, name: 'Test User', email: 'test@example.com' };

    // CSRF cookie get, then login post, then timezone post
    vi.mocked(api.get).mockResolvedValueOnce({});
    vi.mocked(api.post)
      .mockResolvedValueOnce({ data: { user: mockUser } })
      .mockResolvedValueOnce({});

    const { useAuth } = await import('@/lib/auth');
    const { result } = renderHook(() => useAuth());

    await act(async () => {
      await result.current.login('test@example.com', 'password');
    });

    expect(api.post).toHaveBeenCalledWith('/auth/login', {
      email: 'test@example.com',
      password: 'password',
      remember: false,
    });
  });

  it('handles logout', async () => {
    const { api } = await import('@/lib/api');

    vi.mocked(api.post).mockResolvedValueOnce({});

    const { useAuth } = await import('@/lib/auth');
    const { result } = renderHook(() => useAuth());

    await act(async () => {
      await result.current.logout();
    });

    expect(api.post).toHaveBeenCalledWith('/auth/logout');
    expect(result.current.user).toBeNull();
  });

  it('handles registration', async () => {
    const { api } = await import('@/lib/api');
    const mockUser = { id: 1, name: 'New User', email: 'new@example.com' };

    // CSRF cookie get, then register post, then timezone post
    vi.mocked(api.get).mockResolvedValueOnce({});
    vi.mocked(api.post)
      .mockResolvedValueOnce({ data: { user: mockUser } })
      .mockResolvedValueOnce({});

    const { useAuth } = await import('@/lib/auth');
    const { result } = renderHook(() => useAuth());

    await act(async () => {
      await result.current.register('New User', 'new@example.com', 'password', 'password');
    });

    expect(api.post).toHaveBeenCalledWith('/auth/register', {
      name: 'New User',
      email: 'new@example.com',
      password: 'password',
      password_confirmation: 'password',
    });
  });

  it('handles errors', async () => {
    const { api } = await import('@/lib/api');

    // CSRF cookie get succeeds, login post fails
    vi.mocked(api.get).mockResolvedValueOnce({});
    vi.mocked(api.post).mockRejectedValueOnce(
      new Error('Invalid credentials')
    );

    const { useAuth } = await import('@/lib/auth');
    const { result } = renderHook(() => useAuth());

    await act(async () => {
      await expect(
        result.current.login('test@example.com', 'wrongpassword')
      ).rejects.toThrow('Invalid credentials');
    });
  });

  it('handles login requiring 2FA', async () => {
    const { api } = await import('@/lib/api');

    vi.mocked(api.get).mockResolvedValueOnce({});
    vi.mocked(api.post).mockResolvedValueOnce({ data: { requires_2fa: true } });

    const { useAuth } = await import('@/lib/auth');
    const { result } = renderHook(() => useAuth());

    let loginResult: any;
    await act(async () => {
      loginResult = await result.current.login('test@example.com', 'password');
    });

    expect(loginResult).toEqual({ requires_2fa: true });
    expect(result.current.user).toBeNull();
  });

  it('handles verify2FA', async () => {
    const { api } = await import('@/lib/api');
    const mockUser = { id: 1, name: 'Test User', email: 'test@example.com' };

    vi.mocked(api.post)
      .mockResolvedValueOnce({ data: { user: mockUser } })
      .mockResolvedValueOnce({}); // timezone post

    const { useAuth } = await import('@/lib/auth');
    const { result } = renderHook(() => useAuth());

    await act(async () => {
      await result.current.verify2FA('123456');
    });

    expect(api.post).toHaveBeenCalledWith('/auth/2fa/verify', {
      code: '123456',
      remember: false,
      is_recovery_code: false,
    });
    expect(result.current.user).toEqual(expect.objectContaining({ id: 1 }));
  });

  it('handles verify2FA with recovery code', async () => {
    const { api } = await import('@/lib/api');
    const mockUser = { id: 1, name: 'Test User', email: 'test@example.com' };

    vi.mocked(api.post)
      .mockResolvedValueOnce({ data: { user: mockUser } })
      .mockResolvedValueOnce({});

    const { useAuth } = await import('@/lib/auth');
    const { result } = renderHook(() => useAuth());

    await act(async () => {
      await result.current.verify2FA('recovery-code', true, true);
    });

    expect(api.post).toHaveBeenCalledWith('/auth/2fa/verify', {
      code: 'recovery-code',
      remember: true,
      is_recovery_code: true,
    });
  });

  it('handles verify2FA failure', async () => {
    const { api } = await import('@/lib/api');

    vi.mocked(api.post).mockRejectedValueOnce(new Error('Invalid 2FA code'));

    const { useAuth } = await import('@/lib/auth');
    const { result } = renderHook(() => useAuth());

    await act(async () => {
      await expect(result.current.verify2FA('000000')).rejects.toThrow('Invalid 2FA code');
    });

    expect(result.current.user).toBeNull();
  });

  it('handles fetchUser failure gracefully', async () => {
    const { api } = await import('@/lib/api');

    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));

    const { useAuth } = await import('@/lib/auth');
    const { result } = renderHook(() => useAuth());

    await act(async () => {
      await result.current.fetchUser();
    });

    expect(result.current.user).toBeNull();
    expect(result.current.isLoading).toBe(false);
    expect(result.current.error).toBeNull();
  });
});
