"use client";

import { useCallback } from "react";
import { toast } from "sonner";
import { Copy, ExternalLink } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";

export type SSOSetupProviderId =
  | "google"
  | "github"
  | "microsoft"
  | "apple"
  | "discord"
  | "gitlab"
  | "oidc";

interface ProviderSetupContent {
  title: string;
  consoleUrl: string;
  docUrl?: string;
  steps: string[];
  scopes: string[];
  notes?: string;
}

const PROVIDER_CONTENT: Record<SSOSetupProviderId, ProviderSetupContent> = {
  google: {
    title: "Google",
    consoleUrl: "https://console.cloud.google.com/apis/credentials",
    docUrl: "https://developers.google.com/identity/protocols/oauth2",
    steps: [
      "Open Google Cloud Console and create or select a project.",
      "Configure the OAuth consent screen (User type, Scopes).",
      "Go to Credentials and create an OAuth 2.0 Client ID (Web application).",
      "Add the redirect URI below to Authorized redirect URIs.",
      "Copy the Client ID and Client Secret into the SSO settings.",
    ],
    scopes: ["email", "profile", "openid"],
    notes: "Use application type: Web application.",
  },
  github: {
    title: "GitHub",
    consoleUrl: "https://github.com/settings/developers",
    docUrl: "https://docs.github.com/en/apps/oauth-apps/building-oauth-apps/creating-an-oauth-app",
    steps: [
      "Go to GitHub Settings → Developer settings → OAuth Apps.",
      "Click New OAuth App.",
      "Set Application name and Homepage URL.",
      "Set Authorization callback URL to the redirect URI below.",
      "Register the app and copy Client ID and Client Secret.",
    ],
    scopes: ["user:email"],
  },
  microsoft: {
    title: "Microsoft (Azure AD / Entra ID)",
    consoleUrl: "https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade",
    docUrl: "https://learn.microsoft.com/en-us/entra/identity-platform/quickstart-register-app",
    steps: [
      "In Azure Portal go to App registrations → New registration.",
      "Set name and supported account types.",
      "Under Authentication, add a Web platform and set Redirect URI to the value below.",
      "Under Certificates & secrets, create a new client secret.",
      "Under API permissions, add OpenID (openid, profile, email).",
      "Copy Application (client) ID and client secret value.",
    ],
    scopes: ["openid", "profile", "email"],
  },
  apple: {
    title: "Apple",
    consoleUrl: "https://developer.apple.com/account/resources/identifiers",
    docUrl: "https://developer.apple.com/sign-in-with-apple/get-started/",
    steps: [
      "Requires Apple Developer Program membership.",
      "Create an App ID with Sign in with Apple capability.",
      "Create a Services ID for web sign-in; set Domains and Return URLs (use redirect URI below).",
      "Create a Key for Sign in with Apple; configure with your App ID and Services ID.",
      "Download the key and generate client secret (key, key ID, team ID, client ID, expiry).",
      "Use Services ID as Client ID and the generated JWT as Client Secret.",
    ],
    scopes: ["email", "name"],
    notes: "Apple setup is more complex; see Apple documentation for full flow.",
  },
  discord: {
    title: "Discord",
    consoleUrl: "https://discord.com/developers/applications",
    docUrl: "https://discord.com/developers/docs/topics/oauth2",
    steps: [
      "Go to Discord Developer Portal → Applications → New Application.",
      "Under OAuth2 → Redirects, add the redirect URI below.",
      "Copy Client ID and Client Secret from OAuth2 → General.",
    ],
    scopes: ["identify", "email"],
  },
  gitlab: {
    title: "GitLab",
    consoleUrl: "https://gitlab.com/-/profile/applications",
    docUrl: "https://docs.gitlab.com/ee/integration/oauth_provider.html",
    steps: [
      "Go to GitLab → User Settings → Applications (or use your self-hosted GitLab URL).",
      "Add a new application: set name, redirect URI to the value below.",
      "Select scopes: read_user, openid, profile, email.",
      "Save and copy Application ID and Secret.",
    ],
    scopes: ["read_user", "openid", "profile", "email"],
    notes: "For self-hosted GitLab, use your instance URL and /-/profile/applications.",
  },
  oidc: {
    title: "Enterprise SSO (OIDC)",
    consoleUrl: "https://developer.okta.com/docs/guides/implement-oauth-for-okta/",
    docUrl: "https://openid.net/connect/",
    steps: [
      "In your IdP (Okta, Auth0, Keycloak, etc.) create a new OAuth 2.0 / OIDC application.",
      "Set application type to Web application.",
      "Add the redirect URI below to allowed redirect URIs.",
      "Note the Issuer URL (e.g. https://your-domain.okta.com/oauth2/default).",
      "Copy Client ID and Client Secret; set Issuer URL and optional Provider name in SSO settings.",
    ],
    scopes: ["openid", "profile", "email"],
    notes: "Issuer URL is typically the discovery endpoint base (e.g. https://your-tenant.auth0.com/).",
  },
};

interface SSOSetupModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  provider: SSOSetupProviderId;
  redirectUri: string;
}

export function SSOSetupModal({
  open,
  onOpenChange,
  provider,
  redirectUri,
}: SSOSetupModalProps) {
  const content = PROVIDER_CONTENT[provider];
  const handleCopy = useCallback(() => {
    void navigator.clipboard.writeText(redirectUri).then(() => {
      toast.success("Redirect URI copied to clipboard");
    });
  }, [redirectUri]);

  if (!content) return null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Set up {content.title} SSO</DialogTitle>
          <DialogDescription>
            Follow these steps in your {content.title} developer console to configure OAuth.
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-4 text-sm">
          <div>
            <p className="mb-1 font-medium text-foreground">Redirect URI (callback URL)</p>
            <p className="text-muted-foreground mb-2 text-xs">
              Add this exact URL in your provider&apos;s application settings.
            </p>
            {redirectUri ? (
              <div className="flex items-center gap-2 rounded-md border bg-muted/50 px-3 py-2 font-mono text-xs break-all">
                <span className="flex-1">{redirectUri}</span>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8 shrink-0"
                  onClick={handleCopy}
                  aria-label="Copy redirect URI"
                >
                  <Copy className="h-4 w-4" aria-hidden />
                </Button>
              </div>
            ) : (
              <p className="text-muted-foreground rounded-md border border-dashed bg-muted/30 px-3 py-2 text-xs italic">
                Set Application URL in Configuration → System to see the redirect URI here.
              </p>
            )}
          </div>
          <div>
            <p className="mb-2 font-medium text-foreground">Steps</p>
            <ol className="list-decimal space-y-1.5 pl-4 text-muted-foreground">
              {content.steps.map((step, i) => (
                <li key={i}>{step}</li>
              ))}
            </ol>
          </div>
          {content.scopes.length > 0 && (
            <div>
              <p className="mb-1 font-medium text-foreground">Scopes</p>
              <p className="text-muted-foreground text-xs">
                {content.scopes.join(", ")}
              </p>
            </div>
          )}
          {content.notes && (
            <p className="text-muted-foreground text-xs italic">{content.notes}</p>
          )}
          <div className="flex flex-wrap gap-2 pt-2">
            <Button variant="outline" size="sm" className="gap-1.5" asChild>
              <a
                href={content.consoleUrl}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={`Open ${content.title} developer console`}
              >
                Open {content.title} console
                <ExternalLink className="h-3.5 w-3.5" aria-hidden />
              </a>
            </Button>
            {content.docUrl && (
              <Button variant="ghost" size="sm" className="gap-1.5" asChild>
                <a
                  href={content.docUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label="Open documentation"
                >
                  Documentation
                  <ExternalLink className="h-3.5 w-3.5" aria-hidden />
                </a>
              </Button>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
