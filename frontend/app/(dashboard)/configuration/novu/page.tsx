import { redirect } from "next/navigation";

export default function NovuSettingsRedirect() {
  redirect("/configuration/notifications?tab=novu");
}
