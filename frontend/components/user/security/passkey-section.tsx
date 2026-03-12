"use client";

import { useState } from "react";
import { toast } from "sonner";
import { useAppConfig } from "@/lib/app-config";
import { usePasskeys } from "@/lib/use-passkeys";
import { PasskeyRegisterDialog } from "@/components/auth/passkey-register-dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Skeleton } from "@/components/ui/skeleton";
import { Loader2, Fingerprint, Trash2, Pencil, AlertTriangle } from "lucide-react";
import { HelpLink } from "@/components/help/help-link";

export function PasskeySection() {
  const { features } = useAppConfig();
  const passkeyMode = features?.passkeyMode ?? "disabled";
  const passkeysEnabled = passkeyMode !== "disabled";
  const {
    passkeys,
    loading: passkeysLoading,
    supported: passkeySupported,
    registerPasskey,
    deletePasskey,
    renamePasskey,
    fetchPasskeys,
  } = usePasskeys();

  const [showPasskeyRegisterDialog, setShowPasskeyRegisterDialog] = useState(false);
  const [deletePasskeyTarget, setDeletePasskeyTarget] = useState<{ id: string; alias: string } | null>(null);
  const [isDeletingPasskey, setIsDeletingPasskey] = useState(false);
  const [editingPasskeyId, setEditingPasskeyId] = useState<string | null>(null);
  const [editingPasskeyName, setEditingPasskeyName] = useState("");
  const [isRenamingPasskey, setIsRenamingPasskey] = useState(false);

  const handleRenamePasskey = async (id: string) => {
    const trimmed = editingPasskeyName.trim();
    if (!trimmed) return;
    setIsRenamingPasskey(true);
    const ok = await renamePasskey(id, trimmed);
    if (ok) {
      toast.success("Passkey renamed");
      setEditingPasskeyId(null);
    } else {
      toast.error("Failed to rename passkey");
    }
    setIsRenamingPasskey(false);
  };

  if (!passkeysEnabled) return null;

  return (
    <>
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Fingerprint className="h-5 w-5" />
            Passkeys
          </CardTitle>
          <CardDescription>
            Sign in with your fingerprint, face, or hardware security key.{" "}
            <HelpLink articleId="passkeys" />
          </CardDescription>
        </CardHeader>
        <CardContent aria-busy={passkeysLoading}>
          {!passkeySupported ? (
            <Alert>
              <AlertTriangle className="h-4 w-4" />
              <AlertTitle>Not supported</AlertTitle>
              <AlertDescription>
                Passkeys are not supported in this browser. Use a modern browser
                (Chrome, Safari, Edge, Firefox) with WebAuthn support.
              </AlertDescription>
            </Alert>
          ) : passkeysLoading ? (
            <div className="space-y-3">
              {[1, 2].map((i) => (
                <Skeleton key={i} className="h-12 w-full" />
              ))}
            </div>
          ) : (
            <div className="space-y-4">
              {passkeys.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  No passkeys registered. Add one to sign in with your device.
                </p>
              ) : (
                <ul className="space-y-2" aria-live="polite">
                  {passkeys.map((pk) => (
                    <li
                      key={pk.id}
                      className="flex items-center justify-between py-2 border-b border-border last:border-0"
                    >
                      <div className="min-w-0 flex-1">
                        {editingPasskeyId === pk.id ? (
                          <div className="flex items-center gap-2">
                            <Input
                              value={editingPasskeyName}
                              onChange={(e) => setEditingPasskeyName(e.target.value)}
                              maxLength={255}
                              className="h-8 text-sm"
                              autoFocus
                              onKeyDown={(e) => {
                                if (e.key === "Enter") {
                                  e.preventDefault();
                                  handleRenamePasskey(pk.id);
                                }
                                if (e.key === "Escape") setEditingPasskeyId(null);
                              }}
                              disabled={isRenamingPasskey}
                            />
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={isRenamingPasskey || !editingPasskeyName.trim()}
                              onClick={() => handleRenamePasskey(pk.id)}
                            >
                              {isRenamingPasskey ? <Loader2 className="h-3 w-3 animate-spin" /> : "Save"}
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => setEditingPasskeyId(null)}
                              disabled={isRenamingPasskey}
                            >
                              Cancel
                            </Button>
                          </div>
                        ) : (
                          <>
                            <p className="font-medium">{pk.alias}</p>
                            <p className="text-xs text-muted-foreground">
                              Added {pk.created_at ? new Date(pk.created_at).toLocaleDateString() : "unknown"}
                            </p>
                          </>
                        )}
                      </div>
                      {editingPasskeyId !== pk.id && (
                        <div className="flex items-center gap-1">
                          <Button
                            variant="ghost"
                            size="icon"
                            aria-label={`Rename passkey ${pk.alias}`}
                            onClick={() => {
                              setEditingPasskeyId(pk.id);
                              setEditingPasskeyName(pk.alias);
                            }}
                          >
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            className="text-destructive hover:text-destructive"
                            aria-label={`Remove passkey ${pk.alias}`}
                            onClick={() => setDeletePasskeyTarget({ id: pk.id, alias: pk.alias })}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </CardContent>
        {passkeySupported && (
          <CardFooter>
            <Button onClick={() => setShowPasskeyRegisterDialog(true)}>
              <Fingerprint className="mr-2 h-4 w-4" />
              Add Passkey
            </Button>
          </CardFooter>
        )}
      </Card>

      <PasskeyRegisterDialog
        open={showPasskeyRegisterDialog}
        onOpenChange={setShowPasskeyRegisterDialog}
        onSuccess={fetchPasskeys}
        registerPasskey={registerPasskey}
      />

      {/* Delete Passkey Confirmation */}
      <AlertDialog
        open={!!deletePasskeyTarget}
        onOpenChange={(open) => { if (!open) setDeletePasskeyTarget(null); }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remove Passkey</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to remove <strong>{deletePasskeyTarget?.alias}</strong>?
              You will no longer be able to sign in with this passkey.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={isDeletingPasskey}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={async () => {
                if (!deletePasskeyTarget) return;
                setIsDeletingPasskey(true);
                const ok = await deletePasskey(deletePasskeyTarget.id);
                if (ok) {
                  toast.success("Passkey removed");
                  setDeletePasskeyTarget(null);
                } else {
                  toast.error("Failed to remove passkey");
                }
                setIsDeletingPasskey(false);
              }}
              disabled={isDeletingPasskey}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              {isDeletingPasskey && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Remove
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
