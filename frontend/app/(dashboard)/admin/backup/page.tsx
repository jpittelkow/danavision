import { redirect } from "next/navigation";

export default function BackupRedirect() {
  redirect("/configuration/backup");
}
