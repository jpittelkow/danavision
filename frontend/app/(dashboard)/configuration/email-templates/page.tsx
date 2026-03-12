import { redirect } from "next/navigation";

export default function EmailTemplatesRedirect() {
  redirect("/configuration/notifications?tab=templates");
}
