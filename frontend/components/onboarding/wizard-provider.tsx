"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from "react";
import { api } from "@/lib/api";
import { WizardModal } from "@/components/onboarding/wizard-modal";
import { useAuth } from "@/lib/auth";

interface OnboardingStatus {
  wizard_completed: boolean;
  wizard_dismissed: boolean;
  wizard_completed_at: string | null;
  wizard_dismissed_at: string | null;
  steps_completed: string[];
  show_wizard: boolean;
}

interface WizardContextValue {
  showWizard: boolean;
  setShowWizard: (show: boolean) => void;
  currentStep: number;
  setCurrentStep: (step: number) => void;
  stepsCompleted: string[];
  completeStep: (step: string) => Promise<void>;
  completeWizard: () => Promise<void>;
  dismissWizard: () => Promise<void>;
  resetWizard: () => Promise<void>;
  isLoading: boolean;
}

const WizardContext = createContext<WizardContextValue | null>(null);

export function useWizard() {
  const ctx = useContext(WizardContext);
  if (!ctx) {
    throw new Error("useWizard must be used within WizardProvider");
  }
  return ctx;
}

interface WizardProviderProps {
  children: ReactNode;
}

export function WizardProvider({ children }: WizardProviderProps) {
  const { user } = useAuth();
  const [showWizard, setShowWizard] = useState(false);
  const [currentStep, setCurrentStep] = useState(0);
  const [stepsCompleted, setStepsCompleted] = useState<string[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [initialized, setInitialized] = useState(false);

  // Fetch onboarding status when user is available
  useEffect(() => {
    if (!user || initialized) return;

    const fetchStatus = async () => {
      try {
        const response = await api.get<OnboardingStatus>("/onboarding/status");
        const data = response.data;
        setStepsCompleted(data.steps_completed || []);
        setShowWizard(data.show_wizard);
        setInitialized(true);
      } catch (error: unknown) {
        // If fetch fails, don't show wizard
        setShowWizard(false);
        setInitialized(true);
      } finally {
        setIsLoading(false);
      }
    };

    fetchStatus();
  }, [user, initialized]);

  // Reset when user logs out
  useEffect(() => {
    if (!user) {
      setShowWizard(false);
      setCurrentStep(0);
      setStepsCompleted([]);
      setInitialized(false);
      setIsLoading(true);
    }
  }, [user]);

  const completeStep = useCallback(async (step: string) => {
    try {
      const response = await api.post<{ steps_completed: string[] }>(
        "/onboarding/wizard/step",
        { step }
      );
      setStepsCompleted(response.data.steps_completed);
    } catch (error: unknown) {
      // Optimistically update locally even if API fails
      setStepsCompleted((prev) =>
        prev.includes(step) ? prev : [...prev, step]
      );
    }
  }, []);

  const completeWizard = useCallback(async () => {
    try {
      await api.post("/onboarding/wizard/complete");
    } catch (error: unknown) {
      // Ignore API errors
    }
    setShowWizard(false);
    setCurrentStep(0);
  }, []);

  const dismissWizard = useCallback(async () => {
    try {
      await api.post("/onboarding/wizard/dismiss");
    } catch (error: unknown) {
      // Ignore API errors
    }
    setShowWizard(false);
    setCurrentStep(0);
  }, []);

  const resetWizard = useCallback(async () => {
    try {
      await api.post("/onboarding/wizard/reset");
      setStepsCompleted([]);
      setCurrentStep(0);
      setShowWizard(true);
    } catch (error: unknown) {
      // Ignore API errors
    }
  }, []);

  return (
    <WizardContext.Provider
      value={{
        showWizard,
        setShowWizard,
        currentStep,
        setCurrentStep,
        stepsCompleted,
        completeStep,
        completeWizard,
        dismissWizard,
        resetWizard,
        isLoading,
      }}
    >
      {children}
      <WizardModal />
    </WizardContext.Provider>
  );
}
