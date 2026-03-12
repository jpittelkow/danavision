import { redirect } from "next/navigation";

export default function NotificationTemplatesRedirect() {
  redirect("/configuration/notifications?tab=templates");
}
