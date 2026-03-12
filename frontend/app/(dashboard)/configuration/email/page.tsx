import { redirect } from "next/navigation";

export default function EmailSettingsRedirect() {
  redirect("/configuration/notifications?tab=email");
}
