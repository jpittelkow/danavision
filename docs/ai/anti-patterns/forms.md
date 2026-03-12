# Form Validation Anti-Patterns (react-hook-form + Zod)

### Don't: Make Fields Required by Default

```tsx
// BAD - URL validation fails on empty string (blocks save)
const schema = z.object({
  webhook_url: z.string().url().optional(),  // Empty string fails URL validation!
  api_key: z.string().min(1),  // Requires value even if optional
});

// GOOD - Allow empty strings explicitly for optional fields
const schema = z.object({
  webhook_url: z.string()
    .refine((val) => !val || val === "" || isValidUrl(val), {
      message: "Must be a valid URL",
    })
    .optional(),
  api_key: z.string().optional(),  // Empty is fine
});
```

### Don't: Use onChange Mode for Form Validation

```tsx
// BAD - Validates on every keystroke (blocks typing, shows errors too early)
const form = useForm({
  resolver: zodResolver(schema),
  mode: "onChange",
});

// GOOD - Validates when user leaves field
const form = useForm({
  resolver: zodResolver(schema),
  mode: "onBlur",
});
```

### Don't: Use setValue for Initial Data Load

```tsx
// BAD - isDirty won't work correctly
const fetchSettings = async () => {
  const response = await api.get("/settings");
  setValue("name", response.data.name);
  setValue("enabled", response.data.enabled);
  // Form thinks these are user changes, isDirty = true immediately!
};

// GOOD - Use reset() to establish clean state
const fetchSettings = async () => {
  const response = await api.get("/settings");
  reset({
    name: response.data.name || "",
    enabled: response.data.enabled || false,
  });
  // Form knows this is the baseline, isDirty = false
};
```

### Don't: Forget shouldDirty for Custom Inputs

```tsx
// BAD - Switch/ColorPicker changes don't enable save button
<Switch
  checked={watch("enabled")}
  onCheckedChange={(checked) => setValue("enabled", checked)}
/>

// GOOD - Mark the form as dirty when value changes
<Switch
  checked={watch("enabled")}
  onCheckedChange={(checked) => setValue("enabled", checked, { shouldDirty: true })}
/>
```

### Don't: Send Empty Strings to Backend

```tsx
// BAD - Backend may store empty string instead of null
const onSubmit = async (data) => {
  await api.put("/settings", data);  // { webhook_url: "" }
};

// GOOD - Convert empty strings to null
const onSubmit = async (data) => {
  await api.put("/settings", {
    ...data,
    webhook_url: data.webhook_url || null,
    api_key: data.api_key || null,
  });
};
```
