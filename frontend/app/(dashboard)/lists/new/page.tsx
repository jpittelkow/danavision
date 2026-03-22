"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import { usePageTitle } from "@/lib/use-page-title";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Switch } from "@/components/ui/switch";
import { Separator } from "@/components/ui/separator";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { getErrorMessage } from "@/lib/utils";
import { createList } from "@/lib/api/shopping";

const createListSchema = z.object({
  name: z.string().min(1, "List name is required"),
  description: z.string().optional(),
  notify_on_any_drop: z.boolean(),
  notify_on_threshold: z.boolean(),
  threshold_percent: z.number().min(1).max(100).optional(),
  shop_local: z.boolean(),
});

type CreateListForm = z.infer<typeof createListSchema>;

export default function NewListPage() {
  usePageTitle("New Shopping List");
  const router = useRouter();
  const [isSubmitting, setIsSubmitting] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
    watch,
    setValue,
  } = useForm<CreateListForm>({
    resolver: zodResolver(createListSchema),
    mode: "onBlur",
    defaultValues: {
      name: "",
      description: "",
      notify_on_any_drop: true,
      notify_on_threshold: false,
      threshold_percent: 10,
      shop_local: false,
    },
  });

  const notifyOnThreshold = watch("notify_on_threshold");

  async function onSubmit(data: CreateListForm) {
    setIsSubmitting(true);
    try {
      const response = await createList({
        name: data.name,
        description: data.description || undefined,
        notify_on_any_drop: data.notify_on_any_drop,
        notify_on_threshold: data.notify_on_threshold,
        threshold_percent: data.notify_on_threshold
          ? data.threshold_percent
          : undefined,
        shop_local: data.shop_local,
      });
      const newList = response.data?.data;
      toast.success("Shopping list created");
      router.push(`/lists/${newList?.id ?? ""}`);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to create list"));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="space-y-6 max-w-2xl">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">
          Create Shopping List
        </h1>
        <p className="text-muted-foreground mt-1">
          Set up a new list to track product prices.
        </p>
      </div>

      <form onSubmit={handleSubmit(onSubmit)}>
        <Card>
          <CardHeader>
            <CardTitle>List Details</CardTitle>
            <CardDescription>
              Give your list a name and configure notifications.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            {/* Name */}
            <div className="space-y-2">
              <Label htmlFor="name">Name</Label>
              <Input
                id="name"
                {...register("name")}
                placeholder="e.g. Weekly Groceries"
              />
              {errors.name && (
                <p className="text-sm text-destructive">
                  {errors.name.message}
                </p>
              )}
            </div>

            {/* Description */}
            <div className="space-y-2">
              <Label htmlFor="description">Description (optional)</Label>
              <Textarea
                id="description"
                {...register("description")}
                placeholder="What is this list for?"
                rows={3}
              />
            </div>

            <Separator />

            {/* Notification Settings */}
            <div className="space-y-4">
              <h3 className="text-sm font-medium">Notifications</h3>

              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label>Notify on any price drop</Label>
                  <p className="text-sm text-muted-foreground">
                    Get notified whenever a price drops
                  </p>
                </div>
                <Switch
                  checked={watch("notify_on_any_drop")}
                  onCheckedChange={(checked) =>
                    setValue("notify_on_any_drop", checked, {
                      shouldDirty: true,
                    })
                  }
                />
              </div>

              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label>Notify on threshold</Label>
                  <p className="text-sm text-muted-foreground">
                    Only notify when price drops by a certain percentage
                  </p>
                </div>
                <Switch
                  checked={notifyOnThreshold}
                  onCheckedChange={(checked) =>
                    setValue("notify_on_threshold", checked, {
                      shouldDirty: true,
                    })
                  }
                />
              </div>

              {notifyOnThreshold && (
                <div className="space-y-2 ml-4">
                  <Label htmlFor="threshold_percent">
                    Threshold percentage (%)
                  </Label>
                  <Input
                    id="threshold_percent"
                    type="number"
                    {...register("threshold_percent", { valueAsNumber: true })}
                    min={1}
                    max={100}
                    className="max-w-32"
                  />
                  {errors.threshold_percent && (
                    <p className="text-sm text-destructive">
                      {errors.threshold_percent.message}
                    </p>
                  )}
                </div>
              )}
            </div>

            <Separator />

            {/* Shop Local */}
            <div className="flex items-center justify-between">
              <div className="space-y-0.5">
                <Label>Prefer local shops</Label>
                <p className="text-sm text-muted-foreground">
                  Prioritize local retailers when searching for prices
                </p>
              </div>
              <Switch
                checked={watch("shop_local")}
                onCheckedChange={(checked) =>
                  setValue("shop_local", checked, { shouldDirty: true })
                }
              />
            </div>
          </CardContent>
          <CardFooter className="flex justify-between">
            <Button
              type="button"
              variant="outline"
              onClick={() => router.back()}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting ? "Creating..." : "Create List"}
            </Button>
          </CardFooter>
        </Card>
      </form>
    </div>
  );
}
