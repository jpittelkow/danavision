import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { toast } from 'sonner';

interface Flash {
  success?: string;
  error?: string;
  message?: string;
  warning?: string;
  info?: string;
}

interface PageProps {
  flash?: Flash;
  [key: string]: unknown;
}

/**
 * Hook that automatically displays toast notifications for Inertia flash messages.
 * Place this in layouts or pages where you want flash messages to appear as toasts.
 */
export function useFlashToast() {
  const { flash } = usePage<PageProps>().props;
  const shownRef = useRef<Set<string>>(new Set());

  useEffect(() => {
    if (!flash) return;

    // Create a unique key for this set of flash messages
    const flashKey = JSON.stringify(flash);

    // Avoid showing the same flash message twice
    if (shownRef.current.has(flashKey)) return;
    shownRef.current.add(flashKey);

    // Show appropriate toast for each flash type
    if (flash.success) {
      toast.success(flash.success);
    }

    if (flash.error) {
      toast.error(flash.error);
    }

    if (flash.warning) {
      toast.warning(flash.warning);
    }

    if (flash.info) {
      toast.info(flash.info);
    }

    if (flash.message) {
      toast(flash.message);
    }

    // Clean up old keys to prevent memory leak
    if (shownRef.current.size > 10) {
      const keys = Array.from(shownRef.current);
      keys.slice(0, 5).forEach((key) => shownRef.current.delete(key));
    }
  }, [flash]);
}
