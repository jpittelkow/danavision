"use client";

import { useState, useRef } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { toast } from "sonner";
import {
  Search,
  Loader2,
  ShoppingCart,
  Plus,
  Clock,
  Store,
  ImagePlus,
  Type,
  Upload,
  X,
  MapPin,
} from "lucide-react";
import { usePageTitle } from "@/lib/use-page-title";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { getErrorMessage } from "@/lib/utils";
import { api } from "@/lib/api";
import {
  searchProducts,
  fetchLists,
  addItem,
  type ProductSearchResult,
  type ShoppingList,
} from "@/lib/api/shopping";

interface SearchHistoryEntry {
  id: number;
  query: string;
  created_at: string;
}

function ProductResultCard({
  product,
  lists,
  selectedListId,
  setSelectedListId,
  onAdd,
  isAdding,
}: {
  product: ProductSearchResult;
  lists: ShoppingList[];
  selectedListId: string;
  setSelectedListId: (id: string) => void;
  onAdd: (listId: number, product: ProductSearchResult) => void;
  isAdding: boolean;
}) {
  return (
    <Card>
      <CardHeader className="pb-3">
        <div className="flex items-start gap-3">
          {product.image_url ? (
            <img
              src={product.image_url}
              alt={product.product_name}
              className="h-16 w-16 rounded-md object-cover border shrink-0"
            />
          ) : (
            <div className="h-16 w-16 rounded-md bg-muted flex items-center justify-center shrink-0">
              <ShoppingCart className="h-6 w-6 text-muted-foreground" />
            </div>
          )}
          <div className="min-w-0 flex-1">
            <CardTitle className="text-base line-clamp-2">
              {product.product_name}
            </CardTitle>
            <div className="flex items-center gap-2 mt-1">
              {product.retailer && (
                <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                  <Store className="h-3 w-3" />
                  {product.retailer}
                </span>
              )}
              {product.price != null && (
                <Badge variant="secondary">
                  ${Number(product.price).toFixed(2)}
                </Badge>
              )}
            </div>
          </div>
        </div>
      </CardHeader>
      <CardContent className="pt-0">
        <div className="flex items-center gap-2">
          <Select value={selectedListId} onValueChange={setSelectedListId}>
            <SelectTrigger className="h-8 flex-1 text-xs">
              <SelectValue placeholder="Select a list" />
            </SelectTrigger>
            <SelectContent>
              {lists.map((list) => (
                <SelectItem key={list.id} value={list.id.toString()}>
                  {list.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Button
            size="sm"
            variant="outline"
            className="gap-1 shrink-0"
            disabled={isAdding || !selectedListId}
            onClick={() => onAdd(parseInt(selectedListId), product)}
          >
            <Plus className="h-3.5 w-3.5" />
            Add
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

export default function ProductSearchPage() {
  usePageTitle("Product Search");

  const [query, setQuery] = useState("");
  const [results, setResults] = useState<ProductSearchResult[]>([]);
  const [selectedListId, setSelectedListId] = useState<string>("");
  const [shopLocal, setShopLocal] = useState(false);
  const [searchMode, setSearchMode] = useState<"text" | "image">("text");
  const [selectedImage, setSelectedImage] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Fetch lists for the "Add to List" dropdown
  const { data: listsResponse } = useQuery({
    queryKey: ["shopping-lists"],
    queryFn: fetchLists,
  });

  const lists: ShoppingList[] = listsResponse?.data?.data ?? [];

  // Fetch search history
  const { data: historyResponse } = useQuery({
    queryKey: ["search-history"],
    queryFn: () => api.get<{ data: SearchHistoryEntry[] }>("/search-history"),
  });

  const searchHistory: SearchHistoryEntry[] =
    historyResponse?.data?.data ?? [];

  // Text search mutation
  const searchMutation = useMutation({
    mutationFn: (q: string) => searchProducts(q, { shop_local: shopLocal }),
    onSuccess: (response) => {
      setResults(response.data?.data ?? []);
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Search failed"));
    },
  });

  // Image search mutation
  const imageSearchMutation = useMutation({
    mutationFn: (file: File) => {
      const formData = new FormData();
      formData.append("image", file);
      if (shopLocal) formData.append("shop_local", "1");
      return api.post<{ data: ProductSearchResult[] }>("/product-search/image", formData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    },
    onSuccess: (response) => {
      setResults(response.data?.data ?? []);
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Image search failed"));
    },
  });

  // Add to list mutation
  const addToListMutation = useMutation({
    mutationFn: ({
      listId,
      product,
    }: {
      listId: number;
      product: ProductSearchResult;
    }) =>
      addItem(listId, {
        product_name: product.product_name,
        upc: product.upc,
        retailer: product.retailer,
        url: product.url,
      }),
    onSuccess: () => {
      toast.success("Added to list");
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to add to list"));
    },
  });

  function handleSearch(e: React.FormEvent) {
    e.preventDefault();
    if (!query.trim()) return;
    searchMutation.mutate(query.trim());
  }

  function handleImageSelect(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    setSelectedImage(file);
    setImagePreview(URL.createObjectURL(file));
  }

  function handleImageSearch() {
    if (!selectedImage) return;
    imageSearchMutation.mutate(selectedImage);
  }

  function clearImage() {
    setSelectedImage(null);
    setImagePreview(null);
    if (fileInputRef.current) fileInputRef.current.value = "";
  }

  function handleHistoryClick(q: string) {
    setSearchMode("text");
    setQuery(q);
    searchMutation.mutate(q);
  }

  function handleDrop(e: React.DragEvent) {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith("image/")) {
      setSelectedImage(file);
      setImagePreview(URL.createObjectURL(file));
    }
  }

  const isSearching = searchMutation.isPending || imageSearchMutation.isPending;
  const hasSearched = searchMutation.isSuccess || imageSearchMutation.isSuccess;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
          <Search className="h-6 w-6" />
          Product Search
        </h1>
        <p className="text-muted-foreground mt-1">
          Search for products across retailers and add them to your lists.
        </p>
      </div>

      <div className="flex flex-col gap-6 lg:flex-row">
        {/* Main content */}
        <div className="flex-1 space-y-6">
          <Tabs value={searchMode} onValueChange={(v) => setSearchMode(v as "text" | "image")}>
            <TabsList>
              <TabsTrigger value="text" className="gap-2">
                <Type className="h-4 w-4" />
                Text Search
              </TabsTrigger>
              <TabsTrigger value="image" className="gap-2">
                <ImagePlus className="h-4 w-4" />
                Image Search
              </TabsTrigger>
            </TabsList>

            <TabsContent value="text" className="mt-4 space-y-3">
              <form onSubmit={handleSearch} className="flex gap-2">
                <Input
                  type="text"
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  placeholder="Search for a product..."
                  className="flex-1"
                />
                <Button
                  type="submit"
                  disabled={searchMutation.isPending || !query.trim()}
                  className="gap-2"
                >
                  {searchMutation.isPending ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <Search className="h-4 w-4" />
                  )}
                  Search
                </Button>
              </form>
              <div className="flex items-center gap-2">
                <Switch
                  id="shop-local"
                  checked={shopLocal}
                  onCheckedChange={setShopLocal}
                />
                <Label htmlFor="shop-local" className="text-sm flex items-center gap-1.5 cursor-pointer">
                  <MapPin className="h-3.5 w-3.5" />
                  Prefer local prices
                </Label>
              </div>
            </TabsContent>

            <TabsContent value="image" className="mt-4">
              <div className="space-y-4">
                {imagePreview ? (
                  <div className="relative inline-block">
                    <img
                      src={imagePreview}
                      alt="Selected product"
                      className="max-h-48 rounded-lg border object-contain"
                    />
                    <Button
                      variant="destructive"
                      size="icon"
                      className="absolute -top-2 -right-2 h-6 w-6 rounded-full"
                      onClick={clearImage}
                    >
                      <X className="h-3 w-3" />
                    </Button>
                  </div>
                ) : (
                  <div
                    className="flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 cursor-pointer hover:border-primary/50 transition-colors"
                    onClick={() => fileInputRef.current?.click()}
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={handleDrop}
                  >
                    <Upload className="h-8 w-8 text-muted-foreground mb-2" />
                    <p className="text-sm text-muted-foreground text-center">
                      Click to upload or drag and drop an image
                    </p>
                    <p className="text-xs text-muted-foreground mt-1">
                      PNG, JPG, WEBP up to 10MB
                    </p>
                  </div>
                )}
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/*"
                  className="hidden"
                  aria-label="Upload product image"
                  onChange={handleImageSelect}
                />
                <div className="flex items-center gap-4">
                  <Button
                    type="button"
                    onClick={handleImageSearch}
                    disabled={imageSearchMutation.isPending || !selectedImage}
                    className="gap-2"
                  >
                    {imageSearchMutation.isPending ? (
                      <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                      <Search className="h-4 w-4" />
                    )}
                    Search by Image
                  </Button>
                  <div className="flex items-center gap-2">
                    <Switch
                      id="shop-local-image"
                      checked={shopLocal}
                      onCheckedChange={setShopLocal}
                    />
                    <Label htmlFor="shop-local-image" className="text-sm flex items-center gap-1.5 cursor-pointer">
                      <MapPin className="h-3.5 w-3.5" />
                      Prefer local prices
                    </Label>
                  </div>
                </div>
              </div>
            </TabsContent>
          </Tabs>

          {/* Results */}
          {isSearching ? (
            <div className="grid gap-4 md:grid-cols-2">
              {Array.from({ length: 4 }).map((_, i) => (
                <Skeleton key={i} className="h-36 rounded-lg" />
              ))}
            </div>
          ) : results.length > 0 ? (
            <div className="grid gap-4 md:grid-cols-2">
              {results.map((product, idx) => (
                <ProductResultCard
                  key={idx}
                  product={product}
                  lists={lists}
                  selectedListId={selectedListId}
                  setSelectedListId={setSelectedListId}
                  onAdd={(listId, p) =>
                    addToListMutation.mutate({ listId, product: p })
                  }
                  isAdding={addToListMutation.isPending}
                />
              ))}
            </div>
          ) : hasSearched ? (
            <div className="flex flex-col items-center justify-center rounded-lg border border-dashed p-12 text-center">
              <Search className="h-10 w-10 text-muted-foreground mb-3" />
              <h3 className="text-base font-semibold">No results found</h3>
              <p className="text-sm text-muted-foreground mt-1">
                Try a different search term or check your spelling.
              </p>
            </div>
          ) : null}
        </div>

        {/* Search history sidebar */}
        {searchHistory.length > 0 && (
          <div className="lg:w-64 shrink-0">
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium flex items-center gap-1.5">
                  <Clock className="h-3.5 w-3.5" />
                  Recent Searches
                </CardTitle>
              </CardHeader>
              <CardContent className="pt-0">
                <div className="space-y-1">
                  {searchHistory.slice(0, 10).map((entry) => (
                    <button
                      key={entry.id}
                      className="w-full text-left text-sm px-2 py-1.5 rounded-md hover:bg-muted transition-colors truncate text-muted-foreground hover:text-foreground"
                      onClick={() => handleHistoryClick(entry.query)}
                    >
                      {entry.query}
                    </button>
                  ))}
                </div>
              </CardContent>
            </Card>
          </div>
        )}
      </div>
    </div>
  );
}
