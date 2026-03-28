"use client";

import { useState, useEffect, useRef, useCallback } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import { useAuth, isAdminUser } from "@/lib/auth";
import { api } from "@/lib/api";
import { searchAddress, geocodePlace } from "@/lib/api/shopping";
import { getErrorMessage } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { AvatarUpload } from "@/components/user/avatar-upload";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { AlertTriangle, Loader2, MapPin, Navigation, UsersRound } from "lucide-react";
import Link from "next/link";
import { HelpLink } from "@/components/help/help-link";
import { SaveButton } from "@/components/ui/save-button";
import { Badge } from "@/components/ui/badge";
import { useOnline } from "@/lib/use-online";

const profileSchema = z.object({
  name: z.string().min(2, "Name must be at least 2 characters"),
  email: z.string().email("Invalid email address"),
});

type ProfileForm = z.infer<typeof profileSchema>;

interface LocationPreferences {
  home_address: string | null;
  home_zip_code: string | null;
  home_latitude: number | null;
  home_longitude: number | null;
}

export default function ProfilePage() {
  const { user, fetchUser } = useAuth();
  const [isLoading, setIsLoading] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [deleteConfirmation, setDeleteConfirmation] = useState("");
  const isOffline = !useOnline();
  const [location, setLocation] = useState<LocationPreferences>({
    home_address: null,
    home_zip_code: null,
    home_latitude: null,
    home_longitude: null,
  });
  const [isLocationSaving, setIsLocationSaving] = useState(false);
  const [isDetecting, setIsDetecting] = useState(false);
  const [addressQuery, setAddressQuery] = useState("");
  const [addressSuggestions, setAddressSuggestions] = useState<Array<{ place_id: string; description: string }>>([]);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [isSearching, setIsSearching] = useState(false);
  const [isGeocoding, setIsGeocoding] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const suggestionsRef = useRef<HTMLDivElement>(null);

  // Close suggestions on outside click
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (suggestionsRef.current && !suggestionsRef.current.contains(e.target as Node)) {
        setShowSuggestions(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleAddressInput = useCallback((value: string) => {
    setAddressQuery(value);
    setLocation((prev) => ({ ...prev, home_address: value }));

    if (debounceRef.current) clearTimeout(debounceRef.current);

    if (value.length < 3) {
      setAddressSuggestions([]);
      setShowSuggestions(false);
      return;
    }

    debounceRef.current = setTimeout(async () => {
      setIsSearching(true);
      try {
        const res = await searchAddress(value);
        setAddressSuggestions(res.data.data ?? []);
        setShowSuggestions(true);
      } catch {
        setAddressSuggestions([]);
      } finally {
        setIsSearching(false);
      }
    }, 300);
  }, []);

  const handleSelectAddress = async (placeId: string, description: string) => {
    setShowSuggestions(false);
    setAddressQuery(description);
    setLocation((prev) => ({ ...prev, home_address: description }));
    setIsGeocoding(true);
    try {
      const res = await geocodePlace(placeId);
      const { address, latitude, longitude } = res.data.data;
      setLocation((prev) => ({
        ...prev,
        home_address: address || description,
        home_latitude: latitude,
        home_longitude: longitude,
      }));
      setAddressQuery(address || description);
      if (latitude && longitude) {
        toast.success("Address resolved with coordinates");
      } else {
        toast.warning("Address found but coordinates unavailable");
      }
    } catch {
      toast.error("Failed to resolve address coordinates");
    } finally {
      setIsGeocoding(false);
    }
  };

  const {
    register,
    handleSubmit,
    formState: { errors, isDirty },
    reset,
  } = useForm<ProfileForm>({
    resolver: zodResolver(profileSchema),
    mode: "onBlur",
    defaultValues: {
      name: "",
      email: "",
    },
  });

  useEffect(() => {
    if (user) {
      reset({
        name: user.name,
        email: user.email,
      });
    }
  }, [user, reset]);

  useEffect(() => {
    api.get("/user/settings").then((res) => {
      const data = res.data;
      setLocation({
        home_address: data.home_address ?? null,
        home_zip_code: data.home_zip_code ?? null,
        home_latitude: data.home_latitude ?? null,
        home_longitude: data.home_longitude ?? null,
      });
      setAddressQuery(data.home_address ?? "");
    }).catch(() => {
      // Location settings may not exist yet
    });
  }, []);

  const saveLocation = async () => {
    setIsLocationSaving(true);
    try {
      await api.put("/user/settings", {
        home_address: location.home_address,
        home_zip_code: location.home_zip_code,
        home_latitude: location.home_latitude,
        home_longitude: location.home_longitude,
      });
      toast.success("Location saved");
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to save location"));
    } finally {
      setIsLocationSaving(false);
    }
  };

  const detectLocation = () => {
    if (!navigator.geolocation) {
      toast.error("Geolocation is not supported by your browser");
      return;
    }
    if (window.location.protocol !== "https:" && window.location.hostname !== "localhost") {
      toast.error("Location detection requires HTTPS. Please use the address search instead, or access this site over HTTPS.");
      return;
    }
    setIsDetecting(true);
    navigator.geolocation.getCurrentPosition(
      (position) => {
        setLocation((prev) => ({
          ...prev,
          home_latitude: position.coords.latitude,
          home_longitude: position.coords.longitude,
        }));
        setIsDetecting(false);
        toast.success("Location detected");
      },
      (error) => {
        setIsDetecting(false);
        if (error.message?.includes("secure origin") || error.code === 1) {
          toast.error("Location access denied. Use the address search to set your coordinates instead.");
        } else {
          toast.error(`Location error: ${error.message}`);
        }
      },
      { timeout: 10000, enableHighAccuracy: false }
    );
  };

  const onSubmit = async (data: ProfileForm) => {
    setIsLoading(true);
    try {
      await api.put("/profile", data);
      await fetchUser();
      toast.success("Profile updated successfully");
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to update profile"));
    } finally {
      setIsLoading(false);
    }
  };

  const handleDeleteAccount = async () => {
    if (deleteConfirmation !== user?.email) {
      toast.error("Please type your email to confirm");
      return;
    }

    setIsDeleting(true);
    try {
      await api.delete("/profile");
      toast.success("Account deleted");
      window.location.href = "/login";
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to delete account"));
    } finally {
      setIsDeleting(false);
    }
  };


  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Profile</h1>
        <p className="text-muted-foreground">
          Manage your account settings and profile information.{" "}
          <HelpLink articleId="profile" />
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Profile Information</CardTitle>
          <CardDescription>
            Update your account details and personal information.
          </CardDescription>
        </CardHeader>
        <form onSubmit={handleSubmit(onSubmit)}>
          <CardContent className="space-y-6">
            {/* Hero avatar section with upload */}
            <AvatarUpload user={user} onAvatarUpdated={fetchUser} />

            <Separator />

            {/* Form fields */}
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="name">Name</Label>
                <Input
                  id="name"
                  {...register("name")}
                  disabled={isLoading}
                />
                {errors.name && (
                  <p className="text-sm text-destructive">
                    {errors.name.message}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  type="email"
                  {...register("email")}
                  disabled={isLoading}
                />
                {errors.email && (
                  <p className="text-sm text-destructive">
                    {errors.email.message}
                  </p>
                )}
              </div>
            </div>
          </CardContent>
          <CardFooter className="flex justify-end">
            <SaveButton isDirty={isDirty} isSaving={isLoading} />
          </CardFooter>
        </form>
      </Card>

      {/* Group Memberships (from auth user) */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-lg">
            <UsersRound className="h-5 w-5" />
            Group Memberships
          </CardTitle>
          <CardDescription>
            Groups determine your permissions and access levels.
            {isAdminUser(user) && (
              <>
                {" "}
                <Link href="/configuration/groups" className="text-primary hover:underline">
                  Manage groups
                </Link>
              </>
            )}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex flex-wrap gap-2">
            {user?.groups && user.groups.length > 0 ? (
              user.groups.map((g) =>
                isAdminUser(user) ? (
                  <Link key={g.id} href="/configuration/groups">
                    <Badge variant="secondary" className="cursor-pointer hover:bg-secondary/80">
                      {g.name}
                    </Badge>
                  </Link>
                ) : (
                  <Badge key={g.id} variant="secondary">
                    {g.name}
                  </Badge>
                )
              )
            ) : (
              <span className="text-sm text-muted-foreground">
                No groups assigned
              </span>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Shopping Location */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-lg">
            <MapPin className="h-5 w-5" />
            Shopping Location
          </CardTitle>
          <CardDescription>
            Set your home location to find nearby stores and compare local prices.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2 relative" ref={suggestionsRef}>
            <Label htmlFor="home_address">Home Address</Label>
            <div className="relative">
              <Input
                id="home_address"
                placeholder="Start typing an address..."
                value={addressQuery}
                onChange={(e) => handleAddressInput(e.target.value)}
                onFocus={() => addressSuggestions.length > 0 && setShowSuggestions(true)}
                disabled={isOffline || isGeocoding}
                autoComplete="off"
              />
              {(isSearching || isGeocoding) && (
                <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-muted-foreground" />
              )}
            </div>
            {showSuggestions && addressSuggestions.length > 0 && (
              <div className="absolute z-50 w-full mt-1 bg-popover border rounded-md shadow-md max-h-60 overflow-auto">
                {addressSuggestions.map((s) => (
                  <button
                    key={s.place_id}
                    type="button"
                    className="w-full text-left px-3 py-2 text-sm hover:bg-accent hover:text-accent-foreground transition-colors"
                    onClick={() => handleSelectAddress(s.place_id, s.description)}
                  >
                    <MapPin className="inline-block h-3.5 w-3.5 mr-2 text-muted-foreground" />
                    {s.description}
                  </button>
                ))}
              </div>
            )}
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="space-y-2">
              <Label htmlFor="home_zip_code">ZIP Code</Label>
              <Input
                id="home_zip_code"
                placeholder="12345"
                maxLength={10}
                value={location.home_zip_code ?? ""}
                onChange={(e) =>
                  setLocation((prev) => ({ ...prev, home_zip_code: e.target.value }))
                }
                disabled={isOffline}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="home_latitude">Latitude</Label>
              <Input
                id="home_latitude"
                type="number"
                step="any"
                placeholder="Auto-detected"
                value={location.home_latitude ?? ""}
                readOnly
                className="bg-muted"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="home_longitude">Longitude</Label>
              <Input
                id="home_longitude"
                type="number"
                step="any"
                placeholder="Auto-detected"
                value={location.home_longitude ?? ""}
                readOnly
                className="bg-muted"
              />
            </div>
          </div>
          <div className="flex gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={isOffline || isDetecting}
              onClick={detectLocation}
            >
              {isDetecting ? (
                <Loader2 className="h-4 w-4 mr-1 animate-spin" />
              ) : (
                <Navigation className="h-4 w-4 mr-1" />
              )}
              Use My Location
            </Button>
            <SaveButton
              type="button"
              isDirty={true}
              isSaving={isLocationSaving}
              onClick={saveLocation}
              disabled={isOffline}
            />
          </div>
          <p className="text-sm text-muted-foreground">
            Your location is used to discover nearby stores and get local pricing. Coordinates are auto-detected or derived from your address.
          </p>
        </CardContent>
      </Card>

      {/* Danger Zone */}
      <Card className="border-destructive">
        <CardHeader>
          <CardTitle className="text-destructive">Danger Zone</CardTitle>
          <CardDescription>
            Irreversible and destructive actions.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex items-center justify-between">
            <div>
              <p className="font-medium">Delete Account</p>
              <p className="text-sm text-muted-foreground">
                Permanently delete your account and all associated data.
              </p>
            </div>
            <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
              <DialogTrigger asChild>
                <Button variant="destructive">Delete Account</Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle className="flex items-center gap-2">
                    <AlertTriangle className="h-5 w-5 text-destructive" />
                    Delete Account
                  </DialogTitle>
                  <DialogDescription>
                    This action cannot be undone. This will permanently delete
                    your account and remove all associated data.
                  </DialogDescription>
                </DialogHeader>
                <div className="space-y-4 py-4">
                  <p className="text-sm text-muted-foreground">
                    Please type <strong>{user?.email}</strong> to confirm:
                  </p>
                  <Input
                    value={deleteConfirmation}
                    onChange={(e) => setDeleteConfirmation(e.target.value)}
                    placeholder="your@email.com"
                  />
                </div>
                <DialogFooter>
                  <Button
                    variant="outline"
                    onClick={() => {
                      setDeleteDialogOpen(false);
                      setDeleteConfirmation("");
                    }}
                  >
                    Cancel
                  </Button>
                  <Button
                    variant="destructive"
                    onClick={handleDeleteAccount}
                    disabled={isDeleting || deleteConfirmation !== user?.email}
                  >
                    {isDeleting && (
                      <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    )}
                    Delete Account
                  </Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
