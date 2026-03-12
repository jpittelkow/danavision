/**
 * Service worker registration utility for PWA support.
 * Registers the service worker and handles update notifications.
 */

export type ServiceWorkerUpdateCallback = (registration: ServiceWorkerRegistration) => void;
export type ServiceWorkerActivatedCallback = () => void;

/**
 * Check if the browser supports service workers.
 */
export function isServiceWorkerSupported(): boolean {
  return typeof navigator !== 'undefined' && 'serviceWorker' in navigator;
}

export type ServiceWorkerRegistrationResult = {
  registration: ServiceWorkerRegistration;
  unregister: () => void;
};

/**
 * Register the service worker and optionally handle updates.
 * @param onUpdate - Callback when a new service worker is waiting to activate
 * @param onActivated - Callback when a new service worker has taken control (e.g. after skipWaiting)
 * @returns Promise that resolves to the registration result or undefined if not supported. Call unregister() on cleanup.
 */
export async function registerServiceWorker(
  onUpdate?: ServiceWorkerUpdateCallback,
  onActivated?: ServiceWorkerActivatedCallback
): Promise<ServiceWorkerRegistrationResult | undefined> {
  if (!isServiceWorkerSupported()) {
    return undefined;
  }

  try {
    const registration = await navigator.serviceWorker.register('/sw.js', {
      scope: '/',
    });

    const controllerChangeHandler = () => onActivated?.();
    navigator.serviceWorker.addEventListener('controllerchange', controllerChangeHandler);

    // Handle updates when a new service worker is waiting
    registration.addEventListener('updatefound', () => {
      const newWorker = registration.installing;
      if (!newWorker) return;

      newWorker.addEventListener('statechange', () => {
        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
          onUpdate?.(registration);
        }
      });
    });

    // Check for existing waiting worker (e.g. user had tab open during deploy)
    if (registration.waiting && navigator.serviceWorker.controller) {
      onUpdate?.(registration);
    }

    return {
      registration,
      unregister: () => {
        navigator.serviceWorker.removeEventListener('controllerchange', controllerChangeHandler);
      },
    };
  } catch (error: unknown) {
    const { errorLogger } = await import('@/lib/error-logger');
    errorLogger.report(
      error instanceof Error ? error : new Error(String(error)),
      { component: 'ServiceWorkerRegistration' }
    );
    return undefined;
  }
}

/**
 * Tell the waiting service worker to take control immediately.
 * Call this when the user opts to refresh for an update.
 */
export async function activateWaitingServiceWorker(): Promise<void> {
  const registration = await navigator.serviceWorker.ready;
  if (registration.waiting) {
    registration.waiting.postMessage({ type: 'SKIP_WAITING' });
  }
}
