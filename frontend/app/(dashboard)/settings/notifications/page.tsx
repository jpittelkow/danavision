import { redirect } from "next/navigation";

export default function NotificationsRedirect() {
  redirect("/user/preferences?tab=notifications");
}
