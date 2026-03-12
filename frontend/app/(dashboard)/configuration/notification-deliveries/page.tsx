import { redirect } from "next/navigation";

export default function DeliveryLogRedirect() {
  redirect("/configuration/notifications?tab=delivery-log");
}
