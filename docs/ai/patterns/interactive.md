# Interactive UI Patterns

Test connection buttons, file downloads, and typed confirmation dialogs.

## Test Connection Button

Track testing state per item to show loading spinner on the correct button.

```tsx
const [testingDestination, setTestingDestination] = useState<string | null>(null);

const handleTestDestination = async (destination: string) => {
  setTestingDestination(destination);
  try {
    await api.post(`/backup-settings/test/${destination}`);
    toast.success("Connection successful");
  } catch (err: unknown) {
    const msg = err instanceof Error ? err.message : "Connection failed";
    toast.error(msg);
  } finally {
    setTestingDestination(null);
  }
};

<Button variant="outline" onClick={() => handleTestDestination("s3")} disabled={!!testingDestination}>
  {testingDestination === "s3" ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
  Test Connection
</Button>
```

- Track which item is being tested (not just boolean) for correct spinner
- Disable all test buttons while any test is in progress
- Clear testing state in `finally` block

**Used in:** Backup settings, Storage settings, SSO settings, Notification channels, AI providers, Search.

## File Download (Blob)

```tsx
const handleDownload = async (filename: string) => {
  try {
    const response = await api.get(`/backup/download/${filename}`, { responseType: "blob" });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    link.parentNode?.removeChild(link);
    window.URL.revokeObjectURL(url);
  } catch (error: any) {
    toast.error(error.message || "Failed to download");
  }
};
```

- Use `responseType: "blob"` in axios request
- Create object URL, trigger download via hidden link, clean up

**Used in:** Backup downloads, Log exports, Access log exports, File manager.

## Typed Confirmation Dialog

For dangerous operations, require user to type a specific word:

```tsx
const [restoreConfirmation, setRestoreConfirmation] = useState("");

<Dialog>
  <DialogContent>
    <DialogHeader>
      <DialogTitle>Restore Backup</DialogTitle>
      <DialogDescription>
        This will overwrite all current data. Type <strong>RESTORE</strong> to confirm.
      </DialogDescription>
    </DialogHeader>
    <Input
      value={restoreConfirmation}
      onChange={(e) => setRestoreConfirmation(e.target.value)}
      placeholder="Type RESTORE"
    />
    <DialogFooter>
      <Button
        variant="destructive"
        onClick={handleRestore}
        disabled={isRestoring || restoreConfirmation !== "RESTORE"}
      >
        Restore
      </Button>
    </DialogFooter>
  </DialogContent>
</Dialog>
```

- Button stays disabled until exact match (case-sensitive)
- Clear confirmation text when dialog closes
- Use for: backup restore, data deletion, account deletion

**Related:** [API Calls](api-calls.md), [Error Handling](error-handling.md)
